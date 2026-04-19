import React, { useEffect, useState } from "react";
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  Image,
  TouchableOpacity,
  ActivityIndicator,
  Dimensions,
  Linking,
  Alert,
  Share,
} from "react-native";
import { useLocalSearchParams, router, Stack, useFocusEffect } from "expo-router";
import { getUser } from "../../utils/auth";
import { Feather, Ionicons } from "@expo/vector-icons";

const { width } = Dimensions.get("window");
const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";
const WEB_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers";

type SimilarItem = {
  id_annonce: number;
  titre: string;
  prix: number;
  ville: string;
  image_url?: string | null;
};

type AnnonceType = {
  id_annonce: number;
  id_vendeur: number;
  titre: string;
  description: string;
  prix: number;
  ancien_prix: number | null;
  ville: string;
  stock: number;
  mode_vente: string;
  statut: string;
  categorie: string;
  marque: string;
  vendeur_nom: string;
  vendeur_email: string;
  cover_image_url?: string | null;
  gallery: string[];
  latitude: number | null;
  longitude: number | null;
  is_favori?: boolean;
  can_add_to_cart?: boolean;
  can_buy_now?: boolean;
  can_contact?: boolean;
};

export default function AnnonceDetailsScreen() {
  const { id } = useLocalSearchParams();

  const [annonce, setAnnonce] = useState<AnnonceType | null>(null);
  const [similarItems, setSimilarItems] = useState<SimilarItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState("");
  const [busyAction, setBusyAction] = useState(false);

  async function loadAnnonce() {
    try {
      setLoading(true);
      setErrorMsg("");

      const user = await getUser();
      const userId = user?.id_user ? Number(user.id_user) : 0;

      const url =
        `${API_BASE}/annonce_details.php?id=${id}` +
        (userId > 0 ? `&current_user_id=${userId}` : "");

      const res = await fetch(url);
      const data = await res.json();

      if (!data.ok) {
        setErrorMsg(data.message || "Erreur lors du chargement");
        return;
      }

      setAnnonce(data.annonce || null);
      setSimilarItems(data.similarItems || []);
    } catch (error: any) {
      setErrorMsg(String(error));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (id) {
      loadAnnonce();
    }
  }, [id]);

  useFocusEffect(
    React.useCallback(() => {
      if (id) loadAnnonce();
    }, [id])
  );

  function modeLabel(mode: string) {
    if (mode === "PAIEMENT_DIRECT") return "PAIEMENT DIRECT";
    if (mode === "POSSIBILITE_CONTACTE") return "CONTACTER LE VENDEUR";
    if (mode === "LES_DEUX") return "PAIEMENT DIRECT OU CONTACTER VENDEUR";
    return mode;
  }

  function hasPromo() {
    return (
      annonce &&
      annonce.ancien_prix !== null &&
      Number(annonce.ancien_prix) > Number(annonce.prix)
    );
  }

  async function requireLoginBefore(action: () => void) {
    const user = await getUser();

    if (!user) {
      Alert.alert(
        "Connexion requise",
        "Tu dois te connecter pour continuer.",
        [
          { text: "Annuler", style: "cancel" },
          { text: "Connexion", onPress: () => router.push("/login") },
        ]
      );
      return;
    }

    action();
  }

  async function toggleFavori() {
    if (!annonce) return;

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
          id_annonce: Number(annonce.id_annonce),
        }),
      });

      const data = await res.json();

      if (!data.ok) {
        Alert.alert("Erreur", data.message || "Erreur favoris.");
        return;
      }

      setAnnonce((prev) => (prev ? { ...prev, is_favori: !!data.favori } : prev));
    } catch (e) {
      Alert.alert("Erreur", "Erreur serveur.");
    }
  }

  async function addToCart() {
    if (!annonce || busyAction) return;

    const user = await getUser();

    if (!user) {
      Alert.alert(
        "Connexion requise",
        "Tu dois te connecter pour ajouter au panier.",
        [
          { text: "Annuler", style: "cancel" },
          { text: "Connexion", onPress: () => router.push("/login") },
        ]
      );
      return;
    }

    try {
      setBusyAction(true);

      const res = await fetch(`${API_BASE}/panier_add_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: Number(user.id_user),
          id_annonce: Number(annonce.id_annonce),
          qty: 1,
        }),
      });

      const data = await res.json();

      if (!data.ok) {
        Alert.alert("Erreur", data.message || "Impossible d’ajouter au panier.");
        return;
      }

      Alert.alert("Succès", data.message || "Ajouté au panier.");
    } catch (e) {
      Alert.alert("Erreur", "Erreur serveur.");
    } finally {
      setBusyAction(false);
    }
  }

  async function buyNow() {
    if (!annonce || busyAction) return;

    const user = await getUser();

    if (!user) {
      Alert.alert(
        "Connexion requise",
        "Tu dois te connecter pour acheter maintenant.",
        [
          { text: "Annuler", style: "cancel" },
          { text: "Connexion", onPress: () => router.push("/login") },
        ]
      );
      return;
    }

    try {
      setBusyAction(true);

      const res = await fetch(`${API_BASE}/buy_now_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: Number(user.id_user),
          id_annonce: Number(annonce.id_annonce),
        }),
      });

      const data = await res.json();

      if (!data.ok) {
        Alert.alert("Erreur", data.message || "Impossible de continuer l’achat.");
        return;
      }

      router.push("/panier");
    } catch (e) {
      Alert.alert("Erreur", "Erreur serveur.");
    } finally {
      setBusyAction(false);
    }
  }

  async function contactSeller() {
    if (!annonce) return;

    requireLoginBefore(async () => {
      const user = await getUser();

      if (user && Number(user.id_user) === Number(annonce.id_vendeur)) {
        Alert.alert("Impossible", "Tu ne peux pas t’envoyer un message à toi-même.");
        return;
      }

      router.push({
        pathname: "/messages",
        params: {
          vendeur: annonce.vendeur_nom ?? "Vendeur",
          annonceId: String(annonce.id_annonce),
          titre: annonce.titre ?? "",
          to: String(annonce.id_vendeur ?? 0),
        },
      });
    });
  }

  async function shareAnnonce() {
    if (!annonce) return;

    try {
      const link = `${WEB_BASE}/annonce.php?id=${annonce.id_annonce}`;
      await Share.share({
        message: `${annonce.titre}\n${link}`,
        url: link,
        title: annonce.titre,
      });
    } catch (e) {}
  }

  async function signalAnnonce() {
    if (!annonce || busyAction) return;

    const user = await getUser();

    if (!user) {
      Alert.alert(
        "Connexion requise",
        "Tu dois te connecter pour signaler une annonce.",
        [
          { text: "Annuler", style: "cancel" },
          { text: "Connexion", onPress: () => router.push("/login") },
        ]
      );
      return;
    }

    if (Number(user.id_user) === Number(annonce.id_vendeur)) {
      Alert.alert("Impossible", "Tu ne peux pas signaler ta propre annonce.");
      return;
    }

    Alert.alert(
      "Signaler l’annonce",
      "Choisis un motif.",
      [
        { text: "Annuler", style: "cancel" },
        { text: "Arnaque", onPress: () => submitSignalement("ARNAQUE") },
        { text: "Fausse annonce", onPress: () => submitSignalement("FAUSSE_ANNONCE") },
        { text: "Contenu interdit", onPress: () => submitSignalement("CONTENU_INTERDIT") },
        { text: "Prix suspect", onPress: () => submitSignalement("PRIX_SUSPECT") },
        { text: "Spam", onPress: () => submitSignalement("SPAM") },
        { text: "Autre", onPress: () => submitSignalement("AUTRE") },
      ]
    );
  }

  async function submitSignalement(motif: string) {
    if (!annonce || busyAction) return;

    const user = await getUser();
    if (!user) return;

    try {
      setBusyAction(true);

      const res = await fetch(`${API_BASE}/signaler_mobile.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: Number(user.id_user),
          id_annonce: Number(annonce.id_annonce),
          motif,
          description: "",
        }),
      });

      const data = await res.json();

      if (!data.ok) {
        Alert.alert("Erreur", data.message || "Signalement impossible.");
        return;
      }

      Alert.alert("Succès", data.message || "Signalement envoyé.");
    } catch (e) {
      Alert.alert("Erreur", "Erreur serveur.");
    } finally {
      setBusyAction(false);
    }
  }

  async function openMap() {
    if (!annonce || annonce.latitude === null || annonce.longitude === null) return;
    const url = `https://www.google.com/maps/search/?api=1&query=${annonce.latitude},${annonce.longitude}`;
    await Linking.openURL(url);
  }

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#2563eb" />
        <Text style={styles.loadingText}>Chargement...</Text>
      </View>
    );
  }

  if (errorMsg || !annonce) {
    return (
      <View style={styles.center}>
        <Text style={styles.errorTitle}>Erreur</Text>
        <Text style={styles.errorText}>{errorMsg || "Annonce introuvable"}</Text>

        <TouchableOpacity style={styles.backBtn} onPress={() => router.back()}>
          <Text style={styles.backBtnText}>Retour</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <>
      <Stack.Screen options={{ title: "Détail annonce" }} />

      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <TouchableOpacity style={styles.topBack} onPress={() => router.back()}>
          <Text style={styles.topBackText}>← Retour</Text>
        </TouchableOpacity>

        <ScrollView
          horizontal
          pagingEnabled
          showsHorizontalScrollIndicator={false}
          style={styles.galleryWrap}
        >
          {(annonce.gallery?.length ? annonce.gallery : [annonce.cover_image_url]).map(
            (img, index) => (
              <Image
                key={index}
                source={{ uri: img || undefined }}
                style={styles.mainImage}
                resizeMode="cover"
              />
            )
          )}
        </ScrollView>

        <View style={styles.card}>
          <View style={styles.priceRow}>
            <Text style={styles.title}>{annonce.titre}</Text>

            <View style={styles.priceBlock}>
              {hasPromo() ? (
                <>
                  <Text style={styles.oldPrice}>
                    {Number(annonce.ancien_prix).toFixed(2)} DH
                  </Text>
                  <Text style={styles.price}>
                    {Number(annonce.prix).toFixed(2)} DH
                  </Text>
                </>
              ) : (
                <Text style={styles.price}>{Number(annonce.prix).toFixed(2)} DH</Text>
              )}
            </View>
          </View>

          <View style={styles.metaRow}>
            <Feather name="map-pin" size={16} color="#4b5563" />
            <Text style={styles.meta}>{annonce.ville || "Ville inconnue"}</Text>
          </View>

          <TouchableOpacity
            style={styles.metaRow}
            onPress={() => router.push(`/vendeur/${annonce.id_vendeur}`)}
          >
            <Feather name="user" size={16} color="#4b5563" />
            <Text style={[styles.meta, styles.linkText]}>{annonce.vendeur_nom}</Text>
          </TouchableOpacity>

          <View style={styles.metaRow}>
            <Feather name="box" size={16} color="#4b5563" />
            <Text style={styles.meta}>Stock : {annonce.stock}</Text>
          </View>

          <View style={styles.metaRow}>
            <Feather name="shopping-cart" size={16} color="#4b5563" />
            <Text style={styles.meta}>Mode : {modeLabel(annonce.mode_vente)}</Text>
          </View>

          <View style={styles.metaRow}>
            <Feather name="tag" size={16} color="#4b5563" />
            <Text style={styles.meta}>Statut : {annonce.statut}</Text>
          </View>

          {annonce.categorie ? (
            <View style={styles.metaRow}>
              <Feather name="folder" size={16} color="#4b5563" />
              <Text style={styles.meta}>Catégorie : {annonce.categorie}</Text>
            </View>
          ) : null}

          {annonce.marque ? (
            <View style={styles.metaRow}>
              <Feather name="briefcase" size={16} color="#4b5563" />
              <Text style={styles.meta}>Marque : {annonce.marque}</Text>
            </View>
          ) : null}

          <Text style={styles.sectionTitle}>Description</Text>
          <Text style={styles.description}>
            {annonce.description || "Aucune description disponible."}
          </Text>

          <View style={styles.actionsWrap}>
            {annonce.can_add_to_cart ? (
              <TouchableOpacity
                style={[styles.bigActionBtn, styles.cartBtn]}
                onPress={addToCart}
                disabled={busyAction}
              >
                <Feather name="shopping-cart" size={16} color="#fff" />
                <Text style={styles.bigActionBtnText}>Ajouter au panier</Text>
              </TouchableOpacity>
            ) : null}

            {annonce.can_buy_now ? (
              <TouchableOpacity
                style={[styles.bigActionBtn, styles.buyNowBtn]}
                onPress={buyNow}
                disabled={busyAction}
              >
                <Feather name="credit-card" size={16} color="#fff" />
                <Text style={styles.bigActionBtnText}>Acheter maintenant</Text>
              </TouchableOpacity>
            ) : null}
          </View>

          <View style={styles.actionsWrap}>
            {annonce.can_contact ? (
              <TouchableOpacity style={styles.outlineActionBtn} onPress={contactSeller}>
                <Feather name="message-circle" size={16} color="#111827" />
                <Text style={styles.outlineActionBtnText}>Contacter le vendeur</Text>
              </TouchableOpacity>
            ) : null}

            <TouchableOpacity
              style={styles.outlineActionBtn}
              onPress={() => router.push(`/vendeur/${annonce.id_vendeur}`)}
            >
              <Feather name="user" size={16} color="#111827" />
              <Text style={styles.outlineActionBtnText}>Voir profil vendeur</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.actionsWrap}>
            <TouchableOpacity style={styles.smallActionBtn} onPress={shareAnnonce}>
              <Feather name="share-2" size={16} color="#6b7280" />
              <Text style={styles.smallActionBtnText}>Partager</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.smallActionBtn} onPress={toggleFavori}>
              <Ionicons
                name={annonce.is_favori ? "heart" : "heart-outline"}
                size={17}
                color={annonce.is_favori ? "#dc2626" : "#6b7280"}
              />
              <Text style={styles.smallActionBtnText}>
                {annonce.is_favori ? "Retirer favori" : "Ajouter favori"}
              </Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.smallActionBtn} onPress={signalAnnonce}>
              <Feather name="alert-triangle" size={16} color="#6b7280" />
              <Text style={styles.smallActionBtnText}>Signaler</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.smallActionBtn} onPress={() => router.back()}>
              <Feather name="corner-up-left" size={16} color="#6b7280" />
              <Text style={styles.smallActionBtnText}>Retour</Text>
            </TouchableOpacity>
          </View>

          {annonce.latitude !== null && annonce.longitude !== null ? (
            <TouchableOpacity style={styles.mapBtn} onPress={openMap}>
              <Text style={styles.mapBtnText}>Ouvrir la localisation</Text>
            </TouchableOpacity>
          ) : null}
        </View>

        {similarItems.length > 0 && (
          <View style={styles.similarSection}>
            <Text style={styles.sectionTitle}>Annonces similaires</Text>

            {similarItems.map((item) => (
              <TouchableOpacity
                key={item.id_annonce}
                style={styles.similarCard}
                onPress={() => router.push(`/annonce/${item.id_annonce}`)}
              >
                <Image
                  source={{ uri: item.image_url || undefined }}
                  style={styles.similarImage}
                  resizeMode="cover"
                />
                <View style={styles.similarBody}>
                  <Text style={styles.similarTitle} numberOfLines={2}>
                    {item.titre}
                  </Text>
                  <View style={styles.similarMetaRow}>
                    <Feather name="map-pin" size={15} color="#6b7280" />
                    <Text style={styles.similarCity}>{item.ville}</Text>
                  </View>
                  <Text style={styles.similarPrice}>
                    {Number(item.prix).toFixed(2)} DH
                  </Text>
                </View>
              </TouchableOpacity>
            ))}
          </View>
        )}
      </ScrollView>
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f4f4f5",
  },
  content: {
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
    color: "#111",
  },
  errorTitle: {
    fontSize: 24,
    fontWeight: "700",
    marginBottom: 10,
    color: "#111",
  },
  errorText: {
    fontSize: 16,
    color: "#555",
    textAlign: "center",
    marginBottom: 18,
  },
  backBtn: {
    backgroundColor: "#2563eb",
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 10,
  },
  backBtnText: {
    color: "#fff",
    fontWeight: "700",
  },
  topBack: {
    marginTop: 18,
    marginHorizontal: 14,
    marginBottom: 12,
  },
  topBackText: {
    fontSize: 16,
    fontWeight: "600",
    color: "#2563eb",
  },
  galleryWrap: {
    width: "100%",
  },
  mainImage: {
    width: width,
    height: 300,
    backgroundColor: "#ddd",
  },
  card: {
    backgroundColor: "#fff",
    margin: 14,
    padding: 16,
    borderRadius: 18,
  },
  priceRow: {
    marginBottom: 10,
  },
  title: {
    fontSize: 24,
    fontWeight: "800",
    color: "#111827",
    marginBottom: 10,
  },
  priceBlock: {
    alignItems: "flex-start",
  },
  oldPrice: {
    color: "#6b7280",
    textDecorationLine: "line-through",
    fontSize: 16,
    marginBottom: 4,
  },
  price: {
    color: "#2563eb",
    fontSize: 24,
    fontWeight: "800",
  },
  metaRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    marginBottom: 8,
  },
  meta: {
    fontSize: 15,
    color: "#4b5563",
  },
  linkText: {
    color: "#2563eb",
    fontWeight: "700",
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: "800",
    color: "#111827",
    marginTop: 16,
    marginBottom: 10,
  },
  description: {
    fontSize: 15,
    lineHeight: 22,
    color: "#374151",
  },
  actionsWrap: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
    marginTop: 14,
  },
  bigActionBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingVertical: 14,
    paddingHorizontal: 16,
    borderRadius: 12,
  },
  cartBtn: {
    backgroundColor: "#60a5fa",
  },
  buyNowBtn: {
    backgroundColor: "#111827",
  },
  bigActionBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 15,
  },
  outlineActionBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    borderColor: "#d1d5db",
    paddingVertical: 14,
    paddingHorizontal: 16,
    borderRadius: 12,
    backgroundColor: "#fff",
  },
  outlineActionBtnText: {
    color: "#111827",
    fontWeight: "700",
    fontSize: 15,
  },
  smallActionBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    borderColor: "#e5e7eb",
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: 12,
    backgroundColor: "#fff",
  },
  smallActionBtnText: {
    color: "#6b7280",
    fontWeight: "700",
    fontSize: 14,
  },
  mapBtn: {
    marginTop: 14,
    backgroundColor: "#111827",
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: "center",
  },
  mapBtnText: {
    color: "#fff",
    fontWeight: "700",
    fontSize: 15,
  },
  similarSection: {
    marginHorizontal: 14,
    marginTop: 8,
  },
  similarCard: {
    backgroundColor: "#fff",
    borderRadius: 16,
    overflow: "hidden",
    marginBottom: 14,
  },
  similarImage: {
    width: "100%",
    height: 180,
    backgroundColor: "#ddd",
  },
  similarBody: {
    padding: 12,
  },
  similarTitle: {
    fontSize: 16,
    fontWeight: "700",
    color: "#111827",
    marginBottom: 6,
  },
  similarMetaRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    marginBottom: 6,
  },
  similarPrice: {
    fontSize: 18,
    fontWeight: "800",
    color: "#2563eb",
    marginBottom: 4,
  },
  similarCity: {
    fontSize: 14,
    color: "#6b7280",
  },
});