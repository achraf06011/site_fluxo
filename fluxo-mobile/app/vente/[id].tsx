import React, { useEffect, useState } from "react";
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Image,
  Alert,
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
  cover_image_url?: string | null;
};

type OrderType = {
  id_order: number;
  acheteur_id: number;
  acheteur_nom: string;
  acheteur_email: string;
  telephone_client: string;
  mode_reception: string;
  ville_livraison: string | null;
  adresse_livraison: string;
  frais_livraison: number;
  statut_livraison: string;
  statut_livraison_updated_at?: string | null;
  date_commande: string;
  paiement_statut: string;
  paiement_methode: string;
  can_manage_shipping: boolean;
  options_livraison: string[];
};

export default function VenteDetailsScreen() {
  const { id } = useLocalSearchParams();

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [errorMsg, setErrorMsg] = useState("");
  const [user, setUser] = useState<any>(null);
  const [order, setOrder] = useState<OrderType | null>(null);
  const [details, setDetails] = useState<DetailType[]>([]);

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
        `${API_BASE}/vente_details_mobile.php?id=${id}&user_id=${Number(currentUser.id_user)}`
      );
      const data = await res.json();

      if (!data.ok) {
        setErrorMsg(data.message || "Erreur chargement vente");
        return;
      }

      setOrder(data.order || null);
      setDetails(data.details || []);
    } catch (error: any) {
      setErrorMsg(String(error));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (id) loadData();
  }, [id]);

  function money(x: number) {
    return `${Number(x || 0).toLocaleString("fr-FR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} DH`;
  }

  async function updateShipping(nextStatus: string) {
    if (!user || !order || saving) return;

    try {
      setSaving(true);

      const res = await fetch(`${API_BASE}/vendor_order_status_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: Number(user.id_user),
          id_order: Number(order.id_order),
          statut_livraison: nextStatus,
        }),
      });

      const data = await res.json();

      if (!data.ok) {
        Alert.alert("Erreur", data.message || "Erreur mise à jour.");
        return;
      }

      Alert.alert("Succès", "Suivi mis à jour.");
      await loadData();
    } catch (e) {
      Alert.alert("Erreur", "Erreur serveur.");
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return (
      <>
        <Stack.Screen options={{ title: "Vente" }} />
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
        <Stack.Screen options={{ title: "Vente" }} />
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
        <Stack.Screen options={{ title: "Vente" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Erreur</Text>
          <Text style={styles.errorText}>{errorMsg || "Vente introuvable"}</Text>
          <TouchableOpacity style={styles.primaryBtn} onPress={() => router.back()}>
            <Text style={styles.primaryBtnText}>Retour</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  return (
    <>
      <Stack.Screen options={{ title: `Vente #${order.id_order}` }} />

      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <View style={styles.headerBox}>
          <Text style={styles.pageTitle}>Vente #{order.id_order}</Text>
          <Text style={styles.pageSub}>Gestion vendeur de la commande</Text>
        </View>

        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Produits de cette vente</Text>

          {details.length === 0 ? (
            <Text style={styles.emptyText}>Aucun produit trouvé.</Text>
          ) : (
            details.map((d, index) => (
              <View
                key={`${d.id_annonce}-${index}`}
                style={[
                  styles.productRow,
                  index !== details.length - 1 && styles.productBorder,
                ]}
              >
                <Image
                  source={{ uri: d.cover_image_url || undefined }}
                  style={styles.productImage}
                />

                <View style={{ flex: 1 }}>
                  <Text style={styles.productTitle}>{d.titre}</Text>
                  <Text style={styles.productSub}>Quantité : {d.quantite}</Text>
                </View>

                <Text style={styles.productPrice}>{money(d.line_total)}</Text>
              </View>
            ))
          )}
        </View>

        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Infos acheteur</Text>

          <Text style={styles.infoLine}>Nom : {order.acheteur_nom}</Text>
          <Text style={styles.infoLine}>Email : {order.acheteur_email}</Text>
          <Text style={styles.infoLine}>
            Téléphone : {order.telephone_client || "—"}
          </Text>
          <Text style={styles.infoLine}>
            Mode : {order.mode_reception === "LIVRAISON" ? "Livraison" : "Main propre"}
          </Text>

          {order.mode_reception === "LIVRAISON" ? (
            <>
              <Text style={styles.infoLine}>
                Ville : {order.ville_livraison || "—"}
              </Text>
              <Text style={styles.infoBlock}>
                Adresse : {order.adresse_livraison || "—"}
              </Text>
            </>
          ) : (
            <Text style={styles.infoBlock}>
              Lieu de rencontre : {order.adresse_livraison || "Non précisé"}
            </Text>
          )}

          <View style={styles.divider} />

          <Text style={styles.infoLine}>
            Paiement : {order.paiement_statut} · {order.paiement_methode}
          </Text>
          <Text style={styles.infoLine}>
            Statut actuel : {order.statut_livraison}
          </Text>
          <Text style={styles.infoLine}>
            Dernière mise à jour :{" "}
            {order.statut_livraison_updated_at || "—"}
          </Text>

          {!order.can_manage_shipping ? (
            <View style={styles.warningBox}>
              <Text style={styles.warningText}>
                Tu pourras gérer le suivi quand le paiement sera accepté.
              </Text>
            </View>
          ) : (
            <View style={{ marginTop: 14 }}>
              <Text style={styles.sectionTitleSmall}>Statut livraison</Text>

              <View style={styles.statusWrap}>
                {order.options_livraison.map((opt) => {
                  const active = order.statut_livraison === opt;

                  return (
                    <TouchableOpacity
                      key={opt}
                      style={[styles.statusBtn, active && styles.statusBtnActive]}
                      onPress={() => updateShipping(opt)}
                      disabled={saving}
                    >
                      <Text
                        style={[
                          styles.statusBtnText,
                          active && styles.statusBtnTextActive,
                        ]}
                      >
                        {opt}
                      </Text>
                    </TouchableOpacity>
                  );
                })}
              </View>
            </View>
          )}
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
  headerBox: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
    marginBottom: 14,
  },
  pageTitle: { fontSize: 24, fontWeight: "900", color: "#111827" },
  pageSub: { marginTop: 4, color: "#6b7280", fontSize: 14 },
  card: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
    marginBottom: 14,
  },
  sectionTitle: { fontSize: 22, fontWeight: "900", color: "#111827", marginBottom: 14 },
  sectionTitleSmall: { fontSize: 18, fontWeight: "900", color: "#111827", marginBottom: 10 },
  emptyText: { color: "#6b7280", fontSize: 15 },
  productRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingVertical: 10,
  },
  productBorder: {
    borderBottomWidth: 1,
    borderBottomColor: "#f1f5f9",
  },
  productImage: {
    width: 82,
    height: 62,
    borderRadius: 12,
    backgroundColor: "#ddd",
  },
  productTitle: { fontSize: 16, fontWeight: "800", color: "#111827" },
  productSub: { marginTop: 4, fontSize: 14, color: "#6b7280" },
  productPrice: { fontSize: 16, fontWeight: "900", color: "#111827" },
  infoLine: {
    fontSize: 15,
    color: "#111827",
    marginBottom: 8,
    fontWeight: "700",
  },
  infoBlock: {
    fontSize: 15,
    color: "#111827",
    marginBottom: 8,
    fontWeight: "700",
    lineHeight: 22,
  },
  divider: {
    height: 1,
    backgroundColor: "#e5e7eb",
    marginVertical: 12,
  },
  warningBox: {
    marginTop: 14,
    backgroundColor: "#fef3c7",
    borderRadius: 12,
    padding: 12,
  },
  warningText: {
    color: "#92400e",
    fontWeight: "700",
  },
  statusWrap: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
  },
  statusBtn: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: "#fff",
  },
  statusBtnActive: {
    backgroundColor: "#111827",
    borderColor: "#111827",
  },
  statusBtnText: {
    color: "#111827",
    fontWeight: "700",
    fontSize: 13,
  },
  statusBtnTextActive: {
    color: "#fff",
  },
  primaryBtn: {
    backgroundColor: "#2563eb",
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 12,
  },
  primaryBtnText: { color: "#fff", fontWeight: "800", fontSize: 15 },
});