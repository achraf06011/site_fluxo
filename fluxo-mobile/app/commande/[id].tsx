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

type DetailType = {
  id_annonce: number;
  titre: string;
  quantite: number;
  prix_unitaire: number;
  line_total: number;
  id_vendeur: number;
  vendeur_nom: string;
};

type VendorType = {
  id_seller: number;
  vendeur_nom: string;
  annonces: { id_annonce: number; titre: string }[];
  already_reviewed: boolean;
};

type OrderType = {
  id_order: number;
  statut: string;
  total: number;
  mode_reception: string;
  ville_livraison: string | null;
  frais_livraison: number;
  telephone_client: string;
  adresse_livraison: string;
};

type PaiementType = {
  methode: string;
  statut: string;
};

export default function CommandeDetailsScreen() {
  const { id } = useLocalSearchParams();

  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState("");
  const [user, setUser] = useState<any>(null);
  const [order, setOrder] = useState<OrderType | null>(null);
  const [paiement, setPaiement] = useState<PaiementType | null>(null);
  const [details, setDetails] = useState<DetailType[]>([]);
  const [vendors, setVendors] = useState<VendorType[]>([]);

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
          `${API_BASE}/commande_details_mobile.php?id=${id}&user_id=${Number(currentUser.id_user)}`
        );
        const data = await res.json();

        if (!data.ok) {
          setErrorMsg(data.message || "Erreur chargement commande");
          return;
        }

        setOrder(data.order || null);
        setPaiement(data.paiement || null);
        setDetails(data.details || []);
        setVendors(data.vendors || []);
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

  if (loading) {
    return (
      <>
        <Stack.Screen options={{ title: "Commande" }} />
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
        <Stack.Screen options={{ title: "Commande" }} />
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

  if (errorMsg || !order || !paiement) {
    return (
      <>
        <Stack.Screen options={{ title: "Commande" }} />
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

  const isPaid =
    String(order.statut || "").toUpperCase() === "PAYE" ||
    String(paiement.statut || "").toUpperCase() === "ACCEPTE";

  const heroTitle = isPaid ? "Commande confirmée" : "Commande en attente de paiement";
  const heroSub = isPaid
    ? `Commande #${order.id_order}`
    : `Commande #${order.id_order} · Paiement non terminé`;

  return (
    <>
      <Stack.Screen options={{ title: `Commande #${order.id_order}` }} />

      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <View style={styles.hero}>
          <View style={styles.heroTop}>
            <View>
              <Text style={styles.heroTitle}>{heroTitle}</Text>
              <Text style={styles.heroSub}>{heroSub}</Text>
            </View>

            <View style={{ alignItems: "flex-end" }}>
              <Text style={styles.totalLabel}>Total</Text>
              <Text style={styles.totalValue}>{money(order.total)}</Text>
            </View>
          </View>

          <View style={styles.infoGrid}>
            <View style={styles.infoItem}>
              <Text style={styles.infoLabel}>Mode réception</Text>
              <Text style={styles.infoValue}>
                {order.mode_reception === "LIVRAISON" ? "Livraison" : "Main propre"}
              </Text>
            </View>

            <View style={styles.infoItem}>
              <Text style={styles.infoLabel}>Téléphone</Text>
              <Text style={styles.infoValue}>{order.telephone_client || "—"}</Text>
            </View>

            {order.mode_reception === "LIVRAISON" ? (
              <View style={styles.infoItem}>
                <Text style={styles.infoLabel}>Ville livraison</Text>
                <Text style={styles.infoValue}>{order.ville_livraison || "—"}</Text>
              </View>
            ) : null}
          </View>

          <View style={styles.infoItemBlock}>
            <Text style={styles.infoLabel}>
              {order.mode_reception === "LIVRAISON"
                ? "Adresse de livraison"
                : "Lieu de rencontre / adresse"}
            </Text>
            <Text style={styles.infoValue}>{order.adresse_livraison || "—"}</Text>
          </View>

          {order.mode_reception === "LIVRAISON" ? (
            <View style={styles.infoItemBlock}>
              <Text style={styles.infoLabel}>Frais livraison</Text>
              <Text style={styles.infoValue}>{money(order.frais_livraison)}</Text>
            </View>
          ) : null}

          {isPaid ? (
            <TouchableOpacity
              style={styles.followBtn}
              onPress={() => router.push(`/suivi-commande/${order.id_order}`)}
            >
              <Text style={styles.followBtnText}>Suivi commande</Text>
            </TouchableOpacity>
          ) : (
            <View style={styles.pendingBox}>
              <Text style={styles.pendingText}>
                Le suivi détaillé sera disponible après paiement accepté.
              </Text>
            </View>
          )}
        </View>

        <View style={styles.sectionRow}>
          <View style={styles.sectionCard}>
            <Text style={styles.sectionTitle}>Détails</Text>

            {details.length === 0 ? (
              <Text style={styles.emptyText}>Aucun détail trouvé.</Text>
            ) : (
              details.map((d, index) => (
                <View
                  key={`${d.id_annonce}-${index}`}
                  style={[
                    styles.detailRow,
                    index !== details.length - 1 && styles.detailBorder,
                  ]}
                >
                  <View style={{ flex: 1, paddingRight: 10 }}>
                    <Text style={styles.detailTitle}>{d.titre}</Text>
                    <Text style={styles.detailSub}>
                      Quantité : {d.quantite} · Vendeur : {d.vendeur_nom}
                    </Text>
                  </View>

                  <Text style={styles.detailPrice}>{money(d.line_total)}</Text>
                </View>
              ))
            )}
          </View>

          <View style={styles.sectionCard}>
            <Text style={styles.sectionTitle}>Paiement</Text>

            <View style={styles.payBox}>
              <Text style={styles.payLine}>Méthode : {paiement.methode || "STRIPE"}</Text>
              <Text style={styles.payLine}>Statut : {paiement.statut}</Text>
            </View>

            {isPaid ? (
              <View style={{ marginTop: 18 }}>
                <Text style={styles.sectionTitleSmall}>Laisser un avis</Text>

                {vendors.length === 0 ? (
                  <Text style={styles.emptyText}>Aucun vendeur trouvé.</Text>
                ) : (
                  vendors.map((v) => (
                    <View key={v.id_seller} style={styles.vendorBox}>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.vendorName}>{v.vendeur_nom}</Text>
                        <Text style={styles.vendorSub}>
                          {v.annonces.map((a) => a.titre).join(" · ")}
                        </Text>
                      </View>

                      {v.already_reviewed ? (
                        <View style={styles.doneBadge}>
                          <Text style={styles.doneBadgeText}>Déjà noté</Text>
                        </View>
                      ) : (
                        <TouchableOpacity
                          style={styles.noteBtn}
                          onPress={() =>
                            router.push({
                              pathname: "/laisser-avis",
                              params: {
                                order: String(order.id_order),
                                seller: String(v.id_seller),
                              },
                            })
                          }
                        >
                          <Text style={styles.noteBtnText}>Noter</Text>
                        </TouchableOpacity>
                      )}
                    </View>
                  ))
                )}

                <Text style={styles.smallNote}>
                  Avis possible seulement après paiement, 1 avis par vendeur et par commande.
                </Text>
              </View>
            ) : (
              <View style={{ marginTop: 18 }}>
                <Text style={styles.smallNote}>
                  La commande n’est pas encore payée. Les avis seront disponibles après validation du paiement.
                </Text>
              </View>
            )}

            <View style={{ marginTop: 18, gap: 10 }}>
              <TouchableOpacity
                style={styles.backToAdsBtn}
                onPress={() => router.push("/(tabs)")}
              >
                <Text style={styles.backToAdsBtnText}>Retour annonces</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.secondaryBtn}
                onPress={() => router.push("/menu")}
              >
                <Text style={styles.secondaryBtnText}>Mon compte</Text>
              </TouchableOpacity>
            </View>
          </View>
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
  heroTop: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    gap: 12,
    marginBottom: 18,
  },
  heroTitle: { fontSize: 28, fontWeight: "900", color: "#111827" },
  heroSub: { fontSize: 15, color: "#6b7280", marginTop: 4 },
  totalLabel: { fontSize: 13, color: "#6b7280" },
  totalValue: { fontSize: 22, fontWeight: "900", color: "#111827", marginTop: 4 },
  infoGrid: { gap: 12, marginBottom: 12 },
  infoItem: {
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    padding: 12,
  },
  infoItemBlock: {
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    padding: 12,
    marginBottom: 12,
  },
  infoLabel: { fontSize: 13, color: "#6b7280", marginBottom: 5 },
  infoValue: { fontSize: 16, color: "#111827", fontWeight: "800" },
  followBtn: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    backgroundColor: "#fff",
    marginTop: 4,
  },
  followBtnText: { color: "#111827", fontWeight: "800", fontSize: 16 },
  pendingBox: {
    borderWidth: 1,
    borderColor: "#fde68a",
    backgroundColor: "#fffbeb",
    borderRadius: 12,
    padding: 14,
    marginTop: 4,
  },
  pendingText: {
    color: "#92400e",
    fontWeight: "700",
    fontSize: 14,
    textAlign: "center",
  },
  sectionRow: { gap: 14 },
  sectionCard: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
  },
  sectionTitle: { fontSize: 22, fontWeight: "900", color: "#111827", marginBottom: 14 },
  sectionTitleSmall: { fontSize: 18, fontWeight: "900", color: "#111827", marginBottom: 12 },
  detailRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 12,
  },
  detailBorder: {
    borderBottomWidth: 1,
    borderBottomColor: "#f1f5f9",
  },
  detailTitle: { fontSize: 18, fontWeight: "900", color: "#111827" },
  detailSub: { marginTop: 5, fontSize: 14, color: "#6b7280" },
  detailPrice: { fontSize: 18, fontWeight: "900", color: "#111827" },
  payBox: {
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    padding: 12,
  },
  payLine: { fontSize: 16, color: "#111827", fontWeight: "700", marginBottom: 6 },
  vendorBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: "#f1f5f9",
  },
  vendorName: { fontSize: 17, fontWeight: "800", color: "#111827" },
  vendorSub: { marginTop: 4, color: "#6b7280", fontSize: 14 },
  doneBadge: {
    backgroundColor: "#dcfce7",
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  doneBadgeText: { color: "#15803d", fontWeight: "800", fontSize: 12 },
  noteBtn: {
    borderWidth: 1,
    borderColor: "#111827",
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  noteBtnText: { color: "#111827", fontWeight: "800" },
  smallNote: { marginTop: 10, fontSize: 13, color: "#6b7280" },
  backToAdsBtn: {
    backgroundColor: "#111827",
    borderRadius: 12,
    paddingVertical: 13,
    alignItems: "center",
  },
  backToAdsBtnText: { color: "#fff", fontWeight: "800", fontSize: 15 },
  secondaryBtn: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    paddingVertical: 13,
    alignItems: "center",
    backgroundColor: "#fff",
  },
  secondaryBtnText: { color: "#374151", fontWeight: "800", fontSize: 15 },
  emptyText: { color: "#6b7280", fontSize: 15 },
  primaryBtn: {
    backgroundColor: "#2563eb",
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 12,
  },
  primaryBtnText: { color: "#fff", fontWeight: "800", fontSize: 15 },
});