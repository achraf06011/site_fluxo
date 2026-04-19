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

type OrderType = {
  id_order: number;
  date_commande: string;
  statut: string;
  total: number;
  paiement_statut: string;
  methode: string;
  statut_livraison: string;
  shipping_text: string;
  statut_livraison_updated_at?: string | null;
  buyer_seen: number;
  titre: string;
  cover_image_url?: string | null;
  count_items: number;
  id_vendeur: number;
  vendeur_nom: string;
};

export default function MesCommandesScreen() {
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState("");
  const [user, setUser] = useState<any>(null);
  const [orders, setOrders] = useState<OrderType[]>([]);

  async function loadOrders() {
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
        `${API_BASE}/mes_commandes_mobile.php?user_id=${Number(currentUser.id_user)}`
      );
      const data = await res.json();

      if (!data.ok) {
        setErrorMsg(data.message || "Erreur chargement commandes");
        return;
      }

      setOrders(data.orders || []);
    } catch (error: any) {
      setErrorMsg(String(error));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadOrders();
  }, []);

  useFocusEffect(
    React.useCallback(() => {
      loadOrders();
    }, [])
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
        <Stack.Screen options={{ title: "Mes commandes" }} />
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
        <Stack.Screen options={{ title: "Mes commandes" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Connexion requise</Text>
          <Text style={styles.errorText}>
            Tu dois te connecter pour voir tes commandes.
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
        <Stack.Screen options={{ title: "Mes commandes" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Erreur</Text>
          <Text style={styles.errorText}>{errorMsg}</Text>

          <TouchableOpacity style={styles.primaryBtn} onPress={() => router.back()}>
            <Text style={styles.primaryBtnText}>Retour</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  return (
    <>
      <Stack.Screen options={{ title: "Mes commandes" }} />

      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <View style={styles.headerRow}>
          <Text style={styles.pageTitle}>Mes commandes</Text>

          <TouchableOpacity
            style={styles.outlineBtn}
            onPress={() => router.push("/(tabs)")}
          >
            <Text style={styles.outlineBtnText}>Retour annonces</Text>
          </TouchableOpacity>
        </View>

        {orders.length === 0 ? (
          <View style={styles.emptyCard}>
            <Text style={styles.emptyText}>Aucune commande pour le moment.</Text>
            <TouchableOpacity
              style={[styles.primaryBtn, { marginTop: 14 }]}
              onPress={() => router.push("/(tabs)")}
            >
              <Text style={styles.primaryBtnText}>Voir les annonces</Text>
            </TouchableOpacity>
          </View>
        ) : (
          orders.map((item) => (
            <View key={item.id_order} style={styles.card}>
              <View style={styles.topRow}>
                <Image
                  source={{ uri: item.cover_image_url || undefined }}
                  style={styles.image}
                />

                <View style={styles.topInfo}>
                  <Text style={styles.title}>{item.titre || "Produits de la commande"}</Text>
                  <Text style={styles.subText}>
                    {item.count_items > 1
                      ? `+ ${item.count_items - 1} autre(s) article(s)`
                      : item.count_items === 1
                      ? "1 article"
                      : "—"}
                  </Text>

                  <TouchableOpacity
                    onPress={() =>
                      item.id_vendeur > 0
                        ? router.push(`/vendeur/${item.id_vendeur}`)
                        : null
                    }
                  >
                    <Text style={styles.sellerText}>
                      Vendeur : {item.vendeur_nom || `Vendeur #${item.id_vendeur}`}
                    </Text>
                  </TouchableOpacity>

                  <View style={styles.shipRow}>
                    <View style={styles.shipBadge}>
                      <Text style={styles.shipBadgeText}>{item.shipping_text}</Text>
                    </View>

                    {item.statut_livraison_updated_at ? (
                      <Text style={styles.shipDate}>
                        {String(item.statut_livraison_updated_at).substring(0, 16)}
                      </Text>
                    ) : null}
                  </View>
                </View>
              </View>

              <View style={styles.gridInfo}>
                <View style={styles.infoBox}>
                  <Text style={styles.infoLabel}>ID</Text>
                  <Text style={styles.infoValue}>#{item.id_order}</Text>
                </View>

                <View style={styles.infoBox}>
                  <Text style={styles.infoLabel}>Date</Text>
                  <Text style={styles.infoValue}>{String(item.date_commande).substring(0, 10)}</Text>
                </View>

                <View style={styles.infoBox}>
                  <Text style={styles.infoLabel}>Total</Text>
                  <Text style={styles.infoValue}>{money(item.total)}</Text>
                </View>

                <View style={styles.infoBox}>
                  <Text style={styles.infoLabel}>Paiement</Text>
                  <Text style={styles.infoValue}>
                    {item.paiement_statut} {item.methode ? `· ${item.methode}` : ""}
                  </Text>
                </View>
              </View>

              <View style={styles.buttons}>
                <TouchableOpacity
                  style={styles.primarySmallBtn}
                  onPress={() => router.push(`/commande/${item.id_order}`)}
                >
                  <Feather name="eye" size={16} color="#fff" />
                  <Text style={styles.primarySmallBtnText}>Voir</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  style={styles.secondarySmallBtn}
                  onPress={() => router.push(`/suivi-commande/${item.id_order}`)}
                >
                  <Feather name="truck" size={16} color="#111827" />
                  <Text style={styles.secondarySmallBtnText}>Suivre</Text>
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
  headerRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    gap: 10,
    marginBottom: 14,
  },
  pageTitle: {
    fontSize: 24,
    fontWeight: "900",
    color: "#111827",
  },
  outlineBtn: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
    backgroundColor: "#fff",
  },
  outlineBtnText: {
    color: "#374151",
    fontWeight: "700",
  },
  emptyCard: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 18,
  },
  emptyText: {
    color: "#6b7280",
    fontSize: 15,
  },
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
    width: 86,
    height: 64,
    borderRadius: 12,
    backgroundColor: "#ddd",
  },
  topInfo: {
    flex: 1,
  },
  title: {
    fontSize: 18,
    fontWeight: "900",
    color: "#111827",
  },
  subText: {
    marginTop: 4,
    color: "#6b7280",
    fontSize: 14,
  },
  sellerText: {
    marginTop: 5,
    color: "#2563eb",
    fontSize: 14,
    fontWeight: "700",
  },
  shipRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    flexWrap: "wrap",
    marginTop: 8,
  },
  shipBadge: {
    backgroundColor: "#dbeafe",
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
  },
  shipBadgeText: {
    color: "#1d4ed8",
    fontWeight: "700",
    fontSize: 12,
  },
  shipDate: {
    color: "#6b7280",
    fontSize: 12,
  },
  gridInfo: {
    marginTop: 14,
    gap: 10,
  },
  infoBox: {
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    padding: 12,
  },
  infoLabel: {
    fontSize: 13,
    color: "#6b7280",
    marginBottom: 4,
  },
  infoValue: {
    fontSize: 15,
    color: "#111827",
    fontWeight: "800",
  },
  buttons: {
    flexDirection: "row",
    gap: 10,
    marginTop: 14,
  },
  primarySmallBtn: {
    flex: 1,
    backgroundColor: "#111827",
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
  },
  primarySmallBtnText: {
    color: "#fff",
    fontWeight: "800",
  },
  secondarySmallBtn: {
    flex: 1,
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
    backgroundColor: "#fff",
  },
  secondarySmallBtnText: {
    color: "#111827",
    fontWeight: "800",
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