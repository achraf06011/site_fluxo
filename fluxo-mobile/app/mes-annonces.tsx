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
import { Stack, router, useFocusEffect } from "expo-router";
import { getUser } from "../utils/auth";
import { Feather } from "@expo/vector-icons";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

type MesAnnonceType = {
  id_annonce: number;
  titre: string;
  prix: number;
  ancien_prix: number | null;
  stock: number;
  statut: string;
  date_publication: string;
  nb_vues: number;
  cover_image_url?: string | null;
};

export default function MesAnnoncesScreen() {
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState("");
  const [user, setUser] = useState<any>(null);
  const [items, setItems] = useState<MesAnnonceType[]>([]);
  const [busyId, setBusyId] = useState<number | null>(null);

  async function loadMesAnnonces() {
    try {
      setLoading(true);
      setErrorMsg("");

      const currentUser = await getUser();
      setUser(currentUser);

      if (!currentUser?.id_user) {
        setErrorMsg("Connexion requise.");
        return;
      }

      const res = await fetch(
        `${API_BASE}/mes_annonces_mobile.php?user_id=${Number(currentUser.id_user)}`,
        {
          headers: {
            Accept: "application/json",
          },
        }
      );

      const rawText = await res.text();

      if (!rawText || rawText.trim() === "") {
        setErrorMsg("Réponse vide du serveur.");
        return;
      }

      let data: any = null;

      try {
        data = JSON.parse(rawText);
      } catch (e) {
        setErrorMsg(`Réponse non JSON: ${rawText.substring(0, 180)}`);
        return;
      }

      if (!data.ok) {
        setErrorMsg(data.message || "Erreur chargement mes annonces");
        return;
      }

      setItems(Array.isArray(data.annonces) ? data.annonces : []);
    } catch (e: any) {
      setErrorMsg(String(e));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadMesAnnonces();
  }, []);

  useFocusEffect(
    React.useCallback(() => {
      loadMesAnnonces();
    }, [])
  );

  function money(x: number) {
    return `${Number(x || 0).toLocaleString("fr-FR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} DH`;
  }

  function hasPromo(item: MesAnnonceType) {
    return (
      item.ancien_prix !== null &&
      Number(item.ancien_prix) > Number(item.prix)
    );
  }

  function statutText(statut: string) {
    return statut?.trim() ? statut : "DESACTIVEE";
  }

  function statusStyle(statut: string) {
    const s = statutText(statut);

    if (s === "ACTIVE") return styles.statusActive;
    if (s === "EN_ATTENTE_VALIDATION") return styles.statusPending;
    if (s === "REFUSEE") return styles.statusRefused;
    if (s === "VENDUE") return styles.statusSold;
    if (s === "DESACTIVEE") return styles.statusDisabled;

    return styles.statusDefault;
  }

  async function deleteAnnonce(item: MesAnnonceType) {
    if (!user) return;

    Alert.alert(
      "Supprimer",
      "Supprimer cette annonce ? Si elle a déjà été commandée, elle sera désactivée.",
      [
        { text: "Annuler", style: "cancel" },
        {
          text: "Oui",
          style: "destructive",
          onPress: async () => {
            try {
              setBusyId(item.id_annonce);

              const res = await fetch(`${API_BASE}/annonce_delete_mobile.php`, {
                method: "POST",
                headers: {
                  "Content-Type": "application/json",
                  Accept: "application/json",
                },
                body: JSON.stringify({
                  user_id: Number(user.id_user),
                  id_annonce: Number(item.id_annonce),
                }),
              });

              const rawText = await res.text();

              if (!rawText || rawText.trim() === "") {
                Alert.alert("Erreur", "Réponse vide du serveur.");
                return;
              }

              let data: any = null;

              try {
                data = JSON.parse(rawText);
              } catch (e) {
                Alert.alert("Erreur", `Réponse non JSON: ${rawText.substring(0, 180)}`);
                return;
              }

              if (!data.ok) {
                Alert.alert("Erreur", data.message || "Suppression impossible.");
                return;
              }

              Alert.alert("Succès", data.message || "Annonce supprimée.");
              await loadMesAnnonces();
            } catch (e) {
              Alert.alert("Erreur", "Erreur serveur.");
            } finally {
              setBusyId(null);
            }
          },
        },
      ]
    );
  }

  if (loading) {
    return (
      <>
        <Stack.Screen options={{ title: "Mes annonces" }} />
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
        <Stack.Screen options={{ title: "Mes annonces" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Connexion requise</Text>
          <Text style={styles.errorText}>
            Tu dois te connecter pour voir tes annonces.
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
        <Stack.Screen options={{ title: "Mes annonces" }} />
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
      <Stack.Screen options={{ title: "Mes annonces" }} />

      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <View style={styles.headerRow}>
          <Text style={styles.pageTitle}>Mes annonces</Text>

          <TouchableOpacity
            style={styles.outlineBtn}
            onPress={() => router.push("/mon-compte")}
          >
            <Text style={styles.outlineBtnText}>Retour profil</Text>
          </TouchableOpacity>
        </View>

        {items.length === 0 ? (
          <View style={styles.emptyCard}>
            <Text style={styles.emptyText}>Aucune annonce.</Text>

            <TouchableOpacity
              style={[styles.primaryBtn, { marginTop: 14 }]}
              onPress={() => router.push("/publier")}
            >
              <Text style={styles.primaryBtnText}>Publier une annonce</Text>
            </TouchableOpacity>
          </View>
        ) : (
          items.map((item) => (
            <View key={item.id_annonce} style={styles.card}>
              <View style={styles.topRow}>
                {item.cover_image_url ? (
                  <Image source={{ uri: item.cover_image_url }} style={styles.image} />
                ) : (
                  <View style={[styles.image, styles.noImage]}>
                    <Text style={styles.noImageText}>Aucune image</Text>
                  </View>
                )}

                <View style={styles.infoBox}>
                  <Text style={styles.idText}>#{item.id_annonce}</Text>
                  <Text style={styles.title}>{item.titre}</Text>

                  {hasPromo(item) ? (
                    <>
                      <Text style={styles.oldPrice}>
                        {money(Number(item.ancien_prix || 0))}
                      </Text>
                      <Text style={styles.pricePromo}>{money(item.prix)}</Text>
                    </>
                  ) : (
                    <Text style={styles.price}>{money(item.prix)}</Text>
                  )}

                  <View style={styles.metaRow}>
                    <Text style={styles.meta}>Stock: {item.stock}</Text>
                    <Text style={styles.meta}>Vues: {item.nb_vues}</Text>
                  </View>

                  <View style={[styles.statusBadge, statusStyle(item.statut)]}>
                    <Text style={styles.statusText}>{statutText(item.statut)}</Text>
                  </View>
                </View>
              </View>

              <View style={styles.actionsRow}>
                <TouchableOpacity
                  style={styles.btnView}
                  onPress={() => router.push(`/annonce/${item.id_annonce}`)}
                >
                  <Feather name="eye" size={16} color="#111827" />
                  <Text style={styles.btnViewText}>Voir</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  style={styles.btnEdit}
                  onPress={() => router.push(`/annonce-edit/${item.id_annonce}`)}
                >
                  <Feather name="edit-2" size={16} color="#fff" />
                  <Text style={styles.btnEditText}>Modifier</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  style={styles.btnDelete}
                  onPress={() => deleteAnnonce(item)}
                  disabled={busyId === item.id_annonce}
                >
                  <Feather name="trash-2" size={16} color="#dc2626" />
                  <Text style={styles.btnDeleteText}>
                    {busyId === item.id_annonce ? "..." : "Supprimer"}
                  </Text>
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
    fontSize: 28,
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
    width: 96,
    height: 78,
    borderRadius: 12,
    backgroundColor: "#ddd",
  },
  noImage: {
    justifyContent: "center",
    alignItems: "center",
    padding: 8,
  },
  noImageText: {
    color: "#6b7280",
    fontSize: 12,
    textAlign: "center",
  },
  infoBox: {
    flex: 1,
  },
  idText: {
    color: "#6b7280",
    fontSize: 13,
    marginBottom: 4,
  },
  title: {
    fontSize: 17,
    fontWeight: "900",
    color: "#111827",
  },
  price: {
    marginTop: 6,
    color: "#111827",
    fontWeight: "800",
    fontSize: 16,
  },
  oldPrice: {
    marginTop: 6,
    color: "#9ca3af",
    textDecorationLine: "line-through",
    fontSize: 13,
  },
  pricePromo: {
    color: "#dc2626",
    fontWeight: "900",
    fontSize: 16,
    marginTop: 2,
  },
  metaRow: {
    flexDirection: "row",
    gap: 12,
    marginTop: 8,
    flexWrap: "wrap",
  },
  meta: {
    color: "#6b7280",
    fontSize: 14,
  },
  statusBadge: {
    alignSelf: "flex-start",
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
    marginTop: 10,
  },
  statusText: {
    color: "#111827",
    fontWeight: "800",
    fontSize: 12,
  },
  statusActive: {
    backgroundColor: "#dcfce7",
  },
  statusPending: {
    backgroundColor: "#fef3c7",
  },
  statusRefused: {
    backgroundColor: "#fee2e2",
  },
  statusSold: {
    backgroundColor: "#dbeafe",
  },
  statusDisabled: {
    backgroundColor: "#e5e7eb",
  },
  statusDefault: {
    backgroundColor: "#e5e7eb",
  },
  actionsRow: {
    flexDirection: "row",
    gap: 10,
    marginTop: 14,
    flexWrap: "wrap",
  },
  btnView: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 16,
    backgroundColor: "#fff",
  },
  btnViewText: {
    color: "#111827",
    fontWeight: "800",
  },
  btnEdit: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    backgroundColor: "#111827",
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 16,
  },
  btnEditText: {
    color: "#fff",
    fontWeight: "800",
  },
  btnDelete: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    borderColor: "#fecaca",
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 16,
    backgroundColor: "#fff",
  },
  btnDeleteText: {
    color: "#dc2626",
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