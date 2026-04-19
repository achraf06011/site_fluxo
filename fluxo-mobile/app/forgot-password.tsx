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

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

export default function ForgotPasswordScreen() {
  const [email, setEmail] = useState("");
  const [loading, setLoading] = useState(false);

  async function handleSend() {
    if (!email.trim()) {
      Alert.alert("Erreur", "Email obligatoire.");
      return;
    }

    try {
      setLoading(true);

      const res = await fetch(`${API_BASE}/forgot_password_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          email: email.trim(),
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
        Alert.alert("Erreur", data.message || "Impossible d’envoyer le lien.");
        return;
      }

      Alert.alert(
        "Email envoyé",
        data.message || "Si cet email existe, un lien a été envoyé.",
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
      <Stack.Screen options={{ title: "Mot de passe oublié" }} />

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
              <Text style={styles.cardTitle}>Mot de passe oublié</Text>
              <View style={styles.secureRow}>
                <Ionicons name="mail-outline" size={14} color="#6b7280" />
                <Text style={styles.secureText}> email sécurisé</Text>
              </View>
            </View>

            <Text style={styles.desc}>
              Entre ton adresse email. Si elle existe, on t’enverra un lien pour
              réinitialiser ton mot de passe.
            </Text>

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

            <TouchableOpacity
              style={[styles.mainBtn, loading && styles.btnDisabled]}
              onPress={handleSend}
              disabled={loading}
            >
              <Ionicons name="paper-plane-outline" size={18} color="#fff" />
              <Text style={styles.mainBtnText}>
                {loading ? "Envoi..." : "Envoyer le lien"}
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