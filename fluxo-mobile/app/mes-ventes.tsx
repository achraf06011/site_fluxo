import React, { useEffect, useState } from "react";
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Image,
} from "react-native";
import { Stack, router, useFocusEffect } from "expo-router";
import { getUser } from "../utils/auth";
import { Feather } from "@expo/vector-icons";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

type VenteType = {
  id_order: number;
  date_commande: string;
  statut: string;
  mode_reception: string;
  ville_livraison: string | null;
  statut_livraison: string;
  statut_livraison_updated_at?: string | null;
  seller_seen: number;
  acheteur_nom: string;
  acheteur_email: string;
  paiement_statut: string;
  paiement_methode: string;
  vendeur_total: number;
  nb_articles: number;
  titre: string;
  id_annonce: number;
  cover_image_url?: string | null;
  shipping_text: string;
};

export default function MesVentesScreen() {
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState("");
  const [user, setUser] = useState<any>(null);
  const [rows, setRows] = useState<VenteType[]>([]);

  async function loadRows() {
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
        `${API_BASE}/mes_ventes_mobile.php?user_id=${Number(currentUser.id_user)}`,
      );
      const data = await res.json();

      if (!data.ok) {
        setErrorMsg(data.message || "Erreur chargement ventes");
        return;
      }

      setRows(data.orders || []);
    } catch (error: any) {
      setErrorMsg(String(error));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadRows();
  }, []);

  useFocusEffect(
    React.useCallback(() => {
      loadRows();
    }, []),
  );

  function money(x: number) {
    return `${Number(x || 0).toLocaleString("fr-FR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} DH`;
  }

  if (loading) {
    return (
      <>
        <Stack.Screen options={{ title: "Mes ventes" }} />
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
        <Stack.Screen options={{ title: "Mes ventes" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Connexion requise</Text>
          <Text style={styles.errorText}>
            Tu dois te connecter pour voir tes ventes.
          </Text>

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

  if (errorMsg) {
    return (
      <>
        <Stack.Screen options={{ title: "Mes ventes" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Erreur</Text>
          <Text style={styles.errorText}>{errorMsg}</Text>

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
      <Stack.Screen options={{ title: "Mes ventes" }} />

      <ScrollView
        style={styles.container}
        contentContainerStyle={styles.content}
      >
        <View style={styles.headerBox}>
          <Text style={styles.pageTitle}>Mes ventes</Text>
          <Text style={styles.pageSub}>
            Commandes où tes annonces ont été achetées.
          </Text>
        </View>

        {rows.length === 0 ? (
          <View style={styles.emptyCard}>
            <Text style={styles.emptyText}>Aucune vente pour le moment.</Text>
          </View>
        ) : (
          rows.map((item) => (
            <View key={item.id_order} style={styles.card}>
              <View style={styles.topRow}>
                <Image
                  source={{ uri: item.cover_image_url || undefined }}
                  style={styles.image}
                />

                <View style={styles.topInfo}>
                  <Text style={styles.title}>
                    {item.titre || "Produits de la vente"}
                  </Text>
                  <Text style={styles.subText}>
                    {item.nb_articles} article{item.nb_articles > 1 ? "s" : ""}
                  </Text>

                  <Text style={styles.buyerName}>{item.acheteur_nom}</Text>
                  <Text style={styles.buyerEmail}>{item.acheteur_email}</Text>
                </View>
              </View>

              <View style={styles.infoGrid}>
                <View style={styles.infoBox}>
                  <Text style={styles.infoLabel}>Commande</Text>
                  <Text style={styles.infoValue}>#{item.id_order}</Text>
                </View>

                <View style={styles.infoBox}>
                  <Text style={styles.infoLabel}>Date</Text>
                  <Text style={styles.infoValue}>
                    {String(item.date_commande).substring(0, 10)}
                  </Text>
                </View>

                <View style={styles.infoBox}>
                  <Text style={styles.infoLabel}>Statut</Text>
                  <Text style={styles.infoValue}>{item.statut}</Text>
                </View>

                <View style={styles.infoBox}>
                  <Text style={styles.infoLabel}>Paiement</Text>
                  <Text style={styles.infoValue}>
                    {item.paiement_statut} · {item.paiement_methode}
                  </Text>
                </View>

                <View style={styles.infoBox}>
                  <Text style={styles.infoLabel}>Livraison</Text>
                  <Text style={styles.infoValue}>{item.shipping_text}</Text>
                </View>

                <View style={styles.infoBox}>
                  <Text style={styles.infoLabel}>Ton total</Text>
                  <Text style={styles.infoValue}>
                    {money(item.vendeur_total)}
                  </Text>
                </View>
              </View>

              <View style={styles.buttons}>
                <TouchableOpacity
                  style={styles.primarySmallBtn}
                  onPress={() => router.push(`/vente/${item.id_order}`)}
                >
                  <Text style={styles.primarySmallBtnText}>Voir</Text>
                </TouchableOpacity>
              </View>
            </View>
          ))
        )}
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
  headerBox: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
    marginBottom: 14,
  },
  pageTitle: { fontSize: 24, fontWeight: "900", color: "#111827" },
  pageSub: { marginTop: 4, color: "#6b7280", fontSize: 14 },
  emptyCard: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 18,
  },
  emptyText: { color: "#6b7280", fontSize: 15 },
  card: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 14,
    marginBottom: 14,
  },
  topRow: {
    flexDirection: "row",
    gap: 12,
  },
  image: {
    width: 90,
    height: 68,
    borderRadius: 12,
    backgroundColor: "#ddd",
  },
  topInfo: { flex: 1 },
  title: { fontSize: 18, fontWeight: "900", color: "#111827" },
  subText: { marginTop: 4, color: "#6b7280", fontSize: 14 },
  buyerName: {
    marginTop: 6,
    fontSize: 16,
    fontWeight: "800",
    color: "#111827",
  },
  buyerEmail: { marginTop: 2, color: "#6b7280", fontSize: 13 },
  infoGrid: { marginTop: 14, gap: 10 },
  infoBox: {
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    padding: 12,
  },
  infoLabel: { fontSize: 13, color: "#6b7280", marginBottom: 4 },
  infoValue: { fontSize: 15, color: "#111827", fontWeight: "800" },
  buttons: { marginTop: 14 },
  primarySmallBtn: {
    backgroundColor: "#111827",
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: "center",
  },
  primarySmallBtnText: { color: "#fff", fontWeight: "800" },
  primaryBtn: {
    backgroundColor: "#2563eb",
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 12,
  },
  primaryBtnText: { color: "#fff", fontWeight: "800", fontSize: 15 },
});
