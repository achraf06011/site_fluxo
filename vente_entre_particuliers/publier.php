<?php
require_once "config/db.php";
require_once "config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
$old     = $_SESSION["flash_old"] ?? [];

unset($_SESSION["flash_success"], $_SESSION["flash_error"], $_SESSION["flash_old"]);

$MAROC_CITIES = [
  "Agadir","Al Hoceima","Asilah","Azrou","Beni Mellal","Berkane","Boujdour",
  "Casablanca","Chefchaouen","Dakhla","El Jadida","Errachidia","Essaouira",
  "Fès","Guelmim","Ifrane","Kenitra","Khemisset","Khouribga","Laâyoune",
  "Larache","Marrakech","Meknès","Mohammedia","Nador","Ouarzazate",
  "Oujda","Rabat","Safi","Salé","Settat","Sidi Ifni","Tanger","Tarfaya",
  "Taza","Tétouan"
];

$CATEGORIES = [
  "VOITURE" => ["TOYOTA","VOLKSWAGEN","BMW","MERCEDES-BENZ","AUDI","HYUNDAI","KIA","TESLA","FORD","RENAULT","PEUGEOT","HONDA","NISSAN","PORSCHE","VOLVO","MAZDA","SUZUKI","AUTRE"],
  "MOTO" => ["HONDA","YAMAHA","KAWASAKI","SUZUKI","BMW","KTM","DUCATI","TRIUMPH","HARLEY-DAVIDSON","INDIAN","ROYAL ENFIELD","APRILIA","MOTO GUZZI","HUSQVARNA","GASGAS","CFMOTO","BENELLI","AUTRE"],
  "TELEPHONE" => ["APPLE","SAMSUNG","XIAOMI","HUAWEI","GOOGLE","OPPO","VIVO","HONOR","REALME","MOTOROLA","SONY","ASUS","NOKIA","ONEPLUS","NOTHING","TECNO","INFINIX","AUTRE"],
  "INFORMATIQUE" => ["APPLE","LENOVO","HP","DELL","ASUS","ACER","MSI","SAMSUNG","MICROSOFT","RAZER","NVIDIA","INTEL","AMD","GIGABYTE","CORSAIR","LOGITECH","HUAWEI","AUTRE"],
  "TV_AUDIO" => ["SAMSUNG","LG","SONY","PANASONIC","TCL","HISENSE","PHILIPS","BOZE","SONOS","JBL","MARSHALL","BANG & OLUFSEN","DENON","SENNHEISER","BEATS","YAMAHA","APPLE","AUTRE"],
  "ELECTROMENAGER" => ["MIELE","BOSCH","SIEMENS","SAMSUNG","LG","WHIRLPOOL","ELECTROLUX","BEKO","HAIER","DYSON","MOULINEX","ROWENTA","TEFAL","SMEG","DE DIETRICH","LIEBHERR","SHARP","AUTRE"],
  "MODE" => ["ZARA","H&M","MANGO","BERSHKA","PULL&BEAR","STRADIVARIUS","MASSIMO DUTTI","UNIQLO","GAP","LEVI'S","GUESS","CALVIN KLEIN","TOMMY HILFIGER","RALPH LAUREN","LACOSTE","ASOS","SHEIN","AUTRE"],
  "MAISON" => ["IKEA","MAISONS DU MONDE","ZARA HOME","H&M HOME","LEROY MERLIN","CASTORAMA","BUT","CONFORAMA","WESTELM","POTTERY BARN"],
  "SPORT" => ["NIKE","ADIDAS","PUMA","UNDER ARMOUR","NEW BALANCE","ASICS","LULULEMON","JORDAN","SKECHERS","REEBOK","CONVERSE","THE NORTH FACE","COLUMBIA","FILA","MIZUNO","SALOMON","UMBRO","AUTRE"],
  "JEUX" => ["SONY","PLAYSTATION","MICROSOFT","NINTENDO","UBISOFT","ELECTRONIC ARTS","ROCKSTAR GAMES","ACTIVISION","BLIZZARD","EPIC GAMES","KONAMI","AUTRE"],
  "AUTRE" => ["AUTRE"]
];

$oldVille = $old["ville"] ?? "Marrakech";
$oldLivOn = !empty($old["livraison_active"]);
$oldSame  = $old["livraison_prix_same_city"] ?? "15";
$oldOther = $old["livraison_prix_other_city"] ?? "40";
$oldCategorie = $old["categorie"] ?? "TELEPHONE";
$oldMarque = $old["marque"] ?? "";
$oldLatitude = $old["latitude"] ?? "";
$oldLongitude = $old["longitude"] ?? "";

function e($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<link
  rel="stylesheet"
  href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
/>

<style>
#map-picker {
  height: 320px;
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid #dee2e6;
}
</style>

<div class="container my-5" style="max-width: 980px;">
  <div class="row g-4 align-items-stretch">
    <div class="col-12 col-lg-5">
      <div class="p-4 hero h-100">
        <h2 class="fw-bold mb-2">Publier une annonce</h2>
        <p class="mb-0 opacity-75">
          Ajoute ton produit, choisis sa catégorie, sa marque, le prix, le stock et le mode de vente.
        </p>

        <div class="mt-3 d-flex flex-wrap gap-2">
          <span class="badge badge-soft">Catégorie</span>
          <span class="badge badge-soft">Marque</span>
          <span class="badge badge-soft">Ville Maroc</span>
          <span class="badge badge-soft">Localisation carte</span>
          <span class="badge badge-soft">Livraison</span>
          <span class="badge badge-soft">Photo principale obligatoire</span>
          <span class="badge badge-soft">Jusqu’à 8 photos secondaires</span>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-7">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body p-4 p-md-5">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0 fw-bold">Nouvelle annonce</h3>
            <span class="text-muted small"><i class="bi bi-upload"></i> upload</span>
          </div>

          <?php if ($success): ?>
            <div class="alert alert-success"><?php echo e($success); ?></div>
          <?php endif; ?>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
          <?php endif; ?>

          <form action="actions/publier_action.php" method="POST" enctype="multipart/form-data" novalidate>

            <div class="mb-3">
              <label class="form-label">Titre</label>
              <input
                type="text"
                name="titre"
                class="form-control"
                maxlength="150"
                value="<?php echo e($old['titre'] ?? ''); ?>"
                placeholder="Ex: iPhone 13 128GB"
                required
              >
            </div>

            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea
                name="description"
                class="form-control"
                rows="4"
                required
                placeholder="Décris le produit..."
              ><?php echo e($old['description'] ?? ''); ?></textarea>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Catégorie</label>
                <select name="categorie" id="categorie" class="form-select" required>
                  <?php foreach ($CATEGORIES as $cat => $brands): ?>
                    <option value="<?php echo e($cat); ?>" <?php echo $oldCategorie === $cat ? "selected" : ""; ?>>
                      <?php echo e($cat); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Marque</label>
                <select name="marque" id="marque" class="form-select" required></select>
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Prix (DH)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  name="prix"
                  class="form-control"
                  value="<?php echo e($old['prix'] ?? ''); ?>"
                  required
                >
              </div>

              <div class="col-md-6">
                <label class="form-label">Stock</label>
                <input
                  type="number"
                  min="0"
                  name="stock"
                  class="form-control"
                  value="<?php echo e($old['stock'] ?? '1'); ?>"
                  required
                >
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Type</label>
                <?php $t = $old['type'] ?? 'NEUF'; ?>
                <select name="type" class="form-select" required>
                  <option value="NEUF" <?php echo $t==='NEUF'?'selected':''; ?>>NEUF</option>
                  <option value="OCCASION" <?php echo $t==='OCCASION'?'selected':''; ?>>OCCASION</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Mode de vente</label>
                <?php $m = $old['mode_vente'] ?? 'LES_DEUX'; ?>
                <select name="mode_vente" class="form-select" required>
                  <option value="PAIEMENT_DIRECT" <?php echo $m==='PAIEMENT_DIRECT'?'selected':''; ?>>
                    Paiement direct
                  </option>
                  <option value="POSSIBILITE_CONTACTE" <?php echo $m==='POSSIBILITE_CONTACTE'?'selected':''; ?>>
                    Discussion uniquement
                  </option>
                  <option value="LES_DEUX" <?php echo $m==='LES_DEUX'?'selected':''; ?>>
                    Paiement + Discussion
                  </option>
                </select>
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Ville (Maroc)</label>
                <select name="ville" id="ville" class="form-select" required>
                  <?php foreach ($MAROC_CITIES as $c): ?>
                    <option value="<?php echo e($c); ?>" <?php echo ($oldVille === $c) ? "selected" : ""; ?>>
                      <?php echo e($c); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Livraison</label>
                <div class="form-check mt-2">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    id="livraison_active"
                    name="livraison_active"
                    value="1"
                    <?php echo $oldLivOn ? "checked" : ""; ?>
                  >
                  <label class="form-check-label" for="livraison_active">
                    Activer la livraison
                  </label>
                </div>
                <div class="small text-muted">Si désactivé : achat direct = main propre.</div>
              </div>

              <div class="col-md-6">
                <label class="form-label">Prix livraison (même ville)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  name="livraison_prix_same_city"
                  class="form-control"
                  value="<?php echo e($oldSame); ?>"
                >
              </div>

              <div class="col-md-6">
                <label class="form-label">Prix livraison (autre ville)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  name="livraison_prix_other_city"
                  class="form-control"
                  value="<?php echo e($oldOther); ?>"
                >
              </div>
            </div>

            <div class="mt-4">
              <label class="form-label fw-semibold">Localisation précise</label>
              <div id="map-picker"></div>
              <div class="form-text">
                Clique sur la carte pour choisir l’emplacement exact de l’annonce.
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Latitude</label>
                <input
                  type="text"
                  name="latitude"
                  id="latitude"
                  class="form-control"
                  value="<?php echo e($oldLatitude); ?>"
                  readonly
                >
              </div>

              <div class="col-md-6">
                <label class="form-label">Longitude</label>
                <input
                  type="text"
                  name="longitude"
                  id="longitude"
                  class="form-control"
                  value="<?php echo e($oldLongitude); ?>"
                  readonly
                >
              </div>
            </div>

            <hr class="my-4">

            <div class="mb-3">
              <label class="form-label fw-semibold">Image principale (cover)</label>
              <input
                type="file"
                name="cover_image"
                class="form-control"
                accept="image/*"
                required
              >
              <div class="form-text">Obligatoire — JPG / PNG / WebP — max 5MB.</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Photos secondaires</label>

              <div id="secondary-images-wrapper" class="d-flex flex-column gap-2">
                <div class="secondary-image-item">
                  <input type="file" name="images[]" class="form-control" accept="image/*">
                </div>
              </div>

              <div class="d-flex gap-2 mt-3 flex-wrap">
                <button type="button" class="btn btn-outline-dark btn-sm" id="add-image-btn">
                  <i class="bi bi-plus-circle"></i> Ajouter une photo
                </button>

                <button type="button" class="btn btn-outline-secondary btn-sm" id="remove-image-btn">
                  <i class="bi bi-dash-circle"></i> Retirer la dernière
                </button>
              </div>

              <div class="form-text">
                Optionnel — jusqu’à 8 photos secondaires maximum.
              </div>
            </div>

            <div class="d-grid gap-2 mt-4">
              <button class="btn btn-dark btn-lg" type="submit">
                <i class="bi bi-cloud-arrow-up"></i> Publier l’annonce
              </button>
              <a class="btn btn-outline-secondary" href="index.php">Retour</a>
            </div>

          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const brandsByCategory = <?php echo json_encode($CATEGORIES, JSON_UNESCAPED_UNICODE); ?>;
  const oldBrand = <?php echo json_encode($oldMarque, JSON_UNESCAPED_UNICODE); ?>;

  const categorieEl = document.getElementById("categorie");
  const marqueEl = document.getElementById("marque");

  function loadBrands(selectedBrand = "") {
    const cat = categorieEl.value;
    const brands = brandsByCategory[cat] || ["AUTRE"];

    marqueEl.innerHTML = "";
    brands.forEach(function (brand) {
      const opt = document.createElement("option");
      opt.value = brand;
      opt.textContent = brand;
      if (selectedBrand && selectedBrand === brand) {
        opt.selected = true;
      }
      marqueEl.appendChild(opt);
    });
  }

  loadBrands(oldBrand);
  categorieEl.addEventListener("change", function () {
    loadBrands("");
  });

  const wrapper = document.getElementById("secondary-images-wrapper");
  const addBtn = document.getElementById("add-image-btn");
  const removeBtn = document.getElementById("remove-image-btn");
  const maxPhotos = 8;

  function countInputs() {
    return wrapper.querySelectorAll(".secondary-image-item").length;
  }

  addBtn.addEventListener("click", function () {
    const total = countInputs();
    if (total >= maxPhotos) {
      showToast("Maximum 8 photos secondaires.");
      return;
    }

    const div = document.createElement("div");
    div.className = "secondary-image-item";
    div.innerHTML = '<input type="file" name="images[]" class="form-control" accept="image/*">';
    wrapper.appendChild(div);
  });

  removeBtn.addEventListener("click", function () {
    const total = countInputs();
    if (total <= 1) return;
    wrapper.lastElementChild.remove();
  });

  // --- Carte localisation ---
  const cityCenters = {
    "Agadir": [30.4278, -9.5981],
    "Al Hoceima": [35.2517, -3.9372],
    "Asilah": [35.4652, -6.0348],
    "Azrou": [33.4344, -5.2213],
    "Beni Mellal": [32.3373, -6.3498],
    "Berkane": [34.9205, -2.3190],
    "Boujdour": [26.1278, -14.4847],
    "Casablanca": [33.5731, -7.5898],
    "Chefchaouen": [35.1714, -5.2697],
    "Dakhla": [23.6848, -15.9570],
    "El Jadida": [33.2316, -8.5007],
    "Errachidia": [31.9314, -4.4245],
    "Essaouira": [31.5085, -9.7595],
    "Fès": [34.0331, -5.0003],
    "Guelmim": [28.9870, -10.0574],
    "Ifrane": [33.5333, -5.1000],
    "Kenitra": [34.2610, -6.5802],
    "Khemisset": [33.8244, -6.0663],
    "Khouribga": [32.8811, -6.9063],
    "Laâyoune": [27.1536, -13.2033],
    "Larache": [35.1932, -6.1561],
    "Marrakech": [31.6295, -7.9811],
    "Meknès": [33.8935, -5.5473],
    "Mohammedia": [33.6835, -7.3849],
    "Nador": [35.1681, -2.9287],
    "Ouarzazate": [30.9335, -6.9370],
    "Oujda": [34.6814, -1.9086],
    "Rabat": [34.0209, -6.8416],
    "Safi": [32.2994, -9.2372],
    "Salé": [34.0371, -6.7985],
    "Settat": [33.0010, -7.6166],
    "Sidi Ifni": [29.3798, -10.1729],
    "Tanger": [35.7595, -5.8340],
    "Tarfaya": [27.9395, -12.9260],
    "Taza": [34.2149, -4.0104],
    "Tétouan": [35.5889, -5.3626]
  };

  const villeEl = document.getElementById("ville");
  const latEl = document.getElementById("latitude");
  const lngEl = document.getElementById("longitude");

  const oldLat = <?php echo json_encode($oldLatitude !== "" ? (float)$oldLatitude : null); ?>;
  const oldLng = <?php echo json_encode($oldLongitude !== "" ? (float)$oldLongitude : null); ?>;

  let initialCenter = [31.7917, -7.0926];
  let initialZoom = 6;

  if (oldLat !== null && oldLng !== null) {
    initialCenter = [oldLat, oldLng];
    initialZoom = 13;
  } else if (villeEl.value && cityCenters[villeEl.value]) {
    initialCenter = cityCenters[villeEl.value];
    initialZoom = 11;
  }

  const map = L.map("map-picker").setView(initialCenter, initialZoom);

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution: "&copy; OpenStreetMap"
  }).addTo(map);

  let marker = null;

  function setMarker(lat, lng, zoom = 13) {
    latEl.value = Number(lat).toFixed(7);
    lngEl.value = Number(lng).toFixed(7);

    if (marker) {
      marker.setLatLng([lat, lng]);
    } else {
      marker = L.marker([lat, lng], { draggable: true }).addTo(map);

      marker.on("dragend", function (e) {
        const pos = e.target.getLatLng();
        latEl.value = Number(pos.lat).toFixed(7);
        lngEl.value = Number(pos.lng).toFixed(7);
      });
    }

    map.setView([lat, lng], zoom);
  }

  if (oldLat !== null && oldLng !== null) {
    setMarker(oldLat, oldLng, 13);
  }

  map.on("click", function (e) {
    setMarker(e.latlng.lat, e.latlng.lng, map.getZoom() < 13 ? 13 : map.getZoom());
  });

  villeEl.addEventListener("change", function () {
    const city = this.value;
    if (city && cityCenters[city] && !latEl.value && !lngEl.value) {
      map.setView(cityCenters[city], 11);
    }
  });

  setTimeout(function () {
    map.invalidateSize();
  }, 300);
});
</script>

<?php include "includes/footer.php"; ?>