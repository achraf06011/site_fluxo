import React, { useEffect, useState, useCallback, useMemo } from "react";
import {
  View,
  Text,
  FlatList,
  Image,
  StyleSheet,
  TouchableOpacity,
  Alert,
  TextInput,
  ScrollView,
  ActivityIndicator,
} from "react-native";
import { router, useFocusEffect } from "expo-router";
import { getUser } from "../../utils/auth";
import { Feather, Ionicons } from "@expo/vector-icons";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers";

const VILLES = [
  "Toutes",
  "Agadir",
  "Al Hoceima",
  "Asilah",
  "Azrou",
  "Beni Mellal",
  "Berkane",
  "Boujdour",
  "Casablanca",
  "Chefchaouen",
  "Dakhla",
  "El Jadida",
  "Errachidia",
  "Essaouira",
  "Fès",
  "Guelmim",
  "Ifrane",
  "Kenitra",
  "Khemisset",
  "Khouribga",
  "Laâyoune",
  "Larache",
  "Marrakech",
  "Meknès",
  "Mohammedia",
  "Nador",
  "Ouarzazate",
  "Oujda",
  "Rabat",
  "Safi",
  "Salé",
  "Settat",
  "Sidi Ifni",
  "Tanger",
  "Tarfaya",
  "Taza",
  "Tétouan",
];

const CATEGORIES = [
  "Toutes",
  "VOITURE",
  "MOTO",
  "TELEPHONE",
  "INFORMATIQUE",
  "TV_AUDIO",
  "ELECTROMENAGER",
  "MODE",
  "MAISON",
  "SPORT",
  "JEUX",
  "AUTRE",
];

const BRANDS_BY_CATEGORY: Record<string, string[]> = {
  Toutes: ["Toutes"],
  VOITURE: [
    "Toutes",
    "TOYOTA",
    "VOLKSWAGEN",
    "BMW",
    "MERCEDES-BENZ",
    "AUDI",
    "HYUNDAI",
    "KIA",
    "TESLA",
    "FORD",
    "RENAULT",
    "PEUGEOT",
    "HONDA",
    "NISSAN",
    "PORSCHE",
    "VOLVO",
    "MAZDA",
    "SUZUKI",
    "AUTRE",
  ],
  MOTO: [
    "Toutes",
    "HONDA",
    "YAMAHA",
    "KAWASAKI",
    "SUZUKI",
    "BMW",
    "KTM",
    "DUCATI",
    "TRIUMPH",
    "HARLEY-DAVIDSON",
    "INDIAN",
    "ROYAL ENFIELD",
    "APRILIA",
    "MOTO GUZZI",
    "HUSQVARNA",
    "GASGAS",
    "CFMOTO",
    "BENELLI",
    "AUTRE",
  ],
  TELEPHONE: [
    "Toutes",
    "APPLE",
    "SAMSUNG",
    "XIAOMI",
    "HUAWEI",
    "GOOGLE",
    "OPPO",
    "VIVO",
    "HONOR",
    "REALME",
    "MOTOROLA",
    "SONY",
    "ASUS",
    "NOKIA",
    "ONEPLUS",
    "NOTHING",
    "TECNO",
    "INFINIX",
    "AUTRE",
  ],
  INFORMATIQUE: [
    "Toutes",
    "APPLE",
    "LENOVO",
    "HP",
    "DELL",
    "ASUS",
    "ACER",
    "MSI",
    "SAMSUNG",
    "MICROSOFT",
    "RAZER",
    "NVIDIA",
    "INTEL",
    "AMD",
    "GIGABYTE",
    "CORSAIR",
    "LOGITECH",
    "HUAWEI",
    "AUTRE",
  ],
  TV_AUDIO: [
    "Toutes",
    "SAMSUNG",
    "LG",
    "SONY",
    "PANASONIC",
    "TCL",
    "HISENSE",
    "PHILIPS",
    "BOZE",
    "SONOS",
    "JBL",
    "MARSHALL",
    "BANG & OLUFSEN",
    "DENON",
    "SENNHEISER",
    "BEATS",
    "YAMAHA",
    "APPLE",
    "AUTRE",
  ],
  ELECTROMENAGER: [
    "Toutes",
    "MIELE",
    "BOSCH",
    "SIEMENS",
    "SAMSUNG",
    "LG",
    "WHIRLPOOL",
    "ELECTROLUX",
    "BEKO",
    "HAIER",
    "DYSON",
    "MOULINEX",
    "ROWENTA",
    "TEFAL",
    "SMEG",
    "DE DIETRICH",
    "LIEBHERR",
    "SHARP",
    "AUTRE",
  ],
  MODE: [
    "Toutes",
    "ZARA",
    "H&M",
    "MANGO",
    "BERSHKA",
    "PULL&BEAR",
    "STRADIVARIUS",
    "MASSIMO DUTTI",
    "UNIQLO",
    "GAP",
    "LEVI'S",
    "GUESS",
    "CALVIN KLEIN",
    "TOMMY HILFIGER",
    "RALPH LAUREN",
    "LACOSTE",
    "ASOS",
    "SHEIN",
    "AUTRE",
  ],
  MAISON: [
    "Toutes",
    "IKEA",
    "MAISONS DU MONDE",
    "ZARA HOME",
    "H&M HOME",
    "LEROY MERLIN",
    "CASTORAMA",
    "BUT",
    "CONFORAMA",
    "WESTELM",
    "POTTERY BARN",
    "AUTRE",
  ],
  SPORT: [
    "Toutes",
    "NIKE",
    "ADIDAS",
    "PUMA",
    "UNDER ARMOUR",
    "NEW BALANCE",
    "ASICS",
    "LULULEMON",
    "JORDAN",
    "SKECHERS",
    "REEBOK",
    "CONVERSE",
    "THE NORTH FACE",
    "COLUMBIA",
    "FILA",
    "MIZUNO",
    "SALOMON",
    "UMBRO",
    "AUTRE",
  ],
  JEUX: [
    "Toutes",
    "SONY",
    "PLAYSTATION",
    "MICROSOFT",
    "NINTENDO",
    "UBISOFT",
    "ELECTRONIC ARTS",
    "ROCKSTAR GAMES",
    "ACTIVISION",
    "BLIZZARD",
    "EPIC GAMES",
    "KONAMI",
    "AUTRE",
  ],
  AUTRE: ["Toutes", "AUTRE"],
};

const TRI_OPTIONS = [
  { key: "recent", label: "Plus récentes" },
  { key: "prix_asc", label: "Prix croissant" },
  { key: "prix_desc", label: "Prix décroissant" },
  { key: "promo", label: "Promos" },
  { key: "stock_desc", label: "Stock" },
];

type AnnonceType = {
  id_annonce: number;
  id_vendeur: number;
  titre: string;
  description?: string;
  prix: number | string;
  ancien_prix?: number | string | null;
  ville?: string;
  stock?: number | string;
  mode_vente?: string;
  cover_image?: string | null;
  date_publication?: string;
  categorie?: string;
  marque?: string;
  vendeur_nom?: string;
  is_favori?: boolean;
};

export default function HomeScreen() {
  const [annonces, setAnnonces] = useState<AnnonceType[]>([]);
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState("");
  const [showFilters, setShowFilters] = useState(false);

  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [ville, setVille] = useState("Toutes");
  const [categorie, setCategorie] = useState("Toutes");
  const [marque, setMarque] = useState("Toutes");
  const [prixMin, setPrixMin] = useState("");
  const [prixMax, setPrixMax] = useState("");
  const [tri, setTri] = useState("recent");

  const brandOptions = useMemo(() => {
    return BRANDS_BY_CATEGORY[categorie] || ["Toutes"];
  }, [categorie]);

  useEffect(() => {
    if (!brandOptions.includes(marque)) {
      setMarque("Toutes");
    }
  }, [brandOptions, marque]);

  async function loadAnnonces() {
    try {
      setLoading(true);
      setErrorMsg("");

      const user = await getUser();
      const currentUserId = user ? Number(user.id_user) : 0;

      const params = new URLSearchParams();

      if (currentUserId > 0) {
        params.append("current_user_id", String(currentUserId));
      }

      if (search.trim()) {
        params.append("search", search.trim());
      }

      if (ville !== "Toutes") {
        params.append("ville", ville);
      }

      if (categorie !== "Toutes") {
        params.append("categorie", categorie);
      }

      if (marque !== "Toutes") {
        params.append("marque", marque);
      }

      if (prixMin.trim()) {
        params.append("prix_min", prixMin.trim());
      }

      if (prixMax.trim()) {
        params.append("prix_max", prixMax.trim());
      }

      if (tri) {
        params.append("tri", tri);
      }

      const query = params.toString();
      const url = `${API_BASE}/api/annonces.php${query ? `?${query}` : ""}`;

      const res = await fetch(url);
      const data = await res.json();

      if (data.ok) {
        setAnnonces(Array.isArray(data.annonces) ? data.annonces : []);
      } else {
        setErrorMsg(data.message || "Erreur chargement annonces");
      }
    } catch (error: any) {
      setErrorMsg(String(error));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadAnnonces();
  }, []);

  useFocusEffect(
    useCallback(() => {
      loadAnnonces();
    }, [search, ville, categorie, marque, prixMin, prixMax, tri])
  );

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

  function canDirectBuy(item: AnnonceType) {
    const mode = String(item.mode_vente || "");
    const stock = Number(item.stock || 0);
    return (mode === "PAIEMENT_DIRECT" || mode === "LES_DEUX") && stock > 0;
  }

  function hasPromo(item: AnnonceType) {
    return (
      item.ancien_prix !== null &&
      item.ancien_prix !== undefined &&
      Number(item.ancien_prix) > Number(item.prix)
    );
  }

  function modeLabel(mode?: string) {
    if (mode === "PAIEMENT_DIRECT") return "PAIEMENT DIRECT";
    if (mode === "POSSIBILITE_CONTACTE") return "CONTACTER LE VENDEUR";
    if (mode === "LES_DEUX") return "PAIEMENT DIRECT OU CONTACT";
    return mode || "";
  }

  async function toggleFavori(item: AnnonceType) {
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
      const res = await fetch(`${API_BASE}/api/favori_toggle.php`, {
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

  async function goToMessage(item: AnnonceType) {
    requireLoginBefore(async () => {
      const user = await getUser();

      if (user && Number(user.id_user) === Number(item.id_vendeur ?? 0)) {
        Alert.alert(
          "Impossible",
          "Tu ne peux pas t’envoyer un message à toi-même."
        );
        return;
      }

      router.push({
        pathname: "/messages",
        params: {
          vendeur: item.vendeur_nom ?? "Vendeur",
          annonceId: String(item.id_annonce),
          titre: item.titre ?? "",
          to: String(item.id_vendeur ?? 0),
        },
      });
    });
  }

  async function goToBuy(item: AnnonceType) {
    if (!canDirectBuy(item)) {
      Alert.alert(
        "Indisponible",
        "Cette annonce n'est pas disponible en paiement direct."
      );
      return;
    }

    requireLoginBefore(async () => {
      try {
        const user = await getUser();
        if (!user) return;

        const res = await fetch(`${API_BASE}/api/panier_add_mobile.php`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            user_id: Number(user.id_user),
            id_annonce: Number(item.id_annonce),
            qty: 1,
          }),
        });

        const data = await res.json();

        if (!data.ok) {
          Alert.alert(
            "Erreur",
            data.message || "Impossible d’ajouter au panier."
          );
          return;
        }

        router.push("/panier");
      } catch (e) {
        Alert.alert("Erreur", "Erreur serveur.");
      }
    });
  }

  function applySearch() {
    setSearch(searchInput);
  }

  function resetFilters() {
    setSearchInput("");
    setSearch("");
    setVille("Toutes");
    setCategorie("Toutes");
    setMarque("Toutes");
    setPrixMin("");
    setPrixMax("");
    setTri("recent");
  }

  function renderChip(
    label: string,
    active: boolean,
    onPress: () => void,
    small = false
  ) {
    return (
      <TouchableOpacity
        key={label}
        style={[
          styles.chip,
          small && styles.chipSmall,
          active && styles.chipActive,
        ]}
        onPress={onPress}
      >
        <Text
          style={[
            styles.chipText,
            small && styles.chipTextSmall,
            active && styles.chipTextActive,
          ]}
        >
          {label}
        </Text>
      </TouchableOpacity>
    );
  }

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#2563eb" />
        <Text style={styles.text}>Chargement...</Text>
      </View>
    );
  }

  if (errorMsg) {
    return (
      <View style={styles.center}>
        <Text style={styles.text}>Erreur :</Text>
        <Text style={styles.text}>{errorMsg}</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <FlatList
        data={annonces}
        keyExtractor={(item) => String(item.id_annonce)}
        contentContainerStyle={{ paddingBottom: 30 }}
        ListHeaderComponent={
          <View style={styles.headerWrap}>
            <View style={styles.hero}>
              <Text style={styles.heroTitle}>Fluxo</Text>
              <Text style={styles.heroSubtitle}>
                Marketplace entre particuliers
              </Text>

              <View style={styles.searchRow}>
                <View style={styles.searchInputWrap}>
                  <Feather name="search" size={18} color="#6b7280" />
                  <TextInput
                    value={searchInput}
                    onChangeText={setSearchInput}
                    placeholder="Rechercher une annonce..."
                    placeholderTextColor="#9ca3af"
                    style={styles.searchInput}
                    returnKeyType="search"
                    onSubmitEditing={applySearch}
                  />
                </View>

                <TouchableOpacity
                  style={styles.searchBtn}
                  onPress={applySearch}
                >
                  <Feather name="search" size={18} color="#fff" />
                </TouchableOpacity>
              </View>

              <TouchableOpacity
                style={styles.filtersToggle}
                onPress={() => setShowFilters((prev) => !prev)}
              >
                <Feather name="sliders" size={16} color="#fff" />
                <Text style={styles.filtersToggleText}>
                  {showFilters ? "Masquer filtres" : "Filtres avancés"}
                </Text>
                <Feather
                  name={showFilters ? "chevron-up" : "chevron-down"}
                  size={16}
                  color="#fff"
                />
              </TouchableOpacity>
            </View>

            {showFilters ? (
              <View style={styles.filtersCard}>
                <Text style={styles.sectionTitle}>Ville</Text>
                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  style={styles.chipsScroll}
                >
                  <View style={styles.chipsWrap}>
                    {VILLES.map((item) =>
                      renderChip(item, ville === item, () => setVille(item), true)
                    )}
                  </View>
                </ScrollView>

                <Text style={styles.sectionTitle}>Catégorie</Text>
                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  style={styles.chipsScroll}
                >
                  <View style={styles.chipsWrap}>
                    {CATEGORIES.map((item) =>
                      renderChip(
                        item,
                        categorie === item,
                        () => setCategorie(item),
                        true
                      )
                    )}
                  </View>
                </ScrollView>

                <Text style={styles.sectionTitle}>Marque</Text>
                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  style={styles.chipsScroll}
                >
                  <View style={styles.chipsWrap}>
                    {brandOptions.map((item) =>
                      renderChip(item, marque === item, () => setMarque(item), true)
                    )}
                  </View>
                </ScrollView>

                <Text style={styles.sectionTitle}>Prix</Text>
                <View style={styles.priceInputsRow}>
                  <TextInput
                    style={styles.priceInput}
                    value={prixMin}
                    onChangeText={(v) => setPrixMin(v.replace(",", "."))}
                    placeholder="Prix min"
                    placeholderTextColor="#9ca3af"
                    keyboardType="decimal-pad"
                  />
                  <TextInput
                    style={styles.priceInput}
                    value={prixMax}
                    onChangeText={(v) => setPrixMax(v.replace(",", "."))}
                    placeholder="Prix max"
                    placeholderTextColor="#9ca3af"
                    keyboardType="decimal-pad"
                  />
                </View>

                <Text style={styles.sectionTitle}>Tri</Text>
                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  style={styles.chipsScroll}
                >
                  <View style={styles.chipsWrap}>
                    {TRI_OPTIONS.map((item) =>
                      renderChip(
                        item.label,
                        tri === item.key,
                        () => setTri(item.key),
                        true
                      )
                    )}
                  </View>
                </ScrollView>

                <View style={styles.filterButtonsRow}>
                  <TouchableOpacity style={styles.applyBtn} onPress={loadAnnonces}>
                    <Feather name="search" size={16} color="#fff" />
                    <Text style={styles.applyBtnText}>Filtrer</Text>
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={styles.resetBtn}
                    onPress={() => {
                      resetFilters();
                      setTimeout(() => {
                        loadAnnonces();
                      }, 0);
                    }}
                  >
                    <Ionicons name="refresh-outline" size={16} color="#374151" />
                    <Text style={styles.resetBtnText}>Réinitialiser</Text>
                  </TouchableOpacity>
                </View>
              </View>
            ) : null}

            <View style={styles.resultsRow}>
              <Text style={styles.resultsText}>
                {annonces.length} annonce(s) trouvée(s)
              </Text>
              <Text style={styles.sortText}>
                Tri :{" "}
                {TRI_OPTIONS.find((x) => x.key === tri)?.label || "Plus récentes"}
              </Text>
            </View>
          </View>
        }
        renderItem={({ item }) => (
          <TouchableOpacity
            activeOpacity={0.92}
            style={styles.card}
            onPress={() => router.push(`/annonce/${item.id_annonce}`)}
          >
            <View style={styles.imageContainer}>
              {hasPromo(item) ? (
                <View style={styles.promoBadge}>
                  <Text style={styles.promoBadgeText}>PROMO</Text>
                </View>
              ) : null}

              <Image
                source={{
                  uri: item.cover_image
                    ? `${API_BASE}/uploads/${item.cover_image}`
                    : "https://picsum.photos/800/600",
                }}
                style={styles.image}
                resizeMode="cover"
              />
            </View>

            <View style={styles.cardBody}>
              <Text style={styles.title} numberOfLines={2}>
                {item.titre}
              </Text>

              <View style={styles.priceWrap}>
                {hasPromo(item) ? (
                  <>
                    <Text style={styles.oldPrice}>
                      {Number(item.ancien_prix).toFixed(2)} DH
                    </Text>
                    <Text style={styles.pricePromo}>
                      {Number(item.prix).toFixed(2)} DH
                    </Text>
                  </>
                ) : (
                  <Text style={styles.price}>
                    {Number(item.prix).toFixed(2)} DH
                  </Text>
                )}
              </View>

              <View style={styles.metaInlineRow}>
                <View style={styles.metaInlineItem}>
                  <Feather name="user" size={14} color="#6b7280" />
                  <Text style={styles.metaInlineText}>
                    {item.vendeur_nom || "Vendeur"}
                  </Text>
                </View>

                {item.ville ? (
                  <View style={styles.metaInlineItem}>
                    <Feather name="map-pin" size={14} color="#6b7280" />
                    <Text style={styles.metaInlineText}>{item.ville}</Text>
                  </View>
                ) : null}

                {typeof item.stock !== "undefined" ? (
                  <View style={styles.metaInlineItem}>
                    <Feather name="box" size={14} color="#6b7280" />
                    <Text style={styles.metaInlineText}>Stock: {item.stock}</Text>
                  </View>
                ) : null}
              </View>

              {(item.categorie || item.marque) ? (
                <View style={styles.metaInlineRow}>
                  {item.categorie ? (
                    <View style={styles.tagPill}>
                      <Text style={styles.tagPillText}>{item.categorie}</Text>
                    </View>
                  ) : null}
                  {item.marque ? (
                    <View style={styles.tagPill}>
                      <Text style={styles.tagPillText}>{item.marque}</Text>
                    </View>
                  ) : null}
                </View>
              ) : null}

              {item.mode_vente ? (
                <Text style={styles.modeText}>
                  Mode : {modeLabel(item.mode_vente)}
                </Text>
              ) : null}

              <View style={styles.buttons}>
                <TouchableOpacity
                  style={styles.btn}
                  onPress={() => router.push(`/annonce/${item.id_annonce}`)}
                >
                  <Text style={styles.btnText}>Voir</Text>
                </TouchableOpacity>

                {canDirectBuy(item) ? (
                  <TouchableOpacity
                    style={styles.btnBuy}
                    onPress={() => goToBuy(item)}
                  >
                    <Text style={styles.btnBuyText}>Acheter</Text>
                  </TouchableOpacity>
                ) : null}

                <TouchableOpacity
                  style={styles.btnMessage}
                  onPress={() => goToMessage(item)}
                >
                  <Text style={styles.btnMessageText}>Message</Text>
                </TouchableOpacity>

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
            </View>
          </TouchableOpacity>
        )}
        ListEmptyComponent={
          <View style={styles.emptyWrap}>
            <Feather name="search" size={34} color="#9ca3af" />
            <Text style={styles.emptyTitle}>Aucune annonce trouvée</Text>
            <Text style={styles.emptyText}>
              Essaie une autre recherche ou enlève quelques filtres.
            </Text>
          </View>
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f5f5f5",
  },
  headerWrap: {
    paddingBottom: 6,
  },
  hero: {
    backgroundColor: "#0f172a",
    marginHorizontal: 12,
    marginTop: 12,
    borderRadius: 20,
    padding: 16,
  },
  heroTitle: {
    fontSize: 32,
    fontWeight: "900",
    color: "#fff",
  },
  heroSubtitle: {
    fontSize: 14,
    color: "#cbd5e1",
    marginTop: 4,
    marginBottom: 14,
  },
  searchRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  searchInputWrap: {
    flex: 1,
    minHeight: 52,
    borderRadius: 14,
    backgroundColor: "#fff",
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 14,
    gap: 10,
  },
  searchInput: {
    flex: 1,
    color: "#111827",
    fontSize: 15,
  },
  searchBtn: {
    width: 52,
    height: 52,
    borderRadius: 14,
    backgroundColor: "#2563eb",
    alignItems: "center",
    justifyContent: "center",
  },
  filtersToggle: {
    marginTop: 12,
    alignSelf: "flex-start",
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    backgroundColor: "rgba(255,255,255,0.10)",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.18)",
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  filtersToggleText: {
    color: "#fff",
    fontWeight: "700",
  },
  filtersCard: {
    backgroundColor: "#fff",
    marginHorizontal: 12,
    marginTop: 12,
    borderRadius: 18,
    padding: 14,
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: "800",
    color: "#111827",
    marginBottom: 8,
    marginTop: 6,
  },
  chipsScroll: {
    marginBottom: 6,
  },
  chipsWrap: {
    flexDirection: "row",
    gap: 8,
    paddingRight: 12,
  },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "#d1d5db",
    backgroundColor: "#fff",
  },
  chipSmall: {
    paddingHorizontal: 12,
    paddingVertical: 9,
  },
  chipActive: {
    backgroundColor: "#111827",
    borderColor: "#111827",
  },
  chipText: {
    color: "#374151",
    fontWeight: "700",
    fontSize: 13,
  },
  chipTextSmall: {
    fontSize: 12,
  },
  chipTextActive: {
    color: "#fff",
  },
  priceInputsRow: {
    flexDirection: "row",
    gap: 10,
    marginBottom: 6,
  },
  priceInput: {
    flex: 1,
    minHeight: 48,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "#d1d5db",
    backgroundColor: "#fff",
    paddingHorizontal: 12,
    color: "#111827",
  },
  filterButtonsRow: {
    flexDirection: "row",
    gap: 10,
    marginTop: 14,
    flexWrap: "wrap",
  },
  applyBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    backgroundColor: "#111827",
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 13,
  },
  applyBtnText: {
    color: "#fff",
    fontWeight: "800",
  },
  resetBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    backgroundColor: "#fff",
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 13,
    borderWidth: 1,
    borderColor: "#d1d5db",
  },
  resetBtnText: {
    color: "#374151",
    fontWeight: "800",
  },
  resultsRow: {
    paddingHorizontal: 14,
    paddingTop: 14,
    paddingBottom: 4,
    flexDirection: "row",
    justifyContent: "space-between",
    gap: 10,
    flexWrap: "wrap",
  },
  resultsText: {
    color: "#374151",
    fontWeight: "600",
    fontSize: 14,
  },
  sortText: {
    color: "#6b7280",
    fontWeight: "700",
    fontSize: 13,
  },
  center: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    padding: 20,
    backgroundColor: "#ffffff",
  },
  text: {
    fontSize: 16,
    color: "#111",
    textAlign: "center",
    marginTop: 8,
    marginBottom: 8,
  },
  card: {
    backgroundColor: "#fff",
    marginHorizontal: 12,
    marginTop: 12,
    borderRadius: 16,
    overflow: "hidden",
    shadowColor: "#000",
    shadowOpacity: 0.05,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  imageContainer: {
    width: "100%",
    height: 220,
    backgroundColor: "#e5e7eb",
    position: "relative",
  },
  promoBadge: {
    position: "absolute",
    top: 12,
    right: 12,
    zIndex: 5,
    backgroundColor: "#e11d48",
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 7,
  },
  promoBadgeText: {
    color: "#fff",
    fontWeight: "900",
    fontSize: 12,
  },
  image: {
    width: "100%",
    height: "100%",
  },
  cardBody: {
    padding: 14,
  },
  title: {
    fontSize: 18,
    fontWeight: "800",
    color: "#111827",
  },
  priceWrap: {
    marginTop: 8,
  },
  oldPrice: {
    color: "#9ca3af",
    textDecorationLine: "line-through",
    fontSize: 15,
    marginBottom: 3,
    fontWeight: "700",
  },
  price: {
    fontSize: 22,
    color: "#2563eb",
    fontWeight: "900",
  },
  pricePromo: {
    fontSize: 22,
    color: "#dc2626",
    fontWeight: "900",
  },
  metaInlineRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    alignItems: "center",
    gap: 10,
    marginTop: 10,
  },
  metaInlineItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
  },
  metaInlineText: {
    color: "#6b7280",
    fontSize: 13,
  },
  tagPill: {
    backgroundColor: "#f3f4f6",
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  tagPillText: {
    color: "#374151",
    fontSize: 12,
    fontWeight: "800",
  },
  modeText: {
    color: "#374151",
    fontSize: 13,
    fontWeight: "700",
    marginTop: 10,
  },
  buttons: {
    flexDirection: "row",
    marginTop: 14,
    gap: 8,
    flexWrap: "wrap",
    alignItems: "center",
  },
  btn: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: 10,
    backgroundColor: "#fff",
  },
  btnText: {
    color: "#111",
    fontWeight: "700",
  },
  btnBuy: {
    backgroundColor: "#2563eb",
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 10,
  },
  btnBuyText: {
    color: "#fff",
    fontWeight: "800",
  },
  btnMessage: {
    borderWidth: 1,
    borderColor: "#2563eb",
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: 10,
    backgroundColor: "#fff",
  },
  btnMessageText: {
    color: "#2563eb",
    fontWeight: "800",
  },
  btnHeart: {
    width: 42,
    height: 42,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#fecaca",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#fff",
  },
  emptyWrap: {
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 24,
    paddingVertical: 44,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: "800",
    color: "#111827",
    marginTop: 12,
  },
  emptyText: {
    fontSize: 14,
    color: "#6b7280",
    textAlign: "center",
    marginTop: 8,
  },
});