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
} from "react-native";
import { Stack, router, useLocalSearchParams, useFocusEffect } from "expo-router";
import { getUser } from "../../utils/auth";
import * as Location from "expo-location";
import * as ImagePicker from "expo-image-picker";
import { Feather, Ionicons } from "@expo/vector-icons";

const API_BASE = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/api";

type ExistingImageType = {
  id_image: number;
  image_url: string;
};

type NewImageType = {
  uri: string;
  name: string;
  mimeType: string;
};

type FormType = {
  id_annonce: number;
  titre: string;
  description: string;
  prix: string;
  stock: string;
  ville: string;
  categorie: string;
  marque: string;
  latitude: string;
  longitude: string;
  cover_image_url?: string | null;
  statut?: string;
  existing_images: ExistingImageType[];
};

const CATEGORIES: Record<string, string[]> = {
  VOITURE: [
    "TOYOTA","VOLKSWAGEN","BMW","MERCEDES-BENZ","AUDI","HYUNDAI","KIA","TESLA",
    "FORD","RENAULT","PEUGEOT","HONDA","NISSAN","PORSCHE","VOLVO","MAZDA","SUZUKI","AUTRE"
  ],
  MOTO: [
    "HONDA","YAMAHA","KAWASAKI","SUZUKI","BMW","KTM","DUCATI","TRIUMPH",
    "HARLEY-DAVIDSON","INDIAN","ROYAL ENFIELD","APRILIA","MOTO GUZZI",
    "HUSQVARNA","GASGAS","CFMOTO","BENELLI","AUTRE"
  ],
  TELEPHONE: [
    "APPLE","SAMSUNG","XIAOMI","HUAWEI","GOOGLE","OPPO","VIVO","HONOR","REALME",
    "MOTOROLA","SONY","ASUS","NOKIA","ONEPLUS","NOTHING","TECNO","INFINIX","AUTRE"
  ],
  INFORMATIQUE: [
    "APPLE","LENOVO","HP","DELL","ASUS","ACER","MSI","SAMSUNG","MICROSOFT",
    "RAZER","NVIDIA","INTEL","AMD","GIGABYTE","CORSAIR","LOGITECH","HUAWEI","AUTRE"
  ],
  TV_AUDIO: [
    "SAMSUNG","LG","SONY","PANASONIC","TCL","HISENSE","PHILIPS","BOZE","SONOS",
    "JBL","MARSHALL","BANG & OLUFSEN","DENON","SENNHEISER","BEATS","YAMAHA","APPLE","AUTRE"
  ],
  ELECTROMENAGER: [
    "MIELE","BOSCH","SIEMENS","SAMSUNG","LG","WHIRLPOOL","ELECTROLUX","BEKO",
    "HAIER","DYSON","MOULINEX","ROWENTA","TEFAL","SMEG","DE DIETRICH","LIEBHERR","SHARP","AUTRE"
  ],
  MODE: [
    "ZARA","H&M","MANGO","BERSHKA","PULL&BEAR","STRADIVARIUS","MASSIMO DUTTI",
    "UNIQLO","GAP","LEVI'S","GUESS","CALVIN KLEIN","TOMMY HILFIGER",
    "RALPH LAUREN","LACOSTE","ASOS","SHEIN","AUTRE"
  ],
  MAISON: [
    "IKEA","MAISONS DU MONDE","ZARA HOME","H&M HOME","LEROY MERLIN",
    "CASTORAMA","BUT","CONFORAMA","WESTELM","POTTERY BARN","AUTRE"
  ],
  SPORT: [
    "NIKE","ADIDAS","PUMA","UNDER ARMOUR","NEW BALANCE","ASICS","LULULEMON",
    "JORDAN","SKECHERS","REEBOK","CONVERSE","THE NORTH FACE","COLUMBIA",
    "FILA","MIZUNO","SALOMON","UMBRO","AUTRE"
  ],
  JEUX: [
    "SONY","PLAYSTATION","MICROSOFT","NINTENDO","UBISOFT","ELECTRONIC ARTS",
    "ROCKSTAR GAMES","ACTIVISION","BLIZZARD","EPIC GAMES","KONAMI","AUTRE"
  ],
  AUTRE: ["AUTRE"],
};

const MAROC_CITIES = [
  "Agadir","Al Hoceima","Asilah","Azrou","Beni Mellal","Berkane","Boujdour",
  "Casablanca","Chefchaouen","Dakhla","El Jadida","Errachidia","Essaouira",
  "Fès","Guelmim","Ifrane","Kenitra","Khemisset","Khouribga","Laâyoune",
  "Larache","Marrakech","Meknès","Mohammedia","Nador","Ouarzazate",
  "Oujda","Rabat","Safi","Salé","Settat","Sidi Ifni","Tanger","Tarfaya",
  "Taza","Tétouan"
];

export default function AnnonceEditScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [errorMsg, setErrorMsg] = useState("");
  const [user, setUser] = useState<any>(null);

  const [form, setForm] = useState<FormType>({
    id_annonce: 0,
    titre: "",
    description: "",
    prix: "",
    stock: "",
    ville: "Marrakech",
    categorie: "AUTRE",
    marque: "AUTRE",
    latitude: "",
    longitude: "",
    cover_image_url: null,
    statut: "",
    existing_images: [],
  });

  const [newCover, setNewCover] = useState<NewImageType | null>(null);
  const [newImages, setNewImages] = useState<NewImageType[]>([]);
  const [deleteImageIds, setDeleteImageIds] = useState<number[]>([]);

  const brandOptions = useMemo(() => {
    return CATEGORIES[form.categorie] || ["AUTRE"];
  }, [form.categorie]);

  async function loadAnnonce() {
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
        `${API_BASE}/annonce_edit_mobile_details.php?user_id=${Number(currentUser.id_user)}&id=${Number(id)}`,
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
      } catch {
        setErrorMsg(`Réponse non JSON: ${rawText.substring(0, 180)}`);
        return;
      }

      if (!data.ok) {
        setErrorMsg(data.message || "Erreur chargement annonce");
        return;
      }

      const a = data.annonce;

      setForm({
        id_annonce: Number(a.id_annonce || 0),
        titre: String(a.titre || ""),
        description: String(a.description || ""),
        prix: String(a.prix ?? ""),
        stock: String(a.stock ?? ""),
        ville: String(a.ville || "Marrakech"),
        categorie: String(a.categorie || "AUTRE"),
        marque: String(a.marque || "AUTRE"),
        latitude: a.latitude !== null && a.latitude !== undefined ? String(a.latitude) : "",
        longitude: a.longitude !== null && a.longitude !== undefined ? String(a.longitude) : "",
        cover_image_url: a.cover_image_url || null,
        statut: String(a.statut || ""),
        existing_images: Array.isArray(a.existing_images) ? a.existing_images : [],
      });

      setNewCover(null);
      setNewImages([]);
      setDeleteImageIds([]);
    } catch (e: any) {
      setErrorMsg(String(e));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (id) loadAnnonce();
  }, [id]);

  useFocusEffect(
    React.useCallback(() => {
      if (id) loadAnnonce();
    }, [id])
  );

  function updateField(key: keyof FormType, value: string) {
    setForm((prev) => {
      const next = { ...prev, [key]: value };

      if (key === "categorie") {
        const nextBrands = CATEGORIES[value] || ["AUTRE"];
        next.marque = nextBrands.includes(prev.marque) ? prev.marque : nextBrands[0];
      }

      return next;
    });
  }

  async function askImagePermission() {
    const result = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!result.granted) {
      Alert.alert("Permission refusée", "Autorise l’accès aux photos.");
      return false;
    }
    return true;
  }

  async function pickCoverImage() {
    const ok = await askImagePermission();
    if (!ok) return;

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ["images"],
      allowsEditing: true,
      quality: 0.85,
    });

    if (result.canceled || !result.assets?.length) return;

    const asset = result.assets[0];
    const uri = asset.uri;
    const fileName = asset.fileName || `cover_${Date.now()}.jpg`;
    const mimeType = asset.mimeType || "image/jpeg";

    setNewCover({
      uri,
      name: fileName,
      mimeType,
    });
  }

  async function pickSecondaryImages() {
    const ok = await askImagePermission();
    if (!ok) return;

    const alreadyCount =
      form.existing_images.filter((img) => !deleteImageIds.includes(img.id_image)).length +
      newImages.length;

    const remaining = 8 - alreadyCount;

    if (remaining <= 0) {
      Alert.alert("Limite atteinte", "Maximum 8 photos secondaires.");
      return;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ["images"],
      allowsMultipleSelection: true,
      selectionLimit: remaining,
      quality: 0.85,
    });

    if (result.canceled || !result.assets?.length) return;

    const picked: NewImageType[] = result.assets.map((asset, index) => ({
      uri: asset.uri,
      name: asset.fileName || `photo_${Date.now()}_${index}.jpg`,
      mimeType: asset.mimeType || "image/jpeg",
    }));

    setNewImages((prev) => [...prev, ...picked].slice(0, remaining + prev.length));
  }

  function toggleDeleteExistingImage(idImage: number) {
    setDeleteImageIds((prev) =>
      prev.includes(idImage) ? prev.filter((x) => x !== idImage) : [...prev, idImage]
    );
  }

  function removeNewImage(index: number) {
    setNewImages((prev) => prev.filter((_, i) => i !== index));
  }

  async function useCurrentLocation() {
    try {
      const permission = await Location.requestForegroundPermissionsAsync();
      if (!permission.granted) {
        Alert.alert("Permission refusée", "Autorise la localisation.");
        return;
      }

      const position = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.High,
      });

      const lat = position.coords.latitude;
      const lng = position.coords.longitude;

      setForm((prev) => ({
        ...prev,
        latitude: String(lat),
        longitude: String(lng),
      }));

      Alert.alert("Succès", "Position actuelle récupérée.");
    } catch (e: any) {
      Alert.alert("Erreur", "Impossible de récupérer la position.");
    }
  }

  async function saveAnnonce() {
    if (!user?.id_user) {
      Alert.alert("Erreur", "Connexion requise.");
      return;
    }

    if (!form.titre.trim() || form.titre.trim().length < 3) {
      Alert.alert("Erreur", "Titre invalide.");
      return;
    }

    if (!form.description.trim() || form.description.trim().length < 10) {
      Alert.alert("Erreur", "Description trop courte.");
      return;
    }

    if (form.prix.trim() === "" || isNaN(Number(form.prix)) || Number(form.prix) < 0) {
      Alert.alert("Erreur", "Prix invalide.");
      return;
    }

    if (form.stock.trim() === "" || isNaN(Number(form.stock)) || Number(form.stock) < 0) {
      Alert.alert("Erreur", "Stock invalide.");
      return;
    }

    if (!MAROC_CITIES.includes(form.ville)) {
      Alert.alert("Erreur", "Ville invalide.");
      return;
    }

    if (!CATEGORIES[form.categorie]) {
      Alert.alert("Erreur", "Catégorie invalide.");
      return;
    }

    if (!brandOptions.includes(form.marque)) {
      Alert.alert("Erreur", "Marque invalide.");
      return;
    }

    if (!form.latitude.trim() || !form.longitude.trim()) {
      Alert.alert("Erreur", "Choisis la localisation ou utilise ta position actuelle.");
      return;
    }

    if (isNaN(Number(form.latitude)) || isNaN(Number(form.longitude))) {
      Alert.alert("Erreur", "Coordonnées GPS invalides.");
      return;
    }

    try {
      setSaving(true);

      const fd = new FormData();
      fd.append("user_id", String(Number(user.id_user)));
      fd.append("id_annonce", String(Number(form.id_annonce)));
      fd.append("titre", form.titre.trim());
      fd.append("description", form.description.trim());
      fd.append("prix", String(Number(form.prix)));
      fd.append("stock", String(Number(form.stock)));
      fd.append("ville", form.ville);
      fd.append("categorie", form.categorie);
      fd.append("marque", form.marque);
      fd.append("latitude", String(Number(form.latitude)));
      fd.append("longitude", String(Number(form.longitude)));

      deleteImageIds.forEach((imgId) => {
        fd.append("delete_images[]", String(imgId));
      });

      if (newCover) {
        fd.append("cover_image", {
          uri: newCover.uri,
          name: newCover.name,
          type: newCover.mimeType,
        } as any);
      }

      newImages.forEach((img, index) => {
        fd.append("images[]", {
          uri: img.uri,
          name: img.name || `image_${index}.jpg`,
          type: img.mimeType || "image/jpeg",
        } as any);
      });

      const res = await fetch(`${API_BASE}/annonce_edit_mobile_save.php`, {
        method: "POST",
        headers: {
          Accept: "application/json",
        },
        body: fd,
      });

      const rawText = await res.text();

      if (!rawText || rawText.trim() === "") {
        Alert.alert("Erreur", "Réponse vide du serveur.");
        return;
      }

      let data: any = null;

      try {
        data = JSON.parse(rawText);
      } catch {
        Alert.alert("Erreur", `Réponse non JSON: ${rawText.substring(0, 180)}`);
        return;
      }

      if (!data.ok) {
        Alert.alert("Erreur", data.message || "Modification impossible.");
        return;
      }

      Alert.alert(
        "Succès",
        data.message || "Annonce modifiée avec succès.",
        [
          {
            text: "OK",
            onPress: () => router.replace("/mes-annonces"),
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
        <Stack.Screen options={{ title: "Modifier annonce" }} />
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
        <Stack.Screen options={{ title: "Modifier annonce" }} />
        <View style={styles.center}>
          <Text style={styles.errorTitle}>Connexion requise</Text>
          <Text style={styles.errorText}>Tu dois te connecter.</Text>

          <TouchableOpacity style={styles.primaryBtn} onPress={() => router.push("/login")}>
            <Text style={styles.primaryBtnText}>Connexion</Text>
          </TouchableOpacity>
        </View>
      </>
    );
  }

  if (errorMsg) {
    return (
      <>
        <Stack.Screen options={{ title: "Modifier annonce" }} />
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
      <Stack.Screen options={{ title: "Modifier annonce" }} />

      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <Text style={styles.pageTitle}>Modifier annonce</Text>

        <Text style={styles.label}>Photo principale</Text>
        {newCover ? (
          <Image source={{ uri: newCover.uri }} style={styles.coverImage} />
        ) : form.cover_image_url ? (
          <Image source={{ uri: form.cover_image_url }} style={styles.coverImage} />
        ) : (
          <View style={styles.noCover}>
            <Text style={styles.noCoverText}>Aucune photo principale</Text>
          </View>
        )}

        <View style={styles.rowBtns}>
          <TouchableOpacity style={styles.smallBtnDark} onPress={pickCoverImage}>
            <Ionicons name="image-outline" size={16} color="#fff" />
            <Text style={styles.smallBtnDarkText}>Changer cover</Text>
          </TouchableOpacity>

          {newCover ? (
            <TouchableOpacity
              style={styles.smallBtnLight}
              onPress={() => setNewCover(null)}
            >
              <Ionicons name="trash-outline" size={16} color="#111827" />
              <Text style={styles.smallBtnLightText}>Annuler cover</Text>
            </TouchableOpacity>
          ) : null}
        </View>

        <Text style={styles.label}>Photos secondaires</Text>
        <View style={styles.imagesWrap}>
          {form.existing_images
            .filter((img) => !deleteImageIds.includes(img.id_image))
            .map((img) => (
              <View key={`existing-${img.id_image}`} style={styles.thumbCard}>
                <Image source={{ uri: img.image_url }} style={styles.thumb} />
                <TouchableOpacity
                  style={styles.thumbDeleteBtn}
                  onPress={() => toggleDeleteExistingImage(img.id_image)}
                >
                  <Text style={styles.thumbDeleteText}>Supprimer</Text>
                </TouchableOpacity>
              </View>
            ))}

          {newImages.map((img, index) => (
            <View key={`new-${index}`} style={styles.thumbCard}>
              <Image source={{ uri: img.uri }} style={styles.thumb} />
              <TouchableOpacity
                style={styles.thumbDeleteBtn}
                onPress={() => removeNewImage(index)}
              >
                <Text style={styles.thumbDeleteText}>Retirer</Text>
              </TouchableOpacity>
            </View>
          ))}
        </View>

        <TouchableOpacity style={styles.smallBtnDark} onPress={pickSecondaryImages}>
          <Ionicons name="images-outline" size={16} color="#fff" />
          <Text style={styles.smallBtnDarkText}>Ajouter des photos</Text>
        </TouchableOpacity>

        <View style={styles.infoBox}>
          <Text style={styles.infoText}>Statut actuel : {form.statut || "INCONNU"}</Text>
          <Text style={styles.infoText}>
            Après modification, l’annonce repasse en EN_ATTENTE_VALIDATION.
          </Text>
        </View>

        <Text style={styles.label}>Titre</Text>
        <TextInput
          style={styles.input}
          value={form.titre}
          onChangeText={(v) => updateField("titre", v)}
          placeholder="Titre"
        />

        <Text style={styles.label}>Description</Text>
        <TextInput
          style={[styles.input, styles.textarea]}
          value={form.description}
          onChangeText={(v) => updateField("description", v)}
          placeholder="Description"
          multiline
        />

        <Text style={styles.label}>Prix</Text>
        <TextInput
          style={styles.input}
          value={form.prix}
          onChangeText={(v) => updateField("prix", v.replace(",", "."))}
          keyboardType="decimal-pad"
          placeholder="Prix"
        />

        <Text style={styles.label}>Stock</Text>
        <TextInput
          style={styles.input}
          value={form.stock}
          onChangeText={(v) => updateField("stock", v.replace(/[^0-9]/g, ""))}
          keyboardType="numeric"
          placeholder="Stock"
        />

        <Text style={styles.label}>Ville</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chipsScroll}>
          <View style={styles.chipsWrap}>
            {MAROC_CITIES.map((city) => (
              <TouchableOpacity
                key={city}
                style={[styles.chip, form.ville === city && styles.chipActive]}
                onPress={() => updateField("ville", city)}
              >
                <Text style={[styles.chipText, form.ville === city && styles.chipTextActive]}>
                  {city}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
        </ScrollView>

        <Text style={styles.label}>Catégorie</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chipsScroll}>
          <View style={styles.chipsWrap}>
            {Object.keys(CATEGORIES).map((cat) => (
              <TouchableOpacity
                key={cat}
                style={[styles.chip, form.categorie === cat && styles.chipActive]}
                onPress={() => updateField("categorie", cat)}
              >
                <Text style={[styles.chipText, form.categorie === cat && styles.chipTextActive]}>
                  {cat}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
        </ScrollView>

        <Text style={styles.label}>Marque</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chipsScroll}>
          <View style={styles.chipsWrap}>
            {brandOptions.map((brand) => (
              <TouchableOpacity
                key={brand}
                style={[styles.chip, form.marque === brand && styles.chipActive]}
                onPress={() => updateField("marque", brand)}
              >
                <Text style={[styles.chipText, form.marque === brand && styles.chipTextActive]}>
                  {brand}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
        </ScrollView>

        <Text style={styles.label}>Latitude</Text>
        <TextInput
          style={styles.input}
          value={form.latitude}
          onChangeText={(v) => updateField("latitude", v.replace(",", "."))}
          keyboardType="decimal-pad"
          placeholder="Latitude"
        />

        <Text style={styles.label}>Longitude</Text>
        <TextInput
          style={styles.input}
          value={form.longitude}
          onChangeText={(v) => updateField("longitude", v.replace(",", "."))}
          keyboardType="decimal-pad"
          placeholder="Longitude"
        />

        <TouchableOpacity style={styles.mapBtn} onPress={useCurrentLocation}>
          <Ionicons name="locate-outline" size={18} color="#fff" />
          <Text style={styles.mapBtnText}>Utiliser ma position actuelle</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.saveBtn, saving && styles.saveBtnDisabled]}
          onPress={saveAnnonce}
          disabled={saving}
        >
          <Text style={styles.saveBtnText}>
            {saving ? "Enregistrement..." : "Enregistrer"}
          </Text>
        </TouchableOpacity>

        <TouchableOpacity style={styles.cancelBtn} onPress={() => router.back()}>
          <Text style={styles.cancelBtnText}>Annuler</Text>
        </TouchableOpacity>
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
    padding: 16,
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
  pageTitle: {
    fontSize: 28,
    fontWeight: "900",
    color: "#111827",
    marginBottom: 16,
  },
  coverImage: {
    width: "100%",
    height: 220,
    borderRadius: 18,
    backgroundColor: "#ddd",
    marginBottom: 12,
  },
  noCover: {
    width: "100%",
    height: 140,
    borderRadius: 18,
    backgroundColor: "#e5e7eb",
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 12,
  },
  noCoverText: {
    color: "#6b7280",
    fontSize: 14,
    fontWeight: "700",
  },
  rowBtns: {
    flexDirection: "row",
    gap: 10,
    flexWrap: "wrap",
    marginBottom: 12,
  },
  smallBtnDark: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    backgroundColor: "#111827",
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 12,
    alignSelf: "flex-start",
    marginBottom: 10,
  },
  smallBtnDarkText: {
    color: "#fff",
    fontWeight: "800",
  },
  smallBtnLight: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    borderColor: "#d1d5db",
    backgroundColor: "#fff",
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 12,
    alignSelf: "flex-start",
  },
  smallBtnLightText: {
    color: "#111827",
    fontWeight: "800",
  },
  imagesWrap: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
    marginBottom: 12,
  },
  thumbCard: {
    width: 105,
  },
  thumb: {
    width: 105,
    height: 85,
    borderRadius: 12,
    backgroundColor: "#ddd",
    marginBottom: 6,
  },
  thumbDeleteBtn: {
    borderWidth: 1,
    borderColor: "#fecaca",
    backgroundColor: "#fff",
    borderRadius: 10,
    paddingVertical: 8,
    alignItems: "center",
  },
  thumbDeleteText: {
    color: "#dc2626",
    fontWeight: "800",
    fontSize: 12,
  },
  infoBox: {
    backgroundColor: "#e0ecff",
    borderRadius: 14,
    padding: 12,
    marginBottom: 16,
  },
  infoText: {
    color: "#1e3a8a",
    fontSize: 14,
    fontWeight: "600",
    marginBottom: 4,
  },
  label: {
    marginTop: 10,
    marginBottom: 6,
    fontWeight: "800",
    fontSize: 15,
    color: "#111827",
  },
  input: {
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 12,
    backgroundColor: "#fff",
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 16,
    color: "#111827",
  },
  textarea: {
    minHeight: 120,
    textAlignVertical: "top",
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
  mapBtn: {
    marginTop: 16,
    backgroundColor: "#2563eb",
    borderRadius: 14,
    paddingVertical: 15,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
  },
  mapBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 16,
  },
  saveBtn: {
    marginTop: 14,
    backgroundColor: "#111827",
    borderRadius: 14,
    paddingVertical: 15,
    alignItems: "center",
  },
  saveBtnDisabled: {
    opacity: 0.7,
  },
  saveBtnText: {
    color: "#fff",
    fontWeight: "800",
    fontSize: 16,
  },
  cancelBtn: {
    marginTop: 10,
    borderWidth: 1,
    borderColor: "#d1d5db",
    borderRadius: 14,
    paddingVertical: 15,
    alignItems: "center",
    backgroundColor: "#fff",
  },
  cancelBtnText: {
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