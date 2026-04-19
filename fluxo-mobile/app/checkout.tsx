import React, { useState } from "react";
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  Alert,
  ScrollView,
} from "react-native";
import { Stack } from "expo-router";
import { getUser } from "../utils/auth";
import * as WebBrowser from "expo-web-browser";

const API = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

export default function CheckoutScreen() {
  const [phone, setPhone] = useState("");
  const [adresse, setAdresse] = useState("");
  const [loading, setLoading] = useState(false);

  async function handleCheckout() {
    const user = await getUser();

    if (!user) {
      Alert.alert("Erreur", "Connecte-toi");
      return;
    }

    if (!phone || phone.trim().length < 8) {
      Alert.alert("Erreur", "Téléphone invalide");
      return;
    }

    try {
      setLoading(true);

      const res = await fetch(`${API}/checkout_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          user_id: Number(user.id_user),
          mode_reception: "PICKUP",
          ville_livraison: "",
          telephone_client: phone.trim(),
          adresse_livraison: adresse.trim(),
        }),
      });

      const rawText = await res.text();

      if (!rawText || rawText.trim() === "") {
        Alert.alert(
          "Erreur serveur",
          "Réponse vide depuis checkout_mobile.php"
        );
        return;
      }

      let data: any = null;

      try {
        data = JSON.parse(rawText);
      } catch {
        Alert.alert(
          "Erreur serveur",
          `Réponse non JSON :\n${rawText.substring(0, 500)}`
        );
        return;
      }

      if (!res.ok || !data?.ok) {
        Alert.alert("Erreur", data?.message || "Erreur checkout");
        return;
      }

      if (!data.url || typeof data.url !== "string") {
        Alert.alert("Erreur", "URL Stripe introuvable.");
        return;
      }

      await WebBrowser.openBrowserAsync(data.url);
    } catch (e: any) {
      Alert.alert("Erreur", String(e?.message || e || "Erreur serveur"));
    } finally {
      setLoading(false);
    }
  }

  return (
    <>
      <Stack.Screen options={{ title: "Checkout" }} />

      <ScrollView contentContainerStyle={styles.container}>
        <Text style={styles.title}>Checkout</Text>
        <Text style={styles.subtitle}>
          Entre ton téléphone puis clique sur le bouton pour ouvrir Stripe.
        </Text>

        <Text style={styles.label}>Téléphone</Text>
        <TextInput
          style={styles.input}
          value={phone}
          onChangeText={setPhone}
          placeholder="0612345678"
          keyboardType="numeric"
        />

        <Text style={styles.label}>Adresse (optionnel)</Text>
        <TextInput
          style={[styles.input, styles.textarea]}
          value={adresse}
          onChangeText={setAdresse}
          placeholder="Adresse ou lieu de rencontre"
          multiline
        />

        <TouchableOpacity
          style={[styles.btn, loading && styles.btnDisabled]}
          onPress={handleCheckout}
          disabled={loading}
        >
          <Text style={styles.btnText}>
            {loading ? "Chargement..." : "Payer avec Stripe"}
          </Text>
        </TouchableOpacity>
      </ScrollView>
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    padding: 20,
    backgroundColor: "#fff",
    flexGrow: 1,
  },
  title: {
    fontSize: 26,
    fontWeight: "bold",
    marginBottom: 10,
    color: "#111827",
  },
  subtitle: {
    fontSize: 16,
    color: "#6b7280",
    marginBottom: 22,
  },
  label: {
    marginTop: 10,
    marginBottom: 6,
    fontWeight: "700",
    fontSize: 16,
    color: "#111827",
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
  textarea: {
    minHeight: 100,
    textAlignVertical: "top",
  },
  btn: {
    backgroundColor: "#111827",
    paddingVertical: 16,
    borderRadius: 12,
    marginTop: 28,
    alignItems: "center",
  },
  btnDisabled: {
    opacity: 0.7,
  },
  btnText: {
    color: "#fff",
    textAlign: "center",
    fontWeight: "800",
    fontSize: 16,
  },
});