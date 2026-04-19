import React, { useMemo, useState } from "react";
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
import { Stack, router, useLocalSearchParams } from "expo-router";
import { Ionicons } from "@expo/vector-icons";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

export default function ResetPasswordScreen() {
  const params = useLocalSearchParams<{ token?: string; email?: string }>();

  const token = useMemo(() => String(params.token || ""), [params.token]);
  const email = useMemo(() => String(params.email || ""), [params.email]);

  const [password, setPassword] = useState("");
  const [password2, setPassword2] = useState("");
  const [showPwd, setShowPwd] = useState(false);
  const [loading, setLoading] = useState(false);

  async function handleReset() {
    if (!token.trim()) {
      Alert.alert("Erreur", "Lien invalide.");
      return;
    }

    if (password.length < 6) {
      Alert.alert("Erreur", "Mot de passe trop court (minimum 6 caractères).");
      return;
    }

    if (password !== password2) {
      Alert.alert("Erreur", "Les mots de passe ne correspondent pas.");
      return;
    }

    try {
      setLoading(true);

      const res = await fetch(`${API_BASE}/reset_password_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          token,
          password,
          password2,
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
        Alert.alert("Erreur", data.message || "Réinitialisation impossible.");
        return;
      }

      Alert.alert(
        "Succès",
        data.message || "Mot de passe modifié avec succès.",
        [
          {
            text: "OK",
            onPress: () => router.replace("/login"),
          },
        ]
      );
    } catch (e: any) {
      Alert.alert("Erreur", String(e?.message || e || "Erreur serveur."));
    } finally {
      setLoading(false);
    }
  }

  return (
    <>
      <Stack.Screen options={{ title: "Nouveau mot de passe" }} />

      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === "ios" ? "padding" : undefined}
      >
        <ScrollView
          style={styles.container}
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
        >
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <Text style={styles.cardTitle}>Nouveau mot de passe</Text>
              <View style={styles.secureRow}>
                <Ionicons name="key-outline" size={14} color="#6b7280" />
                <Text style={styles.secureText}> sécurisé</Text>
              </View>
            </View>

            <Text style={styles.desc}>
              Choisis un nouveau mot de passe
              {email ? ` pour : ${email}` : ""}.
            </Text>

            <Text style={styles.label}>Nouveau mot de passe</Text>
            <View style={styles.passwordWrap}>
              <TextInput
                style={styles.passwordInput}
                placeholder="Minimum 6 caractères"
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

            <Text style={styles.label}>Confirmer le mot de passe</Text>
            <TextInput
              style={styles.input}
              placeholder="Répète le mot de passe"
              placeholderTextColor="#9ca3af"
              secureTextEntry
              value={password2}
              onChangeText={setPassword2}
            />

            <TouchableOpacity
              style={[styles.mainBtn, loading && styles.btnDisabled]}
              onPress={handleReset}
              disabled={loading}
            >
              <Ionicons name="checkmark-circle-outline" size={18} color="#fff" />
              <Text style={styles.mainBtnText}>
                {loading ? "Enregistrement..." : "Enregistrer"}
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.secondaryBtn}
              onPress={() => router.replace("/login")}
            >
              <Text style={styles.secondaryBtnText}>Retour connexion</Text>
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
    flexGrow: 1,
    justifyContent: "center",
    padding: 16,
    paddingBottom: 30,
  },
  card: {
    backgroundColor: "#fff",
    borderRadius: 24,
    padding: 20,
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
    marginBottom: 14,
    flexWrap: "wrap",
    gap: 8,
  },
  cardTitle: {
    fontSize: 28,
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
  desc: {
    color: "#6b7280",
    fontSize: 15,
    lineHeight: 22,
    marginBottom: 16,
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
  mainBtn: {
    marginTop: 20,
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