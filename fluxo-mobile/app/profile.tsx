import React, { useCallback, useState } from "react";
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
import { getUser, logoutUser } from "../utils/auth";
import { Feather } from "@expo/vector-icons";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

type ProfileType = {
  id_user: number;
  nom: string;
  email: string;
  role: string;
  date_inscription: string;
};

export default function ProfileScreen() {
  const [loading, setLoading] = useState(true);
  const [user, setUser] = useState<any>(null);
  const [profile, setProfile] = useState<ProfileType | null>(null);
  const [errorMsg, setErrorMsg] = useState("");

  const [nom, setNom] = useState("");
  const [email, setEmail] = useState("");
  const [oldPassword, setOldPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [newPassword2, setNewPassword2] = useState("");
  const [saving, setSaving] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);

  const loadProfile = useCallback(async () => {
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
        `${API_BASE}/profile_mobile.php?user_id=${Number(currentUser.id_user)}`
      );
      const data = await res.json();

      if (!data.ok) {
        setErrorMsg(data.message || "Erreur chargement profil.");
        return;
      }

      const p = data.profile || null;
      setProfile(p);

      if (p) {
        setNom(p.nom || "");
        setEmail(p.email || "");
      }
    } catch (error: any) {
      setErrorMsg(String(error));
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      loadProfile();
    }, [loadProfile])
  );

  async function saveProfile() {
    if (!user) return;

    if (!nom.trim() || nom.trim().length < 2) {
      Alert.alert("Erreur", "Nom invalide.");
      return;
    }

    if (!email.trim() || !email.includes("@")) {
      Alert.alert("Erreur", "Email invalide.");
      return;
    }

    try {
      setSaving(true);

      const res = await fetch(`${API_BASE}/profile_update_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: Number(user.id_user),
          nom: nom.trim(),
          email: email.trim(),
        }),
      });

      const data = await res.json();

      if (!data.ok) {
        Alert.alert("Erreur", data.message || "Modification impossible.");
        return;
      }

      Alert.alert("Succès", data.message || "Profil mis à jour.");
      await loadProfile();
    } catch (e) {
      Alert.alert("Erreur", "Erreur serveur.");
    } finally {
      setSaving(false);
    }
  }

  async function changePassword() {
    if (!user) return;

    if (!oldPassword || !newPassword || !newPassword2) {
      Alert.alert("Erreur", "Remplis tous les champs du mot de passe.");
      return;
    }

    if (newPassword.length < 6) {
      Alert.alert("Erreur", "Le nouveau mot de passe doit contenir au moins 6 caractères.");
      return;
    }

    if (newPassword !== newPassword2) {
      Alert.alert("Erreur", "La confirmation du mot de passe est incorrecte.");
      return;
    }

    try {
      setSavingPassword(true);

      const res = await fetch(`${API_BASE}/password_update_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: Number(user.id_user),
          old_password: oldPassword,
          new_password: newPassword,
          new_password2: newPassword2,
        }),
      });

      const data = await res.json();

      if (!data.ok) {
        Alert.alert("Erreur", data.message || "Impossible de changer le mot de passe.");
        return;
      }

      setOldPassword("");
      setNewPassword("");
      setNewPassword2("");
      Alert.alert("Succès", data.message || "Mot de passe modifié.");
    } catch (e) {
      Alert.alert("Erreur", "Erreur serveur.");
    } finally {
      setSavingPassword(false);
    }
  }

  async function handleLogout() {
    Alert.alert("Déconnexion", "Tu veux vraiment te déconnecter ?", [
      { text: "Annuler", style: "cancel" },
      {
        text: "Oui",
        style: "destructive",
        onPress: async () => {
          await logoutUser();
          router.replace("/login");
        },
      },
    ]);
  }

  if (loading) {
    return (
      <>
        <Stack.Screen options={{ title: "Mon profil" }} />
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
        <Stack.Screen options={{ title: "Mon profil" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Connexion requise</Text>
          <Text style={styles.errorText}>Tu dois te connecter.</Text>

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
        <Stack.Screen options={{ title: "Mon profil" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Erreur</Text>
          <Text style={styles.errorText}>{errorMsg || "Profil introuvable."}</Text>

          <TouchableOpacity
            style={styles.primaryBtn}
            onPress={() => router.back()}
          >
            <Text style={styles.primaryBtnText}>Retour</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  return (
    <>
      <Stack.Screen options={{ title: "Mon profil" }} />

      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <Text style={styles.pageTitle}>Mon profil</Text>

        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Informations</Text>
          <Text style={styles.infoText}>Nom : {profile.nom}</Text>
          <Text style={styles.infoText}>Email : {profile.email}</Text>
          <Text style={styles.infoText}>Rôle : {profile.role}</Text>
          <Text style={styles.infoText}>Inscription : {profile.date_inscription}</Text>

          <View style={styles.quickActions}>
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
              <Text style={styles.darkBtnText}>Mes annonces</Text>
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
          <TextInput style={styles.input} value={nom} onChangeText={setNom} />

          <Text style={styles.label}>Email</Text>
          <TextInput
            style={styles.input}
            value={email}
            onChangeText={setEmail}
            keyboardType="email-address"
            autoCapitalize="none"
          />

          <TouchableOpacity
            style={[styles.darkBtn, saving && styles.disabledBtn]}
            onPress={saveProfile}
            disabled={saving}
          >
            <Text style={styles.darkBtnText}>
              {saving ? "Enregistrement..." : "Enregistrer"}
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
            secureTextEntry
          />

          <Text style={styles.label}>Nouveau mot de passe</Text>
          <TextInput
            style={styles.input}
            value={newPassword}
            onChangeText={setNewPassword}
            secureTextEntry
          />

          <Text style={styles.label}>Confirmer</Text>
          <TextInput
            style={styles.input}
            value={newPassword2}
            onChangeText={setNewPassword2}
            secureTextEntry
          />

          <TouchableOpacity
            style={[styles.secondaryBtnBig, savingPassword && styles.disabledBtn]}
            onPress={changePassword}
            disabled={savingPassword}
          >
            <Text style={styles.secondaryBtnBigText}>
              {savingPassword ? "Modification..." : "Modifier"}
            </Text>
          </TouchableOpacity>
        </View>

        <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout}>
          <Feather name="log-out" size={16} color="#fff" />
          <Text style={styles.logoutBtnText}>Déconnexion</Text>
        </TouchableOpacity>
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
    paddingBottom: 40,
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
  pageTitle: {
    fontSize: 28,
    fontWeight: "900",
    color: "#111827",
    marginBottom: 14,
  },
  card: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
    marginBottom: 14,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: "900",
    color: "#111827",
    marginBottom: 14,
  },
  infoText: {
    fontSize: 15,
    color: "#374151",
    marginBottom: 8,
  },
  quickActions: {
    gap: 10,
    marginTop: 16,
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
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 16,
    color: "#111827",
    backgroundColor: "#fff",
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
    borderColor: "#111827",
    borderRadius: 12,
    paddingVertical: 13,
    alignItems: "center",
    backgroundColor: "#fff",
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
    marginTop: 10,
  },
  darkBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 15,
  },
  secondaryBtnBig: {
    borderWidth: 1,
    borderColor: "#111827",
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    backgroundColor: "#fff",
    marginTop: 14,
  },
  secondaryBtnBigText: {
    color: "#111827",
    fontWeight: "800",
    fontSize: 15,
  },
  logoutBtn: {
    backgroundColor: "#dc2626",
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
    marginTop: 4,
  },
  logoutBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 15,
  },
  disabledBtn: {
    opacity: 0.7,
  },
});