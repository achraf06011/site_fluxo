import React, { useEffect, useState } from "react";
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  TextInput,
} from "react-native";
import { Stack, router, useFocusEffect } from "expo-router";
import { getUser } from "../utils/auth";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

type UserProfileType = {
  id_user: number;
  nom: string;
  email: string;
  role: string;
  date_inscription: string;
};

export default function MonCompteScreen() {
  const [loading, setLoading] = useState(true);
  const [savingProfile, setSavingProfile] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);
  const [errorMsg, setErrorMsg] = useState("");
  const [user, setUser] = useState<any>(null);
  const [profile, setProfile] = useState<UserProfileType | null>(null);

  const [nom, setNom] = useState("");
  const [email, setEmail] = useState("");

  const [oldPassword, setOldPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [newPassword2, setNewPassword2] = useState("");

  async function loadProfile() {
    try {
      setLoading(true);
      setErrorMsg("");

      const currentUser = await getUser();
      setUser(currentUser);

      if (!currentUser) {
        setErrorMsg("Connexion requise.");
        return;
      }

      const res = await fetch(
        `${API_BASE}/profile_mobile.php?user_id=${Number(currentUser.id_user)}`,
        {
          headers: {
            Accept: "application/json",
          },
        }
      );

      const rawText = await res.text();

      if (!rawText || rawText.trim() === "") {
        setErrorMsg("Réponse vide du serveur.");
        return;
      }

      let data: any = null;

      try {
        data = JSON.parse(rawText);
      } catch (e) {
        setErrorMsg(`Réponse non JSON: ${rawText.substring(0, 180)}`);
        return;
      }

      if (!data.ok) {
        setErrorMsg(data.message || "Erreur chargement profil");
        return;
      }

      const p = data.user || null;
      setProfile(p);

      setNom(p?.nom || "");
      setEmail(p?.email || "");
    } catch (error: any) {
      setErrorMsg(String(error));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadProfile();
  }, []);

  useFocusEffect(
    React.useCallback(() => {
      loadProfile();
    }, [])
  );

  async function saveProfile() {
    if (!user) return;

    if (!nom.trim() || nom.trim().length < 2) {
      Alert.alert("Erreur", "Nom invalide.");
      return;
    }

    const emailTrim = email.trim();
    if (!emailTrim || !emailTrim.includes("@")) {
      Alert.alert("Erreur", "Email invalide.");
      return;
    }

    try {
      setSavingProfile(true);

      const res = await fetch(`${API_BASE}/profile_update_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          user_id: Number(user.id_user),
          nom: nom.trim(),
          email: emailTrim,
        }),
      });

      const rawText = await res.text();

      if (!rawText || rawText.trim() === "") {
        Alert.alert("Erreur", "Réponse vide du serveur.");
        return;
      }

      let data: any = null;

      try {
        data = JSON.parse(rawText);
      } catch (e) {
        Alert.alert("Erreur", `Réponse non JSON: ${rawText.substring(0, 180)}`);
        return;
      }

      if (!data.ok) {
        Alert.alert("Erreur", data.message || "Impossible de modifier le profil.");
        return;
      }

      Alert.alert("Succès", data.message || "Profil mis à jour.");
      await loadProfile();
    } catch (e: any) {
      Alert.alert("Erreur", String(e?.message || e || "Erreur serveur."));
    } finally {
      setSavingProfile(false);
    }
  }

  async function savePassword() {
    if (!user) return;

    if (!oldPassword.trim()) {
      Alert.alert("Erreur", "Ancien mot de passe requis.");
      return;
    }

    if (newPassword.length < 6) {
      Alert.alert("Erreur", "Le nouveau mot de passe doit contenir au moins 6 caractères.");
      return;
    }

    if (newPassword !== newPassword2) {
      Alert.alert("Erreur", "Les mots de passe ne correspondent pas.");
      return;
    }

    try {
      setSavingPassword(true);

      const res = await fetch(`${API_BASE}/password_update_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          user_id: Number(user.id_user),
          old_password: oldPassword,
          new_password: newPassword,
          new_password2: newPassword2,
        }),
      });

      const rawText = await res.text();

      if (!rawText || rawText.trim() === "") {
        Alert.alert("Erreur", "Réponse vide du serveur.");
        return;
      }

      let data: any = null;

      try {
        data = JSON.parse(rawText);
      } catch (e) {
        Alert.alert("Erreur", `Réponse non JSON: ${rawText.substring(0, 180)}`);
        return;
      }

      if (!data.ok) {
        Alert.alert("Erreur", data.message || "Impossible de modifier le mot de passe.");
        return;
      }

      setOldPassword("");
      setNewPassword("");
      setNewPassword2("");

      Alert.alert("Succès", data.message || "Mot de passe modifié.");
    } catch (e: any) {
      Alert.alert("Erreur", String(e?.message || e || "Erreur serveur."));
    } finally {
      setSavingPassword(false);
    }
  }

  if (loading) {
    return (
      <>
        <Stack.Screen options={{ title: "Mon compte" }} />
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#2563eb" />
          <Text style={styles.loadingText}>Chargement...</Text>
        </View>
      </>
    );
  }

  if (!user) {
    return (
      <>
        <Stack.Screen options={{ title: "Mon compte" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Connexion requise</Text>
          <Text style={styles.errorText}>Tu dois te connecter pour voir ton compte.</Text>

          <TouchableOpacity
            style={styles.primaryBtn}
            onPress={() => router.push("/login")}
          >
            <Text style={styles.primaryBtnText}>Connexion</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  if (errorMsg || !profile) {
    return (
      <>
        <Stack.Screen options={{ title: "Mon compte" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Erreur</Text>
          <Text style={styles.errorText}>{errorMsg || "Erreur inconnue."}</Text>

          <TouchableOpacity
            style={styles.primaryBtn}
            onPress={() => router.push("/login")}
          >
            <Text style={styles.primaryBtnText}>Connexion</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  return (
    <>
      <Stack.Screen options={{ title: "Mon compte" }} />

      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <View style={styles.headerRow}>
          <Text style={styles.pageTitle}>Mon profil</Text>

          <TouchableOpacity
            style={styles.outlineBtn}
            onPress={() => router.push("/(tabs)")}
          >
            <Text style={styles.outlineBtnText}>Retour annonces</Text>
          </TouchableOpacity>
        </View>

        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Informations</Text>

          <Text style={styles.infoText}>
            <Text style={styles.infoLabel}>Nom: </Text>
            {profile.nom}
          </Text>

          <Text style={styles.infoText}>
            <Text style={styles.infoLabel}>Email: </Text>
            {profile.email}
          </Text>

          <Text style={styles.infoText}>
            <Text style={styles.infoLabel}>Rôle: </Text>
            {profile.role}
          </Text>

          <Text style={styles.infoText}>
            <Text style={styles.infoLabel}>Inscription: </Text>
            {profile.date_inscription}
          </Text>

          <View style={styles.actionsBox}>
            <TouchableOpacity
              style={styles.secondaryBtn}
              onPress={() => router.push("/mes-commandes")}
            >
              <Text style={styles.secondaryBtnText}>Mes commandes</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.darkBtn}
              onPress={() => router.push("/mes-annonces")}
            >
              <Text style={styles.darkBtnText}>Mes annonces (modifier)</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.secondaryBtn}
              onPress={() => router.push("/mes-ventes")}
            >
              <Text style={styles.secondaryBtnText}>Mes ventes</Text>
            </TouchableOpacity>
          </View>
        </View>

        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Modifier mon profil</Text>

          <Text style={styles.label}>Nom</Text>
          <TextInput
            style={styles.input}
            value={nom}
            onChangeText={setNom}
            placeholder="Nom"
          />

          <Text style={styles.label}>Email</Text>
          <TextInput
            style={styles.input}
            value={email}
            onChangeText={setEmail}
            placeholder="Email"
            keyboardType="email-address"
            autoCapitalize="none"
          />

          <TouchableOpacity
            style={[styles.darkBtn, savingProfile && styles.disabledBtn]}
            onPress={saveProfile}
            disabled={savingProfile}
          >
            <Text style={styles.darkBtnText}>
              {savingProfile ? "Enregistrement..." : "Enregistrer"}
            </Text>
          </TouchableOpacity>
        </View>

        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Changer mot de passe</Text>

          <Text style={styles.label}>Ancien mot de passe</Text>
          <TextInput
            style={styles.input}
            value={oldPassword}
            onChangeText={setOldPassword}
            placeholder="Ancien mot de passe"
            secureTextEntry
          />

          <Text style={styles.label}>Nouveau mot de passe</Text>
          <TextInput
            style={styles.input}
            value={newPassword}
            onChangeText={setNewPassword}
            placeholder="Nouveau mot de passe"
            secureTextEntry
          />

          <Text style={styles.label}>Confirmer</Text>
          <TextInput
            style={styles.input}
            value={newPassword2}
            onChangeText={setNewPassword2}
            placeholder="Confirmer le mot de passe"
            secureTextEntry
          />

          <TouchableOpacity
            style={[styles.secondaryBtn, savingPassword && styles.disabledBtn]}
            onPress={savePassword}
            disabled={savingPassword}
          >
            <Text style={styles.secondaryBtnText}>
              {savingPassword ? "Modification..." : "Modifier"}
            </Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f3f4f6",
  },
  content: {
    padding: 14,
    paddingBottom: 30,
  },
  center: {
    flex: 1,
    backgroundColor: "#fff",
    justifyContent: "center",
    alignItems: "center",
    padding: 24,
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: "#111827",
  },
  errorTitle: {
    fontSize: 24,
    fontWeight: "800",
    color: "#111827",
    marginBottom: 8,
  },
  errorText: {
    fontSize: 15,
    color: "#6b7280",
    textAlign: "center",
    marginBottom: 16,
  },
  headerRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    gap: 10,
    marginBottom: 14,
  },
  pageTitle: {
    fontSize: 24,
    fontWeight: "900",
    color: "#111827",
  },
  outlineBtn: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
    backgroundColor: "#fff",
  },
  outlineBtnText: {
    color: "#374151",
    fontWeight: "700",
  },
  card: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
    marginBottom: 14,
  },
  sectionTitle: {
    fontSize: 21,
    fontWeight: "900",
    color: "#111827",
    marginBottom: 14,
  },
  infoText: {
    fontSize: 15,
    color: "#374151",
    marginBottom: 8,
  },
  infoLabel: {
    fontWeight: "800",
    color: "#111827",
  },
  actionsBox: {
    marginTop: 16,
    gap: 10,
  },
  label: {
    fontSize: 14,
    color: "#374151",
    fontWeight: "700",
    marginBottom: 6,
    marginTop: 8,
  },
  input: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    backgroundColor: "#fff",
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 16,
    color: "#111827",
  },
  primaryBtn: {
    backgroundColor: "#2563eb",
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 12,
  },
  primaryBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 15,
  },
  secondaryBtn: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    backgroundColor: "#fff",
    marginTop: 14,
  },
  secondaryBtnText: {
    color: "#111827",
    fontWeight: "800",
    fontSize: 15,
  },
  darkBtn: {
    backgroundColor: "#111827",
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    marginTop: 14,
  },
  darkBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 15,
  },
  disabledBtn: {
    opacity: 0.7,
  },
});