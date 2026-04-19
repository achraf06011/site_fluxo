import React, { useEffect, useState } from "react";
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
import {
  clearPendingVerification,
  getPendingVerification,
  saveUser,
} from "../utils/auth";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

export default function VerifierEmailScreen() {
  const params = useLocalSearchParams<{ id_user?: string; email?: string }>();

  const [idUser, setIdUser] = useState<number>(0);
  const [email, setEmail] = useState("");
  const [code, setCode] = useState("");
  const [loading, setLoading] = useState(false);
  const [loadingResend, setLoadingResend] = useState(false);

  useEffect(() => {
    async function boot() {
      const pending = await getPendingVerification();

      const uid = Number(params.id_user || pending?.id_user || 0);
      const em = String(params.email || pending?.email || "");

      if (!uid || !em) {
        Alert.alert("Erreur", "Aucune vérification en attente.", [
          {
            text: "OK",
            onPress: () => router.replace("/login"),
          },
        ]);
        return;
      }

      setIdUser(uid);
      setEmail(em);
    }

    boot();
  }, [params.id_user, params.email]);

  async function verifyCode() {
    if (!idUser) {
      Alert.alert("Erreur", "Utilisateur invalide.");
      return;
    }

    if (!code.trim() || code.trim().length < 6) {
      Alert.alert("Erreur", "Code de vérification invalide.");
      return;
    }

    try {
      setLoading(true);

      const res = await fetch(`${API_BASE}/verify_email_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          id_user: idUser,
          code: code.trim(),
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
        Alert.alert("Vérification", data.message || "Code invalide.");
        return;
      }

      if (data.user) {
        await saveUser(data.user);
      }

      await clearPendingVerification();

      Alert.alert("Succès", data.message || "Email vérifié avec succès.", [
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

  async function resendCode() {
    if (!idUser) {
      Alert.alert("Erreur", "Utilisateur invalide.");
      return;
    }

    try {
      setLoadingResend(true);

      const res = await fetch(`${API_BASE}/resend_verification_code_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          id_user: idUser,
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
        Alert.alert("Renvoyer code", data.message || "Impossible d’envoyer le code.");
        return;
      }

      Alert.alert("Succès", data.message || "Code renvoyé.");
    } catch (e: any) {
      Alert.alert("Erreur", String(e?.message || e || "Erreur serveur."));
    } finally {
      setLoadingResend(false);
    }
  }

  return (
    <>
      <Stack.Screen options={{ title: "Vérifier email" }} />

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
            <Text style={styles.title}>Vérifier ton email</Text>
            <Text style={styles.subTitle}>
              Un code de vérification a été envoyé à :
            </Text>
            <Text style={styles.email}>{email || "-"}</Text>

            <Text style={styles.label}>Code de vérification</Text>
            <TextInput
              style={styles.input}
              placeholder="Ex: 123456"
              placeholderTextColor="#9ca3af"
              keyboardType="number-pad"
              value={code}
              onChangeText={setCode}
              maxLength={6}
            />

            <TouchableOpacity
              style={[styles.mainBtn, loading && styles.btnDisabled]}
              onPress={verifyCode}
              disabled={loading}
            >
              <Text style={styles.mainBtnText}>
                {loading ? "Vérification..." : "Vérifier mon email"}
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.secondaryBtn, loadingResend && styles.btnDisabled]}
              onPress={resendCode}
              disabled={loadingResend}
            >
              <Text style={styles.secondaryBtnText}>
                {loadingResend ? "Envoi..." : "Renvoyer le code"}
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.thirdBtn}
              onPress={() => router.replace("/login")}
            >
              <Text style={styles.thirdBtnText}>Retour connexion</Text>
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
    minHeight: "100%",
    justifyContent: "center",
    padding: 16,
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
  title: {
    fontSize: 30,
    fontWeight: "900",
    color: "#111827",
    marginBottom: 8,
  },
  subTitle: {
    fontSize: 15,
    color: "#4b5563",
  },
  email: {
    fontSize: 16,
    color: "#111827",
    fontWeight: "800",
    marginTop: 6,
    marginBottom: 18,
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
    marginTop: 18,
    backgroundColor: "#111827",
    borderRadius: 14,
    paddingVertical: 15,
    alignItems: "center",
    justifyContent: "center",
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
  thirdBtn: {
    marginTop: 12,
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 10,
  },
  thirdBtnText: {
    color: "#374151",
    fontSize: 15,
    fontWeight: "700",
  },
  btnDisabled: {
    opacity: 0.7,
  },
});