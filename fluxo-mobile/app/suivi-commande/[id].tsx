import React, { useEffect, useState } from "react";
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
} from "react-native";
import { Stack, router, useLocalSearchParams } from "expo-router";
import { getUser } from "../../utils/auth";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

type StepType = {
  key: string;
  label: string;
  state: "Terminée" | "En cours" | "En attente";
};

type DetailType = {
  id_annonce: number;
  titre: string;
  quantite: number;
  prix_unitaire: number;
  line_total: number;
  vendeur_nom: string;
};

type OrderType = {
  id_order: number;
  statut: string;
  paiement_statut: string;
  date_commande: string;
  updated_at: string;
  mode_reception: string;
  telephone_client: string;
  ville_livraison: string;
  adresse_livraison: string;
  frais_livraison: number;
  statut_livraison: string;
  current_shipping_label: string;
};

export default function SuiviCommandeScreen() {
  const { id } = useLocalSearchParams();

  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState("");
  const [user, setUser] = useState<any>(null);
  const [order, setOrder] = useState<OrderType | null>(null);
  const [steps, setSteps] = useState<StepType[]>([]);
  const [details, setDetails] = useState<DetailType[]>([]);

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
          `${API_BASE}/suivi_commande_mobile.php?id=${id}&user_id=${Number(currentUser.id_user)}`
        );
        const data = await res.json();

        if (!data.ok) {
          setErrorMsg(data.message || "Erreur chargement suivi");
          return;
        }

        setOrder(data.order || null);
        setSteps(data.steps || []);
        setDetails(data.details || []);
      } catch (error: any) {
        setErrorMsg(String(error));
      } finally {
        setLoading(false);
      }
    }

    if (id) {
      loadData();
    }
  }, [id]);

  function money(x: number) {
    return `${Number(x || 0).toLocaleString("fr-FR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} DH`;
  }

  function stepBadgeStyle(state: string) {
    if (state === "Terminée") return styles.doneBadge;
    if (state === "En cours") return styles.currentBadge;
    return styles.waitBadge;
  }

  function stepBadgeTextStyle(state: string) {
    if (state === "Terminée") return styles.doneBadgeText;
    if (state === "En cours") return styles.currentBadgeText;
    return styles.waitBadgeText;
  }

  if (loading) {
    return (
      <>
        <Stack.Screen options={{ title: "Suivi commande" }} />
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
        <Stack.Screen options={{ title: "Suivi commande" }} />
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

  if (errorMsg || !order) {
    return (
      <>
        <Stack.Screen options={{ title: "Suivi commande" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Erreur</Text>
          <Text style={styles.errorText}>{errorMsg || "Commande introuvable"}</Text>
          <TouchableOpacity style={styles.primaryBtn} onPress={() => router.back()}>
            <Text style={styles.primaryBtnText}>Retour</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  return (
    <>
      <Stack.Screen options={{ title: `Suivi #${order.id_order}` }} />

      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <View style={styles.hero}>
          <View style={styles.headerRow}>
            <Text style={styles.pageTitle}>Suivi commande #{order.id_order}</Text>

            <TouchableOpacity
              style={styles.outlineBtn}
              onPress={() => router.back()}
            >
              <Text style={styles.outlineBtnText}>Retour</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.grid}>
            <View style={styles.infoCard}>
              <Text style={styles.infoLabel}>Statut commande</Text>
              <Text style={styles.infoValue}>{order.statut}</Text>
            </View>

            <View style={styles.infoCard}>
              <Text style={styles.infoLabel}>Paiement</Text>
              <Text style={styles.infoValue}>{order.paiement_statut}</Text>
            </View>

            <View style={styles.infoCard}>
              <Text style={styles.infoLabel}>Date commande</Text>
              <Text style={styles.infoValue}>{String(order.date_commande).substring(0, 10)}</Text>
            </View>

            <View style={styles.infoCard}>
              <Text style={styles.infoLabel}>Dernière mise à jour</Text>
              <Text style={styles.infoValue}>
                {order.updated_at ? String(order.updated_at).substring(0, 19) : "—"}
              </Text>
            </View>
          </View>

          <View style={styles.currentStatusBox}>
            <Text style={styles.currentStatusTitle}>Statut actuel</Text>
            <View style={styles.currentBadge}>
              <Text style={styles.currentBadgeText}>{order.current_shipping_label}</Text>
            </View>
          </View>

          <View style={styles.shippingInfo}>
            <View style={styles.infoLineBox}>
              <Text style={styles.infoLabel}>Mode réception</Text>
              <Text style={styles.infoValue}>
                {order.mode_reception === "LIVRAISON" ? "Livraison" : "Main propre"}
              </Text>
            </View>

            <View style={styles.infoLineBox}>
              <Text style={styles.infoLabel}>Téléphone</Text>
              <Text style={styles.infoValue}>{order.telephone_client || "—"}</Text>
            </View>

            <View style={styles.infoLineBox}>
              <Text style={styles.infoLabel}>Ville livraison</Text>
              <Text style={styles.infoValue}>{order.ville_livraison || "—"}</Text>
            </View>

            <View style={styles.infoLineBox}>
              <Text style={styles.infoLabel}>Adresse livraison</Text>
              <Text style={styles.infoValue}>{order.adresse_livraison || "—"}</Text>
            </View>

            <View style={styles.infoLineBox}>
              <Text style={styles.infoLabel}>Frais livraison</Text>
              <Text style={styles.infoValue}>{money(order.frais_livraison)}</Text>
            </View>
          </View>
        </View>

        <View style={styles.sectionCard}>
          <Text style={styles.sectionTitle}>Étapes</Text>

          {steps.map((step) => (
            <View key={step.key} style={styles.stepRow}>
              <Text style={styles.stepLabel}>{step.label}</Text>

              <View style={stepBadgeStyle(step.state)}>
                <Text style={stepBadgeTextStyle(step.state)}>{step.state}</Text>
              </View>
            </View>
          ))}
        </View>

        <View style={styles.sectionCard}>
          <Text style={styles.sectionTitle}>Produits de la commande</Text>

          {details.map((item, index) => (
            <View
              key={`${item.id_annonce}-${index}`}
              style={[
                styles.productRow,
                index !== details.length - 1 && styles.productBorder,
              ]}
            >
              <View style={{ flex: 1, paddingRight: 10 }}>
                <Text style={styles.productTitle}>{item.titre}</Text>
                <Text style={styles.productSub}>
                  Quantité: {item.quantite} · Vendeur: {item.vendeur_nom}
                </Text>
              </View>

              <Text style={styles.productPrice}>{money(item.line_total)}</Text>
            </View>
          ))}
        </View>
      </ScrollView>
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: "#f3f4f6" },
  content: { padding: 14, paddingBottom: 30 },
  center: {
    flex: 1,
    backgroundColor: "#fff",
    justifyContent: "center",
    alignItems: "center",
    padding: 24,
  },
  loadingText: { marginTop: 10, fontSize: 16, color: "#111827" },
  errorTitle: { fontSize: 24, fontWeight: "800", color: "#111827", marginBottom: 8 },
  errorText: { fontSize: 15, color: "#6b7280", textAlign: "center", marginBottom: 16 },
  hero: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
    marginBottom: 14,
  },
  headerRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    gap: 10,
    marginBottom: 14,
  },
  pageTitle: { fontSize: 26, fontWeight: "900", color: "#111827", flex: 1 },
  outlineBtn: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
    backgroundColor: "#fff",
  },
  outlineBtnText: { color: "#374151", fontWeight: "700" },
  grid: { gap: 10, marginBottom: 14 },
  infoCard: {
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    padding: 12,
  },
  infoLabel: { fontSize: 13, color: "#6b7280", marginBottom: 4 },
  infoValue: { fontSize: 16, color: "#111827", fontWeight: "800" },
  currentStatusBox: {
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    padding: 12,
    marginBottom: 12,
  },
  currentStatusTitle: { fontSize: 18, fontWeight: "900", color: "#111827", marginBottom: 10 },
  shippingInfo: { gap: 10 },
  infoLineBox: {
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    padding: 12,
  },
  sectionCard: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
    marginBottom: 14,
  },
  sectionTitle: { fontSize: 22, fontWeight: "900", color: "#111827", marginBottom: 14 },
  stepRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#f1f5f9",
  },
  stepLabel: { fontSize: 17, fontWeight: "800", color: "#111827", flex: 1, paddingRight: 10 },
  doneBadge: {
    backgroundColor: "#dcfce7",
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  doneBadgeText: { color: "#15803d", fontWeight: "800", fontSize: 12 },
  currentBadge: {
    backgroundColor: "#dbeafe",
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
    alignSelf: "flex-start",
  },
  currentBadgeText: { color: "#1d4ed8", fontWeight: "800", fontSize: 12 },
  waitBadge: {
    backgroundColor: "#ede9fe",
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  waitBadgeText: { color: "#6d28d9", fontWeight: "800", fontSize: 12 },
  productRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 12,
  },
  productBorder: {
    borderBottomWidth: 1,
    borderBottomColor: "#f1f5f9",
  },
  productTitle: { fontSize: 18, fontWeight: "900", color: "#111827" },
  productSub: { marginTop: 5, fontSize: 14, color: "#6b7280" },
  productPrice: { fontSize: 18, fontWeight: "900", color: "#111827" },
  primaryBtn: {
    backgroundColor: "#2563eb",
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 12,
  },
  primaryBtnText: { color: "#fff", fontWeight: "800", fontSize: 15 },
});