import React, { useEffect, useState } from "react";
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ActivityIndicator,
  TextInput,
  Alert,
  ScrollView,
} from "react-native";
import { Stack, router, useLocalSearchParams } from "expo-router";
import { getUser } from "../utils/auth";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

type SellerType = {
  id_user: number;
  nom: string;
  email: string;
};

type OrderType = {
  id_order: number;
  statut: string;
};

export default function LaisserAvisScreen() {
  const params = useLocalSearchParams();

  const orderId = Number(params.order ?? 0);
  const sellerId = Number(params.seller ?? 0);

  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [errorMsg, setErrorMsg] = useState("");
  const [user, setUser] = useState<any>(null);
  const [seller, setSeller] = useState<SellerType | null>(null);
  const [order, setOrder] = useState<OrderType | null>(null);

  const [rating, setRating] = useState<number>(0);
  const [comment, setComment] = useState("");

  useEffect(() => {
    async function loadData() {
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
          `${API_BASE}/laisser_avis_mobile.php?user_id=${Number(currentUser.id_user)}&order=${orderId}&seller=${sellerId}`
        );
        const data = await res.json();

        if (!data.ok) {
          setErrorMsg(data.message || "Erreur chargement avis");
          return;
        }

        setSeller(data.seller || null);
        setOrder(data.order || null);
      } catch (error: any) {
        setErrorMsg(String(error));
      } finally {
        setLoading(false);
      }
    }

    if (orderId > 0 && sellerId > 0) {
      loadData();
    } else {
      setLoading(false);
      setErrorMsg("Paramètres invalides.");
    }
  }, [orderId, sellerId]);

  async function sendReview() {
    if (!user || !seller || !order) return;

    if (rating < 1 || rating > 5) {
      Alert.alert("Erreur", "Choisis une note entre 1 et 5.");
      return;
    }

    if (comment.trim().length > 1000) {
      Alert.alert("Erreur", "Commentaire trop long (max 1000 caractères).");
      return;
    }

    try {
      setSending(true);

      const res = await fetch(`${API_BASE}/avis_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: Number(user.id_user),
          id_order: orderId,
          id_seller: sellerId,
          rating,
          comment: comment.trim(),
        }),
      });

      const data = await res.json();

      if (!data.ok) {
        Alert.alert("Erreur", data.message || "Erreur envoi avis.");
        return;
      }

      Alert.alert("Succès", data.message || "Avis envoyé avec succès.", [
        {
          text: "OK",
          onPress: () => router.replace(`/commande/${orderId}`),
        },
      ]);
    } catch (error: any) {
      Alert.alert("Erreur", String(error));
    } finally {
      setSending(false);
    }
  }

  function StarButton({
    value,
    active,
    onPress,
  }: {
    value: number;
    active: boolean;
    onPress: () => void;
  }) {
    return (
      <TouchableOpacity style={styles.starBtn} onPress={onPress}>
        <Text style={[styles.starText, active && styles.starTextActive]}>
          {active ? "★" : "☆"}
        </Text>
      </TouchableOpacity>
    );
  }

  if (loading) {
    return (
      <>
        <Stack.Screen options={{ title: "Laisser un avis" }} />
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
        <Stack.Screen options={{ title: "Laisser un avis" }} />
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

  if (errorMsg || !seller || !order) {
    return (
      <>
        <Stack.Screen options={{ title: "Laisser un avis" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Erreur</Text>
          <Text style={styles.errorText}>{errorMsg || "Page introuvable"}</Text>
          <TouchableOpacity style={styles.primaryBtn} onPress={() => router.back()}>
            <Text style={styles.primaryBtnText}>Retour</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  return (
    <>
      <Stack.Screen options={{ title: "Laisser un avis" }} />

      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <View style={styles.card}>
          <View style={styles.headerRow}>
            <View style={{ flex: 1 }}>
              <Text style={styles.title}>Laisser un avis</Text>
              <Text style={styles.subtitle}>
                Commande #{order.id_order} · Vendeur: {seller.nom}
              </Text>
            </View>

            <TouchableOpacity
              style={styles.backBtn}
              onPress={() => router.replace(`/commande/${order.id_order}`)}
            >
              <Text style={styles.backBtnText}>Retour commande</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.block}>
            <Text style={styles.label}>Note (1 à 5)</Text>

            <View style={styles.starsRow}>
              {[1, 2, 3, 4, 5].map((n) => (
                <StarButton
                  key={n}
                  value={n}
                  active={n <= rating}
                  onPress={() => setRating(n)}
                />
              ))}
            </View>

            <Text style={styles.ratingValue}>
              {rating > 0 ? `${rating}/5` : "Choisir une note"}
            </Text>
          </View>

          <View style={styles.block}>
            <Text style={styles.label}>Commentaire</Text>
            <TextInput
              style={styles.textarea}
              placeholder="Optionnel (qualité, communication, livraison...)"
              placeholderTextColor="#9ca3af"
              multiline
              value={comment}
              onChangeText={setComment}
              maxLength={1000}
              textAlignVertical="top"
            />
            <Text style={styles.counter}>{comment.length}/1000</Text>
          </View>

          <TouchableOpacity
            style={[styles.submitBtn, sending && styles.submitBtnDisabled]}
            onPress={sendReview}
            disabled={sending}
          >
            <Text style={styles.submitBtnText}>
              {sending ? "Envoi..." : "Envoyer l’avis"}
            </Text>
          </TouchableOpacity>

          <Text style={styles.noteInfo}>
            Avis possible uniquement si la commande est payée.
          </Text>
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
  card: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
  },
  headerRow: {
    gap: 12,
    marginBottom: 20,
  },
  title: {
    fontSize: 28,
    fontWeight: "900",
    color: "#111827",
  },
  subtitle: {
    marginTop: 4,
    fontSize: 15,
    color: "#6b7280",
  },
  backBtn: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
    alignSelf: "flex-start",
    backgroundColor: "#fff",
  },
  backBtnText: {
    color: "#374151",
    fontWeight: "800",
  },
  block: {
    marginBottom: 18,
  },
  label: {
    fontSize: 16,
    fontWeight: "800",
    color: "#111827",
    marginBottom: 10,
  },
  starsRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    marginBottom: 8,
  },
  starBtn: {
    paddingVertical: 6,
    paddingHorizontal: 2,
  },
  starText: {
    fontSize: 34,
    color: "#cbd5e1",
  },
  starTextActive: {
    color: "#f59e0b",
  },
  ratingValue: {
    fontSize: 14,
    color: "#6b7280",
  },
  textarea: {
    minHeight: 140,
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 14,
    backgroundColor: "#fff",
    paddingHorizontal: 14,
    paddingTop: 14,
    paddingBottom: 14,
    fontSize: 15,
    color: "#111827",
  },
  counter: {
    marginTop: 8,
    fontSize: 13,
    color: "#6b7280",
    textAlign: "right",
  },
  submitBtn: {
    backgroundColor: "#111827",
    borderRadius: 14,
    paddingVertical: 15,
    alignItems: "center",
  },
  submitBtnDisabled: {
    opacity: 0.6,
  },
  submitBtnText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "800",
  },
  noteInfo: {
    marginTop: 10,
    fontSize: 13,
    color: "#6b7280",
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
});