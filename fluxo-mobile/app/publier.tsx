import React, { useEffect, useMemo, useState } from "react";
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  TextInput,
  Image,
  Switch,
  Platform,
} from "react-native";
import { Stack, router } from "expo-router";
import * as ImagePicker from "expo-image-picker";
import * as Location from "expo-location";
import { getUser } from "../utils/auth";
import { Feather, Ionicons } from "@expo/vector-icons";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

const CATEGORIES: Record<string, string[]> = {
  VOITURE: [
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
  AUTRE: ["AUTRE"],
};

const MAROC_CITIES = [
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

type PickedImage = {
  uri: string;
  name: string;
  type: string;
};

export default function PublierScreen() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [user, setUser] = useState<any>(null);

  const [titre, setTitre] = useState("");
  const [description, setDescription] = useState("");
  const [prix, setPrix] = useState("");
  const [stock, setStock] = useState("1");
  const [typeProduit, setTypeProduit] = useState<"NEUF" | "OCCASION">("NEUF");
  const [modeVente, setModeVente] = useState<
    "PAIEMENT_DIRECT" | "POSSIBILITE_CONTACTE" | "LES_DEUX"
  >("LES_DEUX");

  const [categorie, setCategorie] = useState("TELEPHONE");
  const [marque, setMarque] = useState("APPLE");
  const [ville, setVille] = useState("Marrakech");

  const [livraisonActive, setLivraisonActive] = useState(false);
  const [livraisonSame, setLivraisonSame] = useState("15");
  const [livraisonOther, setLivraisonOther] = useState("40");

  const [latitude, setLatitude] = useState("");
  const [longitude, setLongitude] = useState("");

  const [coverImage, setCoverImage] = useState<PickedImage | null>(null);
  const [secondaryImages, setSecondaryImages] = useState<PickedImage[]>([]);

  const brands = useMemo(() => CATEGORIES[categorie] || ["AUTRE"], [categorie]);

  useEffect(() => {
    async function init() {
      const currentUser = await getUser();
      setUser(currentUser);

      if (!currentUser?.id_user) {
        setLoading(false);
        return;
      }

      if (!brands.includes(marque)) {
        setMarque(brands[0]);
      }

      setLoading(false);
    }

    init();
  }, []);

  useEffect(() => {
    if (!brands.includes(marque)) {
      setMarque(brands[0]);
    }
  }, [categorie]);

  function sanitizeDecimal(value: string) {
    return value.replace(",", ".").replace(/[^0-9.]/g, "");
  }

  function sanitizeInteger(value: string) {
    return value.replace(/[^0-9]/g, "");
  }

  async function pickCoverImage() {
    try {
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!perm.granted) {
        Alert.alert("Permission refusée", "Autorise l’accès aux photos.");
        return;
      }

      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ["images"],
        quality: 0.9,
        allowsMultipleSelection: false,
      });

      if (result.canceled || !result.assets?.length) return;

      const asset = result.assets[0];
      const fileName = asset.fileName || `cover_${Date.now()}.jpg`;
      const mimeType = asset.mimeType || "image/jpeg";

      setCoverImage({
        uri: asset.uri,
        name: fileName,
        type: mimeType,
      });
    } catch (e) {
      Alert.alert("Erreur", "Impossible de choisir l’image principale.");
    }
  }

  async function addSecondaryImage() {
    try {
      if (secondaryImages.length >= 8) {
        Alert.alert("Limite atteinte", "Maximum 8 photos secondaires.");
        return;
      }

      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!perm.granted) {
        Alert.alert("Permission refusée", "Autorise l’accès aux photos.");
        return;
      }

      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ["images"],
        quality: 0.9,
        allowsMultipleSelection: false,
      });

      if (result.canceled || !result.assets?.length) return;

      const asset = result.assets[0];
      const fileName = asset.fileName || `image_${Date.now()}.jpg`;
      const mimeType = asset.mimeType || "image/jpeg";

      setSecondaryImages((prev) => [
        ...prev,
        {
          uri: asset.uri,
          name: fileName,
          type: mimeType,
        },
      ]);
    } catch (e) {
      Alert.alert("Erreur", "Impossible d’ajouter une photo.");
    }
  }

  function removeSecondaryImage(index: number) {
    setSecondaryImages((prev) => prev.filter((_, i) => i !== index));
  }

  async function useCurrentLocation() {
    try {
      const perm = await Location.requestForegroundPermissionsAsync();

      if (perm.status !== "granted") {
        Alert.alert(
          "Permission refusée",
          "Active la localisation pour utiliser ta position actuelle."
        );
        return;
      }

      const pos = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.High,
      });

      setLatitude(String(Number(pos.coords.latitude).toFixed(7)));
      setLongitude(String(Number(pos.coords.longitude).toFixed(7)));

      Alert.alert("Succès", "Position actuelle récupérée.");
    } catch (e) {
      Alert.alert("Erreur", "Impossible de récupérer ta position.");
    }
  }

  function validateForm() {
    if (!titre.trim() || titre.trim().length < 3) {
      Alert.alert("Erreur", "Titre invalide (minimum 3 caractères).");
      return false;
    }

    if (!description.trim() || description.trim().length < 10) {
      Alert.alert("Erreur", "Description trop courte (minimum 10 caractères).");
      return false;
    }

    if (!prix.trim() || isNaN(Number(prix)) || Number(prix) < 0) {
      Alert.alert("Erreur", "Prix invalide.");
      return false;
    }

    if (!stock.trim() || isNaN(Number(stock)) || Number(stock) < 0) {
      Alert.alert("Erreur", "Stock invalide.");
      return false;
    }

    if (!MAROC_CITIES.includes(ville)) {
      Alert.alert("Erreur", "Ville invalide.");
      return false;
    }

    if (!CATEGORIES[categorie]) {
      Alert.alert("Erreur", "Catégorie invalide.");
      return false;
    }

    if (!brands.includes(marque)) {
      Alert.alert("Erreur", "Marque invalide.");
      return false;
    }

    if (!latitude.trim() || !longitude.trim()) {
      Alert.alert("Erreur", "Choisis la localisation.");
      return false;
    }

    if (isNaN(Number(latitude)) || isNaN(Number(longitude))) {
      Alert.alert("Erreur", "Coordonnées GPS invalides.");
      return false;
    }

    if (!coverImage && secondaryImages.length === 0) {
      Alert.alert("Erreur", "Ajoute au moins une photo.");
      return false;
    }

    if (!livraisonSame.trim() || isNaN(Number(livraisonSame)) || Number(livraisonSame) < 0) {
      Alert.alert("Erreur", "Prix livraison même ville invalide.");
      return false;
    }

    if (!livraisonOther.trim() || isNaN(Number(livraisonOther)) || Number(livraisonOther) < 0) {
      Alert.alert("Erreur", "Prix livraison autre ville invalide.");
      return false;
    }

    return true;
  }

  async function submitAnnonce() {
    if (!user?.id_user) {
      Alert.alert("Connexion requise", "Tu dois te connecter.");
      return;
    }

    if (!validateForm()) return;

    try {
      setSaving(true);

      const formData = new FormData();

      formData.append("user_id", String(Number(user.id_user)));
      formData.append("titre", titre.trim());
      formData.append("description", description.trim());
      formData.append("prix", String(Number(prix)));
      formData.append("stock", String(Number(stock)));
      formData.append("type", typeProduit);
      formData.append("mode_vente", modeVente);
      formData.append("categorie", categorie);
      formData.append("marque", marque);
      formData.append("ville", ville);
      formData.append("latitude", String(Number(latitude)));
      formData.append("longitude", String(Number(longitude)));
      formData.append("livraison_active", livraisonActive ? "1" : "0");
      formData.append("livraison_prix_same_city", String(Number(livraisonSame || "0")));
      formData.append("livraison_prix_other_city", String(Number(livraisonOther || "0")));

      if (coverImage) {
        formData.append("cover_image", {
          uri: coverImage.uri,
          name: coverImage.name,
          type: coverImage.type,
        } as any);
      }

      secondaryImages.forEach((img) => {
        formData.append("images[]", {
          uri: img.uri,
          name: img.name,
          type: img.type,
        } as any);
      });

      const res = await fetch(`${API_BASE}/publier_mobile.php`, {
        method: "POST",
        headers: {
          Accept: "application/json",
        },
        body: formData,
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
        Alert.alert("Erreur", data.message || "Publication impossible.");
        return;
      }

      Alert.alert(
        "Succès",
        data.message || "Annonce publiée avec succès.",
        [
          {
            text: "OK",
            onPress: () => {
              if (data.id_annonce) {
                router.replace(`/annonce/${data.id_annonce}`);
              } else {
                router.replace("/mes-annonces");
              }
            },
          },
        ]
      );
    } catch (e: any) {
      Alert.alert("Erreur", String(e?.message || e || "Erreur serveur."));
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return (
      <>
        <Stack.Screen options={{ title: "Publier" }} />
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
        <Stack.Screen options={{ title: "Publier" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Connexion requise</Text>
          <Text style={styles.errorText}>
            Tu dois te connecter pour publier une annonce.
          </Text>

          <TouchableOpacity style={styles.primaryBtn} onPress={() => router.push("/login")}>
            <Text style={styles.primaryBtnText}>Connexion</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  return (
    <>
      <Stack.Screen options={{ title: "Publier une annonce" }} />

      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <View style={styles.heroCard}>
          <Text style={styles.heroTitle}>Publier une annonce</Text>
          <Text style={styles.heroText}>
            Ajoute ton produit, choisis sa catégorie, sa marque, le prix, le stock et le mode de vente.
          </Text>

          <View style={styles.badgesWrap}>
            {[
              "Catégorie",
              "Marque",
              "Ville Maroc",
              "Position actuelle",
              "Livraison",
              "Photo principale",
              "8 photos secondaires max",
            ].map((item) => (
              <View key={item} style={styles.softBadge}>
                <Text style={styles.softBadgeText}>{item}</Text>
              </View>
            ))}
          </View>
        </View>

        <View style={styles.formCard}>
          <View style={styles.formTop}>
            <Text style={styles.formTitle}>Nouvelle annonce</Text>
            <Text style={styles.uploadHint}>upload</Text>
          </View>

          <Text style={styles.label}>Titre</Text>
          <TextInput
            style={styles.input}
            value={titre}
            onChangeText={setTitre}
            placeholder="Ex: iPhone 13 128GB"
            placeholderTextColor="#9ca3af"
          />

          <Text style={styles.label}>Description</Text>
          <TextInput
            style={[styles.input, styles.textarea]}
            value={description}
            onChangeText={setDescription}
            placeholder="Décris le produit..."
            placeholderTextColor="#9ca3af"
            multiline
          />

          <Text style={styles.label}>Catégorie</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chipsScroll}>
            <View style={styles.chipsWrap}>
              {Object.keys(CATEGORIES).map((cat) => (
                <TouchableOpacity
                  key={cat}
                  style={[styles.chip, categorie === cat && styles.chipActive]}
                  onPress={() => setCategorie(cat)}
                >
                  <Text style={[styles.chipText, categorie === cat && styles.chipTextActive]}>
                    {cat}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          </ScrollView>

          <Text style={styles.label}>Marque</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chipsScroll}>
            <View style={styles.chipsWrap}>
              {brands.map((item) => (
                <TouchableOpacity
                  key={item}
                  style={[styles.chip, marque === item && styles.chipActive]}
                  onPress={() => setMarque(item)}
                >
                  <Text style={[styles.chipText, marque === item && styles.chipTextActive]}>
                    {item}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          </ScrollView>

          <View style={styles.row}>
            <View style={styles.half}>
              <Text style={styles.label}>Prix (DH)</Text>
              <TextInput
                style={styles.input}
                value={prix}
                onChangeText={(v) => setPrix(sanitizeDecimal(v))}
                placeholder="0"
                placeholderTextColor="#9ca3af"
                keyboardType="decimal-pad"
              />
            </View>

            <View style={styles.half}>
              <Text style={styles.label}>Stock</Text>
              <TextInput
                style={styles.input}
                value={stock}
                onChangeText={(v) => setStock(sanitizeInteger(v))}
                placeholder="1"
                placeholderTextColor="#9ca3af"
                keyboardType="numeric"
              />
            </View>
          </View>

          <View style={styles.row}>
            <View style={styles.half}>
              <Text style={styles.label}>Type</Text>
              <View style={styles.inlineChoiceWrap}>
                {(["NEUF", "OCCASION"] as const).map((item) => (
                  <TouchableOpacity
                    key={item}
                    style={[
                      styles.inlineChoice,
                      typeProduit === item && styles.inlineChoiceActive,
                    ]}
                    onPress={() => setTypeProduit(item)}
                  >
                    <Text
                      style={[
                        styles.inlineChoiceText,
                        typeProduit === item && styles.inlineChoiceTextActive,
                      ]}
                    >
                      {item}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>
            </View>

            <View style={styles.half}>
              <Text style={styles.label}>Mode de vente</Text>
              <View style={styles.inlineChoiceWrap}>
                {[
                  { key: "PAIEMENT_DIRECT", label: "Paiement" },
                  { key: "POSSIBILITE_CONTACTE", label: "Discussion" },
                  { key: "LES_DEUX", label: "Les deux" },
                ].map((item) => (
                  <TouchableOpacity
                    key={item.key}
                    style={[
                      styles.inlineChoice,
                      modeVente === item.key && styles.inlineChoiceActive,
                    ]}
                    onPress={() => setModeVente(item.key as any)}
                  >
                    <Text
                      style={[
                        styles.inlineChoiceText,
                        modeVente === item.key && styles.inlineChoiceTextActive,
                      ]}
                    >
                      {item.label}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>
            </View>
          </View>

          <Text style={styles.label}>Ville (Maroc)</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chipsScroll}>
            <View style={styles.chipsWrap}>
              {MAROC_CITIES.map((city) => (
                <TouchableOpacity
                  key={city}
                  style={[styles.chip, ville === city && styles.chipActive]}
                  onPress={() => setVille(city)}
                >
                  <Text style={[styles.chipText, ville === city && styles.chipTextActive]}>
                    {city}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          </ScrollView>

          <View style={styles.switchRow}>
            <View style={{ flex: 1 }}>
              <Text style={styles.label}>Livraison</Text>
              <Text style={styles.mutedText}>
                Si désactivée : achat direct = main propre.
              </Text>
            </View>

            <Switch value={livraisonActive} onValueChange={setLivraisonActive} />
          </View>

          <View style={styles.row}>
            <View style={styles.half}>
              <Text style={styles.label}>Prix livraison même ville</Text>
              <TextInput
                style={styles.input}
                value={livraisonSame}
                onChangeText={(v) => setLivraisonSame(sanitizeDecimal(v))}
                keyboardType="decimal-pad"
                placeholder="15"
                placeholderTextColor="#9ca3af"
              />
            </View>

            <View style={styles.half}>
              <Text style={styles.label}>Prix livraison autre ville</Text>
              <TextInput
                style={styles.input}
                value={livraisonOther}
                onChangeText={(v) => setLivraisonOther(sanitizeDecimal(v))}
                keyboardType="decimal-pad"
                placeholder="40"
                placeholderTextColor="#9ca3af"
              />
            </View>
          </View>

          <Text style={styles.sectionTitle}>Localisation précise</Text>
          <TouchableOpacity style={styles.locationBtn} onPress={useCurrentLocation}>
            <Ionicons name="locate" size={18} color="#fff" />
            <Text style={styles.locationBtnText}>Choisir position actuelle</Text>
          </TouchableOpacity>

          <View style={styles.row}>
            <View style={styles.half}>
              <Text style={styles.label}>Latitude</Text>
              <TextInput
                style={styles.input}
                value={latitude}
                onChangeText={(v) => setLatitude(v.replace(",", "."))}
                keyboardType="decimal-pad"
                placeholder="Latitude"
                placeholderTextColor="#9ca3af"
              />
            </View>

            <View style={styles.half}>
              <Text style={styles.label}>Longitude</Text>
              <TextInput
                style={styles.input}
                value={longitude}
                onChangeText={(v) => setLongitude(v.replace(",", "."))}
                keyboardType="decimal-pad"
                placeholder="Longitude"
                placeholderTextColor="#9ca3af"
              />
            </View>
          </View>

          <Text style={styles.sectionTitle}>Image principale (cover)</Text>
          {coverImage ? (
            <View style={styles.imagePreviewBox}>
              <Image source={{ uri: coverImage.uri }} style={styles.coverPreview} />
              <TouchableOpacity style={styles.smallDarkBtn} onPress={pickCoverImage}>
                <Text style={styles.smallDarkBtnText}>Modifier</Text>
              </TouchableOpacity>
            </View>
          ) : (
            <View style={styles.imageEmptyBox}>
              <Text style={styles.imageEmptyText}>Aucune image principale</Text>
              <TouchableOpacity style={styles.smallDarkBtn} onPress={pickCoverImage}>
                <Text style={styles.smallDarkBtnText}>Choisir un fichier</Text>
              </TouchableOpacity>
            </View>
          )}
          <Text style={styles.helperText}>
            Obligatoire — JPG / PNG / WebP — max 5MB
          </Text>

          <Text style={styles.sectionTitle}>Photos secondaires</Text>
          {secondaryImages.length > 0 ? (
            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              <View style={styles.secondaryWrap}>
                {secondaryImages.map((img, index) => (
                  <View key={`${img.uri}-${index}`} style={styles.secondaryCard}>
                    <Image source={{ uri: img.uri }} style={styles.secondaryImage} />
                    <TouchableOpacity
                      style={styles.removeImageBtn}
                      onPress={() => removeSecondaryImage(index)}
                    >
                      <Text style={styles.removeImageBtnText}>Supprimer</Text>
                    </TouchableOpacity>
                  </View>
                ))}
              </View>
            </ScrollView>
          ) : (
            <Text style={styles.emptySecondaryText}>Aucune photo secondaire.</Text>
          )}

          <View style={styles.secondaryActions}>
            <TouchableOpacity style={styles.outlineActionBtn} onPress={addSecondaryImage}>
              <Feather name="plus-circle" size={16} color="#111827" />
              <Text style={styles.outlineActionBtnText}>Ajouter une photo</Text>
            </TouchableOpacity>
          </View>

          <Text style={styles.helperText}>
            Optionnel — jusqu’à 8 photos secondaires maximum
          </Text>

          <TouchableOpacity
            style={[styles.publishBtn, saving && styles.publishBtnDisabled]}
            onPress={submitAnnonce}
            disabled={saving}
          >
            <Ionicons name="cloud-upload-outline" size={18} color="#fff" />
            <Text style={styles.publishBtnText}>
              {saving ? "Publication..." : "Publier l’annonce"}
            </Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.backBtn} onPress={() => router.back()}>
            <Text style={styles.backBtnText}>Retour</Text>
          </TouchableOpacity>
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
    paddingBottom: 34,
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
  heroCard: {
    backgroundColor: "#1e3a8a",
    borderRadius: 22,
    padding: 20,
    marginBottom: 16,
  },
  heroTitle: {
    fontSize: 30,
    fontWeight: "900",
    color: "#fff",
    marginBottom: 10,
  },
  heroText: {
    color: "#dbeafe",
    fontSize: 15,
    lineHeight: 22,
  },
  badgesWrap: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 8,
    marginTop: 16,
  },
  softBadge: {
    backgroundColor: "rgba(255,255,255,0.16)",
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  softBadgeText: {
    color: "#fff",
    fontWeight: "700",
    fontSize: 12,
  },
  formCard: {
    backgroundColor: "#fff",
    borderRadius: 22,
    padding: 16,
  },
  formTop: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 10,
  },
  formTitle: {
    fontSize: 28,
    fontWeight: "900",
    color: "#111827",
  },
  uploadHint: {
    color: "#6b7280",
    fontSize: 13,
  },
  label: {
    marginTop: 12,
    marginBottom: 6,
    fontWeight: "800",
    fontSize: 15,
    color: "#111827",
  },
  sectionTitle: {
    marginTop: 18,
    marginBottom: 8,
    fontWeight: "900",
    fontSize: 18,
    color: "#111827",
  },
  input: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 14,
    backgroundColor: "#fff",
    paddingHorizontal: 14,
    paddingVertical: 13,
    fontSize: 16,
    color: "#111827",
  },
  textarea: {
    minHeight: 120,
    textAlignVertical: "top",
  },
  chipsScroll: {
    marginBottom: 2,
  },
  chipsWrap: {
    flexDirection: "row",
    gap: 8,
    paddingRight: 12,
  },
  chip: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    backgroundColor: "#fff",
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 10,
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
  chipTextActive: {
    color: "#fff",
  },
  row: {
    flexDirection: "row",
    gap: 10,
  },
  half: {
    flex: 1,
  },
  inlineChoiceWrap: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 8,
  },
  inlineChoice: {
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "#d1d5db",
    backgroundColor: "#fff",
  },
  inlineChoiceActive: {
    backgroundColor: "#111827",
    borderColor: "#111827",
  },
  inlineChoiceText: {
    color: "#374151",
    fontWeight: "700",
    fontSize: 13,
  },
  inlineChoiceTextActive: {
    color: "#fff",
  },
  switchRow: {
    marginTop: 8,
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
  },
  mutedText: {
    color: "#6b7280",
    fontSize: 13,
    lineHeight: 18,
  },
  locationBtn: {
    backgroundColor: "#2563eb",
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
    marginTop: 4,
    marginBottom: 8,
  },
  locationBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 15,
  },
  imagePreviewBox: {
    backgroundColor: "#f9fafb",
    borderRadius: 18,
    padding: 12,
    borderWidth: 1,
    borderColor: "#e5e7eb",
  },
  coverPreview: {
    width: "100%",
    height: 210,
    borderRadius: 14,
    backgroundColor: "#ddd",
    marginBottom: 10,
  },
  imageEmptyBox: {
    backgroundColor: "#f9fafb",
    borderRadius: 18,
    padding: 18,
    borderWidth: 1,
    borderColor: "#e5e7eb",
    alignItems: "center",
  },
  imageEmptyText: {
    color: "#6b7280",
    fontSize: 14,
    fontWeight: "700",
    marginBottom: 12,
  },
  smallDarkBtn: {
    alignSelf: "flex-start",
    backgroundColor: "#111827",
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 12,
  },
  smallDarkBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 14,
  },
  helperText: {
    marginTop: 8,
    color: "#6b7280",
    fontSize: 13,
  },
  secondaryWrap: {
    flexDirection: "row",
    gap: 10,
  },
  secondaryCard: {
    width: 130,
    backgroundColor: "#f9fafb",
    borderRadius: 16,
    padding: 8,
    borderWidth: 1,
    borderColor: "#e5e7eb",
  },
  secondaryImage: {
    width: "100%",
    height: 95,
    borderRadius: 12,
    backgroundColor: "#ddd",
    marginBottom: 8,
  },
  removeImageBtn: {
    backgroundColor: "#fee2e2",
    borderRadius: 10,
    paddingVertical: 8,
    alignItems: "center",
  },
  removeImageBtnText: {
    color: "#b91c1c",
    fontWeight: "800",
    fontSize: 12,
  },
  emptySecondaryText: {
    color: "#6b7280",
    fontSize: 14,
  },
  secondaryActions: {
    marginTop: 10,
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
  },
  outlineActionBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 12,
    backgroundColor: "#fff",
  },
  outlineActionBtnText: {
    color: "#111827",
    fontWeight: "700",
    fontSize: 14,
  },
  publishBtn: {
    marginTop: 22,
    backgroundColor: "#111827",
    borderRadius: 14,
    paddingVertical: 15,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
  },
  publishBtnDisabled: {
    opacity: 0.7,
  },
  publishBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 16,
  },
  backBtn: {
    marginTop: 10,
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 14,
    paddingVertical: 15,
    alignItems: "center",
    backgroundColor: "#fff",
  },
  backBtnText: {
    color: "#374151",
    fontWeight: "800",
    fontSize: 16,
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