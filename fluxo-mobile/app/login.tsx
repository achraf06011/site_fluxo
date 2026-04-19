import React, { useState } from "react";
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ScrollView,
  Alert,
  KeyboardAvoidingView,
  Platform,
} from "react-native";
import { Stack, router } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import { saveUser, savePendingVerification } from "../utils/auth";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

export default function LoginScreen() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPwd, setShowPwd] = useState(false);
  const [loading, setLoading] = useState(false);

  async function handleLogin() {
    if (!email.trim()) {
      Alert.alert("Erreur", "Email obligatoire.");
      return;
    }

    if (!password) {
      Alert.alert("Erreur", "Mot de passe obligatoire.");
      return;
    }

    try {
      setLoading(true);

      const res = await fetch(`${API_BASE}/login_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          email: email.trim(),
          password,
        }),
      });

      const raw = await res.text();

      if (!raw || raw.trim() === "") {
        Alert.alert("Erreur", "Réponse vide du serveur.");
        return;
      }

      let data: any = null;

      try {
        data = JSON.parse(raw);
      } catch {
        Alert.alert("Erreur", `Réponse non JSON: ${raw.substring(0, 180)}`);
        return;
      }

      if (!data.ok) {
        Alert.alert("Connexion", data.message || "Connexion impossible.");
        return;
      }

      if (data.need_verification) {
        if (!data.id_user || !data.email) {
          Alert.alert(
            "Erreur",
            "Réponse incomplète pour la vérification email.",
          );
          return;
        }

        await savePendingVerification({
          id_user: Number(data.id_user),
          email: String(data.email),
        });

        Alert.alert(
          "Email non vérifié",
          data.message || "Tu dois vérifier ton email avant de te connecter.",
          [
            {
              text: "OK",
              onPress: () =>
                router.replace({
                  pathname: "/verifier-email",
                  params: {
                    id_user: String(data.id_user),
                    email: String(data.email),
                  },
                }),
            },
          ],
        );
        return;
      }

      if (!data.user) {
        Alert.alert("Erreur", "Utilisateur introuvable dans la réponse.");
        return;
      }

      await saveUser(data.user);

      Alert.alert("Succès", data.message || "Connexion réussie.", [
        {
          text: "OK",
          onPress: () => router.replace("/(tabs)"),
        },
      ]);
    } catch (e: any) {
      Alert.alert("Erreur", String(e?.message || e || "Erreur serveur."));
    } finally {
      setLoading(false);
    }
  }

  return (
    <>
      <Stack.Screen options={{ title: "Connexion" }} />

      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === "ios" ? "padding" : undefined}
      >
        <ScrollView
          style={styles.container}
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
        >
          <View style={styles.hero}>
            <Text style={styles.heroTitle}>Re-bienvenue 👋</Text>
            <Text style={styles.heroText}>
              Connecte-toi pour publier, discuter, acheter et gérer tes
              annonces.
            </Text>

            <View style={styles.badgesWrap}>
              <View style={styles.badge}>
                <Text style={styles.badgeText}>Messagerie</Text>
              </View>
              <View style={styles.badge}>
                <Text style={styles.badgeText}>Paiement direct</Text>
              </View>
              <View style={styles.badge}>
                <Text style={styles.badgeText}>Panier</Text>
              </View>
              <View style={styles.badge}>
                <Text style={styles.badgeText}>Avis</Text>
              </View>
            </View>
          </View>

          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <Text style={styles.cardTitle}>Connexion</Text>
              <View style={styles.secureRow}>
                <Ionicons
                  name="lock-closed-outline"
                  size={14}
                  color="#6b7280"
                />
                <Text style={styles.secureText}> sécurisé</Text>
              </View>
            </View>

            <Text style={styles.label}>Email</Text>
            <TextInput
              style={styles.input}
              placeholder="exemple@gmail.com"
              placeholderTextColor="#9ca3af"
              keyboardType="email-address"
              autoCapitalize="none"
              value={email}
              onChangeText={setEmail}
            />

            <Text style={styles.label}>Mot de passe</Text>
            <View style={styles.passwordWrap}>
              <TextInput
                style={styles.passwordInput}
                placeholder="Ton mot de passe"
                placeholderTextColor="#9ca3af"
                secureTextEntry={!showPwd}
                value={password}
                onChangeText={setPassword}
              />

              <TouchableOpacity
                style={styles.eyeBtn}
                onPress={() => setShowPwd((v) => !v)}
              >
                <Ionicons
                  name={showPwd ? "eye-off-outline" : "eye-outline"}
                  size={20}
                  color="#4b5563"
                />
              </TouchableOpacity>
            </View>

            <TouchableOpacity
              style={styles.forgotWrap}
              onPress={() => router.push("/forgot-password")}
            >
              <Text style={styles.forgotText}>Mot de passe oublié ?</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.mainBtn, loading && styles.btnDisabled]}
              onPress={handleLogin}
              disabled={loading}
            >
              <Ionicons name="log-in-outline" size={18} color="#fff" />
              <Text style={styles.mainBtnText}>
                {loading ? "Connexion..." : "Se connecter"}
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.secondaryBtn}
              onPress={() => router.push("/register")}
            >
              <Text style={styles.secondaryBtnText}>Créer un compte</Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </>
  );
}

const styles = StyleSheet.create({
  flex: {
    flex: 1,
  },
  container: {
    flex: 1,
    backgroundColor: "#f8fafc",
  },
  content: {
    padding: 16,
    paddingBottom: 30,
    justifyContent: "center",
    minHeight: "100%",
  },
  hero: {
    backgroundColor: "#111827",
    borderRadius: 24,
    padding: 20,
    marginBottom: 16,
  },
  heroTitle: {
    fontSize: 34,
    fontWeight: "900",
    color: "#fff",
    marginBottom: 8,
  },
  heroText: {
    fontSize: 15,
    lineHeight: 22,
    color: "rgba(255,255,255,0.82)",
  },
  badgesWrap: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 8,
    marginTop: 14,
  },
  badge: {
    backgroundColor: "#2563eb",
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  badgeText: {
    color: "#fff",
    fontSize: 12,
    fontWeight: "700",
  },
  card: {
    backgroundColor: "#fff",
    borderRadius: 24,
    padding: 18,
    shadowColor: "#000",
    shadowOpacity: 0.06,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 4 },
    elevation: 3,
  },
  cardHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 16,
    flexWrap: "wrap",
    gap: 8,
  },
  cardTitle: {
    fontSize: 30,
    fontWeight: "900",
    color: "#111827",
  },
  secureRow: {
    flexDirection: "row",
    alignItems: "center",
  },
  secureText: {
    color: "#6b7280",
    fontSize: 13,
  },
  label: {
    fontSize: 15,
    fontWeight: "700",
    color: "#374151",
    marginBottom: 8,
    marginTop: 8,
  },
  input: {
    backgroundColor: "#f9fafb",
    borderWidth: 1,
    borderColor: "#e5e7eb",
    borderRadius: 14,
    paddingHorizontal: 14,
    paddingVertical: 14,
    fontSize: 16,
    color: "#111827",
  },
  passwordWrap: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#f9fafb",
    borderWidth: 1,
    borderColor: "#e5e7eb",
    borderRadius: 14,
    overflow: "hidden",
  },
  passwordInput: {
    flex: 1,
    paddingHorizontal: 14,
    paddingVertical: 14,
    fontSize: 16,
    color: "#111827",
  },
  eyeBtn: {
    width: 54,
    alignItems: "center",
    justifyContent: "center",
  },
  forgotWrap: {
    alignSelf: "flex-end",
    marginTop: 10,
    marginBottom: 16,
  },
  forgotText: {
    fontSize: 13,
    color: "#6b7280",
    fontWeight: "600",
  },
  mainBtn: {
    backgroundColor: "#111827",
    borderRadius: 14,
    paddingVertical: 15,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
  },
  btnDisabled: {
    opacity: 0.7,
  },
  mainBtnText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "800",
  },
  secondaryBtn: {
    marginTop: 12,
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 14,
    paddingVertical: 15,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#fff",
  },
  secondaryBtnText: {
    color: "#374151",
    fontSize: 16,
    fontWeight: "700",
  },
});
