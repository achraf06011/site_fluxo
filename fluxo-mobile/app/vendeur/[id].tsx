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
import { Stack, router, useLocalSearchParams, useFocusEffect } from "expo-router";
import { getUser, getCurrentUserId } from "../../utils/auth";
import { Feather, Ionicons } from "@expo/vector-icons";
import ReactNative from "react";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

type ReviewType = {
  id_review: number;
  acheteur_nom: string;
  note: number;
  review_comment: string;
  review_date: string;
  stars: ("full" | "half" | "empty")[];
};

type SellerAnnonceType = {
  id_annonce: number;
  titre: string;
  prix: number;
  ville: string;
  stock: number;
  mode_vente: string;
  mode_label: string;
  cover_image_url?: string | null;
  is_favori: boolean;
  can_buy: boolean;
  can_chat: boolean;
};

type SellerType = {
  id_user: number;
  nom: string;
  email: string;
  date_inscription: string;
  role: string;
  statut: string;
  initiale: string;
};

type SellerStatsType = {
  total_annonces: number;
  annonces_actives: number;
  avg_note: number;
  total_reviews: number;
  stars: ("full" | "half" | "empty")[];
};

export default function VendeurScreen() {
  const { id } = useLocalSearchParams();

  const [currentUserId, setCurrentUserId] = useState<number | null>(null);
  const [vendeur, setVendeur] = useState<SellerType | null>(null);
  const [stats, setStats] = useState<SellerStatsType | null>(null);
  const [reviews, setReviews] = useState<ReviewType[]>([]);
  const [annonces, setAnnonces] = useState<SellerAnnonceType[]>([]);
  const [isOwnProfile, setIsOwnProfile] = useState(false);
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState("");

  async function loadVendeur() {
    try {
      setLoading(true);
      setErrorMsg("");

      const uid = await getCurrentUserId();
      setCurrentUserId(uid);

      const url = `${API_BASE}/vendeur_details.php?id=${id}${
        uid ? `&current_user_id=${uid}` : ""
      }`;

      const res = await fetch(url);
      const data = await res.json();

      if (!data.ok) {
        setErrorMsg(data.message || "Erreur chargement vendeur");
        return;
      }

      setVendeur(data.vendeur || null);
      setStats(data.stats || null);
      setReviews(data.reviews || []);
      setAnnonces(data.annonces || []);
      setIsOwnProfile(Boolean(data.isOwnProfile));
    } catch (error: any) {
      setErrorMsg(String(error));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (id) {
      loadVendeur();
    }
  }, [id]);

  useFocusEffect(
    React.useCallback(() => {
      if (id) loadVendeur();
    }, [id])
  );

  function starText(stars?: ("full" | "half" | "empty")[]) {
    if (!stars) return "☆☆☆☆☆";
    return stars
      .map((s) => {
        if (s === "full") return "★";
        if (s === "half") return "☆";
        return "☆";
      })
      .join("");
  }

  function formatDate(dateStr: string) {
    if (!dateStr) return "—";
    return dateStr.substring(0, 10);
  }

  function openAnnonce(item: SellerAnnonceType) {
    router.push(`/annonce/${item.id_annonce}`);
  }

  function openMessages(item: SellerAnnonceType) {
    if (!currentUserId) {
      Alert.alert("Connexion requise", "Tu dois te connecter d’abord.");
      router.push("/login");
      return;
    }

    if (!vendeur) return;

    if (currentUserId === vendeur.id_user) {
      Alert.alert("Impossible", "Tu ne peux pas t’envoyer un message à toi-même.");
      return;
    }

    router.push({
      pathname: "/messages",
      params: {
        annonceId: String(item.id_annonce),
        to: String(vendeur.id_user),
        vendeur: vendeur.nom,
        titre: item.titre,
      },
    });
  }

  function openSellerContactInfo() {
    if (!currentUserId) {
      Alert.alert("Connexion requise", "Tu dois te connecter d’abord.");
      router.push("/login");
      return;
    }

    if (!vendeur) return;

    if (isOwnProfile) {
      Alert.alert("Info", "C’est ton propre profil vendeur.");
      return;
    }

    Alert.alert(
      "Info",
      "Pour contacter ce vendeur, ouvre une annonce de ce vendeur puis clique sur Message."
    );
  }

  async function toggleFavori(item: SellerAnnonceType) {
    const user = await getUser();

    if (!user) {
      Alert.alert(
        "Connexion requise",
        "Tu dois te connecter pour gérer les favoris.",
        [
          { text: "Annuler", style: "cancel" },
          { text: "Connexion", onPress: () => router.push("/login") },
        ]
      );
      return;
    }

    try {
      const res = await fetch(`${API_BASE}/favori_toggle.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: Number(user.id_user),
          id_annonce: Number(item.id_annonce),
        }),
      });

      const data = await res.json();

      if (!data.ok) {
        Alert.alert("Erreur", data.message || "Erreur favoris.");
        return;
      }

      setAnnonces((prev) =>
        prev.map((x) =>
          Number(x.id_annonce) === Number(item.id_annonce)
            ? { ...x, is_favori: !!data.favori }
            : x
        )
      );
    } catch (e) {
      Alert.alert("Erreur", "Erreur serveur.");
    }
  }

  if (loading) {
    return (
      <>
        <Stack.Screen options={{ title: "Profil vendeur" }} />
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#2563eb" />
          <Text style={styles.loadingText}>Chargement...</Text>
        </View>
      </>
    );
  }

  if (errorMsg || !vendeur || !stats) {
    return (
      <>
        <Stack.Screen options={{ title: "Profil vendeur" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Erreur</Text>
          <Text style={styles.errorText}>{errorMsg || "Vendeur introuvable"}</Text>

          <TouchableOpacity style={styles.primaryBtnSmall} onPress={() => router.back()}>
            <Text style={styles.primaryBtnSmallText}>Retour</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  return (
    <>
      <Stack.Screen options={{ title: "Profil vendeur" }} />

      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <View style={styles.hero}>
          <View style={styles.heroTop}>
            <View style={styles.heroLeft}>
              <View style={styles.avatar}>
                <Text style={styles.avatarText}>{vendeur.initiale || "V"}</Text>
              </View>

              <View style={{ flex: 1 }}>
                <Text style={styles.sellerName}>{vendeur.nom}</Text>

                <View style={styles.metaWhiteWrap}>
                  <View style={styles.metaWhiteItem}>
                    <Feather name="credit-card" size={14} color="rgba(255,255,255,0.8)" />
                    <Text style={styles.metaWhite}>Vendeur</Text>
                  </View>

                  <View style={styles.metaWhiteItem}>
                    <Feather name="calendar" size={14} color="rgba(255,255,255,0.8)" />
                    <Text style={styles.metaWhite}>
                      Inscrit le {formatDate(vendeur.date_inscription)}
                    </Text>
                  </View>
                </View>

                <View style={styles.ratingRow}>
                  <Feather name="star" size={15} color="#fbbf24" />
                  <Text style={styles.ratingLine}>
                    <Text style={styles.stars}>{starText(stats.stars)}</Text>
                    {"  "}
                    {Number(stats.avg_note).toFixed(1)}/5 ({stats.total_reviews} avis)
                  </Text>
                </View>
              </View>
            </View>

            <View style={styles.heroButtons}>
              {!isOwnProfile ? (
                <>
                  <TouchableOpacity style={styles.lightBtn} onPress={openSellerContactInfo}>
                    <View style={styles.btnContentRow}>
                      <Feather name="message-circle" size={16} color="#111827" />
                      <Text style={styles.lightBtnText}>Contacter</Text>
                    </View>
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={styles.outlineLightBtn}
                    onPress={() => router.back()}
                  >
                    <View style={styles.btnContentRow}>
                      <Feather name="arrow-left" size={16} color="#fff" />
                      <Text style={styles.outlineLightBtnText}>Retour</Text>
                    </View>
                  </TouchableOpacity>
                </>
              ) : (
                <TouchableOpacity
                  style={styles.outlineLightBtn}
                  onPress={() => router.back()}
                >
                  <View style={styles.btnContentRow}>
                    <Feather name="arrow-left" size={16} color="#fff" />
                    <Text style={styles.outlineLightBtnText}>Retour</Text>
                  </View>
                </TouchableOpacity>
              )}
            </View>
          </View>
        </View>

        <View style={styles.statsRow}>
          <View style={styles.statsCard}>
            <Text style={styles.statsLabel}>Annonces publiées</Text>
            <Text style={styles.statsValue}>{stats.total_annonces}</Text>
          </View>

          <View style={styles.statsCard}>
            <Text style={styles.statsLabel}>Annonces actives</Text>
            <Text style={styles.statsValue}>{stats.annonces_actives}</Text>
          </View>

          <View style={styles.statsCard}>
            <Text style={styles.statsLabel}>Avis reçus</Text>
            <Text style={styles.statsValue}>{stats.total_reviews}</Text>
          </View>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Avis des acheteurs</Text>
          <Text style={styles.sectionSub}>{stats.total_reviews} avis reçu(s)</Text>

          {reviews.length > 0 ? (
            reviews.map((r) => (
              <View key={r.id_review} style={styles.reviewCard}>
                <View style={styles.reviewTop}>
                  <View style={styles.reviewAvatar}>
                    <Text style={styles.reviewAvatarText}>
                      {r.acheteur_nom?.charAt(0)?.toUpperCase() || "U"}
                    </Text>
                  </View>

                  <View style={{ flex: 1 }}>
                    <Text style={styles.reviewName}>{r.acheteur_nom}</Text>
                    <Text style={styles.reviewStars}>
                      {starText(r.stars)} {Number(r.note).toFixed(1)}/5
                    </Text>
                  </View>

                  <Text style={styles.reviewDate}>{formatDate(r.review_date)}</Text>
                </View>

                <Text style={styles.reviewComment}>
                  {r.review_comment?.trim() !== ""
                    ? r.review_comment
                    : "Aucun commentaire ajouté."}
                </Text>
              </View>
            ))
          ) : (
            <View style={styles.emptyCard}>
              <Text style={styles.emptyCardText}>
                Ce vendeur n’a pas encore reçu d’avis.
              </Text>
            </View>
          )}
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Annonces du vendeur</Text>
          <Text style={styles.sectionSub}>{annonces.length} annonce(s) active(s)</Text>

          {annonces.length > 0 ? (
            annonces.map((item) => (
              <TouchableOpacity
                key={item.id_annonce}
                style={styles.annonceCard}
                activeOpacity={0.9}
                onPress={() => openAnnonce(item)}
              >
                <Image
                  source={{ uri: item.cover_image_url || undefined }}
                  style={styles.annonceImage}
                  resizeMode="cover"
                />

                <View style={styles.annonceBody}>
                  <View style={styles.annonceTitleRow}>
                    <Text style={styles.annonceTitle} numberOfLines={2}>
                      {item.titre}
                    </Text>
                    <Text style={styles.priceBadge}>
                      {Number(item.prix).toFixed(2)} DH
                    </Text>
                  </View>

                  <View style={styles.metaInlineRow}>
                    <View style={styles.metaInlineItem}>
                      <Feather name="map-pin" size={15} color="#6b7280" />
                      <Text style={styles.metaInlineText}>
                        {item.ville || "Ville inconnue"}
                      </Text>
                    </View>

                    <View style={styles.metaInlineItem}>
                      <Feather name="box" size={15} color="#6b7280" />
                      <Text style={styles.metaInlineText}>Stock: {item.stock}</Text>
                    </View>
                  </View>

                  <View style={styles.annonceButtons}>
                    <TouchableOpacity
                      style={styles.btnVoir}
                      onPress={() => openAnnonce(item)}
                    >
                      <Text style={styles.btnVoirText}>Voir</Text>
                    </TouchableOpacity>

                    {item.can_buy ? (
                      <TouchableOpacity
                        style={styles.btnAcheter}
                        onPress={() => openAnnonce(item)}
                      >
                        <Text style={styles.btnAcheterText}>Acheter</Text>
                      </TouchableOpacity>
                    ) : null}

                    {item.can_chat ? (
                      <TouchableOpacity
                        style={styles.btnMessage}
                        onPress={() => openMessages(item)}
                      >
                        <Text style={styles.btnMessageText}>Message</Text>
                      </TouchableOpacity>
                    ) : null}

                    <TouchableOpacity
                      style={styles.btnHeart}
                      onPress={() => toggleFavori(item)}
                    >
                      <Ionicons
                        name={item.is_favori ? "heart" : "heart-outline"}
                        size={18}
                        color={item.is_favori ? "#dc2626" : "#9ca3af"}
                      />
                    </TouchableOpacity>
                  </View>

                  <Text style={styles.modeText}>Mode: {item.mode_label}</Text>
                </View>
              </TouchableOpacity>
            ))
          ) : (
            <View style={styles.emptyCard}>
              <Text style={styles.emptyCardText}>
                Ce vendeur n’a aucune annonce active.
              </Text>
            </View>
          )}
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
    justifyContent: "center",
    alignItems: "center",
    backgroundColor: "#fff",
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

  hero: {
    borderRadius: 22,
    backgroundColor: "#111827",
    padding: 18,
    marginBottom: 14,
  },
  heroTop: {
    gap: 16,
  },
  heroLeft: {
    flexDirection: "row",
    gap: 14,
    alignItems: "center",
  },
  avatar: {
    width: 72,
    height: 72,
    borderRadius: 999,
    backgroundColor: "rgba(255,255,255,0.12)",
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.14)",
  },
  avatarText: {
    color: "#fff",
    fontSize: 30,
    fontWeight: "800",
  },
  sellerName: {
    color: "#fff",
    fontSize: 28,
    fontWeight: "900",
    marginBottom: 6,
  },

  metaWhiteWrap: {
    gap: 6,
    marginBottom: 10,
  },
  metaWhiteItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  metaWhite: {
    color: "rgba(255,255,255,0.8)",
    fontSize: 14,
  },

  ratingRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
  },
  ratingLine: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "700",
  },
  stars: {
    color: "#fbbf24",
  },

  heroButtons: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
    marginTop: 12,
  },
  btnContentRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  lightBtn: {
    backgroundColor: "#fff",
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 12,
  },
  lightBtnText: {
    color: "#111827",
    fontWeight: "800",
    fontSize: 15,
  },
  outlineLightBtn: {
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.4)",
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 12,
  },
  outlineLightBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 15,
  },

  statsRow: {
    gap: 12,
    marginBottom: 18,
  },
  statsCard: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 16,
  },
  statsLabel: {
    color: "#6b7280",
    fontSize: 14,
    marginBottom: 8,
  },
  statsValue: {
    fontSize: 28,
    fontWeight: "900",
    color: "#111827",
  },

  section: {
    marginBottom: 18,
  },
  sectionTitle: {
    fontSize: 30,
    fontWeight: "900",
    color: "#111827",
    marginBottom: 4,
  },
  sectionSub: {
    fontSize: 14,
    color: "#6b7280",
    marginBottom: 12,
  },

  reviewCard: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 14,
    marginBottom: 12,
  },
  reviewTop: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 12,
    marginBottom: 10,
  },
  reviewAvatar: {
    width: 46,
    height: 46,
    borderRadius: 999,
    backgroundColor: "#f3f4f6",
    alignItems: "center",
    justifyContent: "center",
  },
  reviewAvatarText: {
    fontSize: 18,
    fontWeight: "800",
    color: "#111827",
  },
  reviewName: {
    fontSize: 16,
    fontWeight: "800",
    color: "#111827",
  },
  reviewStars: {
    fontSize: 14,
    color: "#6b7280",
    marginTop: 4,
  },
  reviewDate: {
    fontSize: 13,
    color: "#6b7280",
  },
  reviewComment: {
    fontSize: 15,
    color: "#374151",
    lineHeight: 22,
  },

  emptyCard: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 18,
  },
  emptyCardText: {
    color: "#6b7280",
    fontSize: 15,
  },

  annonceCard: {
    backgroundColor: "#fff",
    borderRadius: 18,
    overflow: "hidden",
    marginBottom: 14,
  },
  annonceImage: {
    width: "100%",
    height: 220,
    backgroundColor: "#ddd",
  },
  annonceBody: {
    padding: 14,
  },
  annonceTitleRow: {
    gap: 8,
    marginBottom: 8,
  },
  annonceTitle: {
    fontSize: 22,
    fontWeight: "900",
    color: "#111827",
  },
  priceBadge: {
    alignSelf: "flex-start",
    backgroundColor: "#111827",
    color: "#fff",
    fontSize: 14,
    fontWeight: "800",
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 999,
  },

  metaInlineRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    alignItems: "center",
    gap: 12,
    marginBottom: 12,
  },
  metaInlineItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
  },
  metaInlineText: {
    color: "#6b7280",
    fontSize: 14,
  },

  annonceButtons: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
    marginBottom: 10,
    alignItems: "center",
  },
  btnVoir: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 12,
    backgroundColor: "#fff",
  },
  btnVoirText: {
    color: "#374151",
    fontWeight: "800",
  },
  btnAcheter: {
    backgroundColor: "#2563eb",
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 12,
  },
  btnAcheterText: {
    color: "#fff",
    fontWeight: "800",
  },
  btnMessage: {
    borderWidth: 1,
    borderColor: "#2563eb",
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 12,
    backgroundColor: "#fff",
  },
  btnMessageText: {
    color: "#2563eb",
    fontWeight: "800",
  },
  btnHeart: {
    width: 42,
    height: 42,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "#fca5a5",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#fff",
  },
  modeText: {
    color: "#6b7280",
    fontSize: 14,
    fontWeight: "600",
  },

  primaryBtnSmall: {
    backgroundColor: "#2563eb",
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 12,
  },
  primaryBtnSmallText: {
    color: "#fff",
    fontWeight: "800",
  },
});