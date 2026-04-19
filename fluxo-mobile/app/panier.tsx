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
  TextInput,
} from "react-native";
import { Stack, router, useFocusEffect } from "expo-router";
import { getUser } from "../utils/auth";
import { Feather } from "@expo/vector-icons";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

type PanierItemType = {
  id_panier_item: number;
  id_annonce: number;
  titre: string;
  prix: number;
  stock: number;
  quantity: number;
  cover_image_url?: string | null;
};

export default function PanierScreen() {
  const [loading, setLoading] = useState(true);
  const [savingId, setSavingId] = useState<number | null>(null);
  const [errorMsg, setErrorMsg] = useState("");
  const [user, setUser] = useState<any>(null);
  const [items, setItems] = useState<PanierItemType[]>([]);
  const [total, setTotal] = useState(0);
  const [qtyMap, setQtyMap] = useState<Record<number, string>>({});

  async function loadPanier() {
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
        `${API_BASE}/panier_mobile.php?user_id=${Number(currentUser.id_user)}`
      );
      const data = await res.json();

      if (!data.ok) {
        setErrorMsg(data.message || "Erreur chargement panier");
        return;
      }

      const list = data.items || [];
      setItems(list);
      setTotal(Number(data.total || 0));

      const nextQtyMap: Record<number, string> = {};
      list.forEach((item: PanierItemType) => {
        nextQtyMap[item.id_annonce] = String(item.quantity);
      });
      setQtyMap(nextQtyMap);
    } catch (error: any) {
      setErrorMsg(String(error));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadPanier();
  }, []);

  useFocusEffect(
    React.useCallback(() => {
      loadPanier();
    }, [])
  );

  function money(x: number) {
    return `${Number(x || 0).toLocaleString("fr-FR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} DH`;
  }

  function setLocalQty(idAnnonce: number, value: string) {
    const cleaned = value.replace(/[^0-9]/g, "");
    setQtyMap((prev) => ({
      ...prev,
      [idAnnonce]: cleaned,
    }));
  }

  async function updateQty(item: PanierItemType) {
    if (!user) return;

    const raw = qtyMap[item.id_annonce] ?? String(item.quantity);
    let qty = parseInt(raw || "1", 10);

    if (isNaN(qty) || qty < 1) qty = 1;
    if (qty > item.stock) qty = item.stock;

    try {
      setSavingId(item.id_annonce);

      const res = await fetch(`${API_BASE}/panier_action_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: Number(user.id_user),
          action: "update",
          id_annonce: Number(item.id_annonce),
          qty,
        }),
      });

      const data = await res.json();

      if (!data.ok) {
        Alert.alert("Erreur", data.message || "Quantité non mise à jour.");
        return;
      }

      await loadPanier();
      Alert.alert("Succès", data.message || "Quantité mise à jour.");
    } catch (e) {
      Alert.alert("Erreur", "Erreur serveur.");
    } finally {
      setSavingId(null);
    }
  }

  async function deleteItem(item: PanierItemType) {
    if (!user) return;

    try {
      setSavingId(item.id_annonce);

      const res = await fetch(`${API_BASE}/panier_action_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: Number(user.id_user),
          action: "delete",
          id_annonce: Number(item.id_annonce),
        }),
      });

      const data = await res.json();

      if (!data.ok) {
        Alert.alert("Erreur", data.message || "Suppression impossible.");
        return;
      }

      await loadPanier();
    } catch (e) {
      Alert.alert("Erreur", "Erreur serveur.");
    } finally {
      setSavingId(null);
    }
  }

  async function clearPanier() {
    if (!user) return;

    Alert.alert("Vider le panier", "Tu veux vraiment vider le panier ?", [
      { text: "Annuler", style: "cancel" },
      {
        text: "Oui",
        style: "destructive",
        onPress: async () => {
          try {
            const res = await fetch(`${API_BASE}/panier_action_mobile.php`, {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
              },
              body: JSON.stringify({
                user_id: Number(user.id_user),
                action: "clear",
              }),
            });

            const data = await res.json();

            if (!data.ok) {
              Alert.alert("Erreur", data.message || "Impossible de vider le panier.");
              return;
            }

            await loadPanier();
          } catch (e) {
            Alert.alert("Erreur", "Erreur serveur.");
          }
        },
      },
    ]);
  }

  function goCheckout() {
    if (items.length === 0) {
      Alert.alert("Panier vide", "Ajoute au moins un produit avant de continuer.");
      return;
    }

    router.push("/checkout");
  }

  if (loading) {
    return (
      <>
        <Stack.Screen options={{ title: "Panier" }} />
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
        <Stack.Screen options={{ title: "Panier" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Connexion requise</Text>
          <Text style={styles.errorText}>
            Tu dois te connecter pour voir ton panier.
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
        <Stack.Screen options={{ title: "Panier" }} />
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
      <Stack.Screen options={{ title: "Panier" }} />

      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <View style={styles.headerRow}>
          <Text style={styles.pageTitle}>Mon panier</Text>

          <TouchableOpacity
            style={styles.outlineBtn}
            onPress={() => router.push("/(tabs)")}
          >
            <Text style={styles.outlineBtnText}>Continuer achats</Text>
          </TouchableOpacity>
        </View>

        {items.length === 0 ? (
          <View style={styles.emptyCard}>
            <Text style={styles.emptyText}>Ton panier est vide.</Text>

            <TouchableOpacity
              style={[styles.primaryBtn, { marginTop: 14 }]}
              onPress={() => router.push("/(tabs)")}
            >
              <Text style={styles.primaryBtnText}>Voir les annonces</Text>
            </TouchableOpacity>
          </View>
        ) : (
          <>
            {items.map((item) => {
              const subTotal = Number(item.prix) * Number(item.quantity);

              return (
                <View key={item.id_panier_item} style={styles.card}>
                  <View style={styles.topRow}>
                    <Image
                      source={{ uri: item.cover_image_url || undefined }}
                      style={styles.image}
                    />

                    <View style={styles.topInfo}>
                      <Text style={styles.title}>{item.titre}</Text>
                      <Text style={styles.price}>{money(item.prix)}</Text>
                      <Text style={styles.stockText}>Stock : {item.stock}</Text>
                      <Text style={styles.subTotal}>Sous-total : {money(subTotal)}</Text>
                    </View>
                  </View>

                  <View style={styles.qtyRow}>
                    <View style={styles.qtyBox}>
                      <Text style={styles.qtyLabel}>Quantité</Text>
                      <TextInput
                        value={qtyMap[item.id_annonce] ?? String(item.quantity)}
                        onChangeText={(v) => setLocalQty(item.id_annonce, v)}
                        keyboardType="numeric"
                        style={styles.qtyInput}
                        placeholder="1"
                      />
                    </View>

                    <TouchableOpacity
                      style={styles.updateBtn}
                      onPress={() => updateQty(item)}
                      disabled={savingId === item.id_annonce}
                    >
                      <Text style={styles.updateBtnText}>
                        {savingId === item.id_annonce ? "..." : "OK"}
                      </Text>
                    </TouchableOpacity>
                  </View>

                  <TouchableOpacity
                    style={styles.deleteBtn}
                    onPress={() => deleteItem(item)}
                    disabled={savingId === item.id_annonce}
                  >
                    <Feather name="trash-2" size={16} color="#dc2626" />
                    <Text style={styles.deleteBtnText}>Supprimer</Text>
                  </TouchableOpacity>
                </View>
              );
            })}

            <View style={styles.footerCard}>
              <View style={styles.totalRow}>
                <Text style={styles.totalLabel}>Total</Text>
                <Text style={styles.totalValue}>{money(total)}</Text>
              </View>

              <View style={styles.footerButtons}>
                <TouchableOpacity style={styles.clearBtn} onPress={clearPanier}>
                  <Text style={styles.clearBtnText}>Vider le panier</Text>
                </TouchableOpacity>

                <TouchableOpacity style={styles.checkoutBtn} onPress={goCheckout}>
                  <Text style={styles.checkoutBtnText}>Passer commande</Text>
                </TouchableOpacity>
              </View>
            </View>
          </>
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
    fontSize: 26,
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
    width: 92,
    height: 72,
    borderRadius: 12,
    backgroundColor: "#ddd",
  },
  topInfo: {
    flex: 1,
  },
  title: {
    fontSize: 17,
    fontWeight: "900",
    color: "#111827",
  },
  price: {
    marginTop: 6,
    color: "#2563eb",
    fontWeight: "800",
    fontSize: 17,
  },
  stockText: {
    marginTop: 4,
    color: "#6b7280",
    fontSize: 14,
  },
  subTotal: {
    marginTop: 6,
    color: "#111827",
    fontWeight: "800",
    fontSize: 15,
  },
  qtyRow: {
    flexDirection: "row",
    gap: 10,
    marginTop: 14,
    alignItems: "flex-end",
  },
  qtyBox: {
    flex: 1,
  },
  qtyLabel: {
    fontSize: 13,
    color: "#6b7280",
    marginBottom: 6,
  },
  qtyInput: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    backgroundColor: "#fff",
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 16,
    color: "#111827",
  },
  updateBtn: {
    backgroundColor: "#2563eb",
    borderRadius: 12,
    paddingHorizontal: 18,
    paddingVertical: 13,
    alignItems: "center",
    justifyContent: "center",
  },
  updateBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 15,
  },
  deleteBtn: {
    marginTop: 12,
    borderWidth: 1,
    borderColor: "#fecaca",
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
    backgroundColor: "#fff",
  },
  deleteBtnText: {
    color: "#dc2626",
    fontWeight: "800",
  },
  footerCard: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
  },
  totalRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 14,
  },
  totalLabel: {
    fontSize: 18,
    color: "#374151",
    fontWeight: "700",
  },
  totalValue: {
    fontSize: 24,
    color: "#111827",
    fontWeight: "900",
  },
  footerButtons: {
    gap: 10,
  },
  clearBtn: {
    borderWidth: 1,
    borderColor: "#fca5a5",
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    backgroundColor: "#fff",
  },
  clearBtnText: {
    color: "#dc2626",
    fontWeight: "800",
    fontSize: 15,
  },
  checkoutBtn: {
    backgroundColor: "#111827",
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
  },
  checkoutBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 15,
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