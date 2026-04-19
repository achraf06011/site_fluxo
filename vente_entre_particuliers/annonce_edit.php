<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = currentUserId();
$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) die("Annonce invalide");

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

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

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

$stmt = $pdo->prepare("
  SELECT * FROM annonce
  WHERE id_annonce = ? AND id_vendeur = ?
  LIMIT 1
");
$stmt->execute([$id, $userId]);
$a = $stmt->fetch();
if (!$a) die("Annonce introuvable");

function coverUrl($a) {
  $file = $a["cover_image"] ?? null;
  if (!$file) return null;
  if (str_starts_with($file, "http")) return $file;
  return "uploads/" . $file;
}

$cover = coverUrl($a);
$needsCover = empty($a["cover_image"]);

$stmt = $pdo->prepare("SELECT id_image, url FROM annonce_image WHERE id_annonce = ? ORDER BY id_image DESC");
$stmt->execute([$id]);
$imgs = $stmt->fetchAll();

$currentCategorie = $a["categorie"] ?? "AUTRE";
$currentMarque = $a["marque"] ?? "AUTRE";

if (!isset($CATEGORIES[$currentCategorie])) {
  $currentCategorie = "AUTRE";
}
if (!in_array($currentMarque, $CATEGORIES[$currentCategorie], true)) {
  $currentMarque = $CATEGORIES[$currentCategorie][0];
}

$currentLatitude = $a["latitude"] ?? "";
$currentLongitude = $a["longitude"] ?? "";
$currentVille = $a["ville"] ?? "Marrakech";

include "includes/header.php";
include "includes/navbar.php";
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
#map-picker{
  height: 320px;
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid #dee2e6;
}
.locate-me-btn{
  width: 42px;
  height: 42px;
  border: none;
  border-radius: 999px;
  background: #fff;
  box-shadow: 0 4px 12px rgba(0,0,0,.15);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.locate-me-btn:hover{
  background: #f8f9fa;
}
</style>

<div class="container my-4" style="max-width: 950px;">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h3 class="fw-bold mb-0"><i class="bi bi-pencil-square"></i> Modifier annonce</h3>
    <a class="btn btn-outline-secondary" href="mes_annonces.php">Retour</a>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

  <?php if ($needsCover): ?>
    <div class="alert alert-warning">
      Cette annonce n’a pas de photo principale. Tu dois ajouter une image avant d’enregistrer.
    </div>
  <?php endif; ?>

  <form action="actions/annonce_edit_action.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="id_annonce" value="<?php echo (int)$id; ?>">

    <div class="card shadow-sm border-0">
      <div class="card-body p-4">

        <div class="mb-3">
          <label class="form-label fw-semibold">Photo principale</label>

          <?php if ($cover): ?>
            <div class="mb-2">
              <img src="<?php echo e($cover); ?>" alt=""
                   style="width:100%;max-height:300px;object-fit:cover;border-radius:14px;border:1px solid #eee;">
            </div>
            <div class="text-muted small mb-2">Image actuelle (tu peux la remplacer)</div>
          <?php else: ?>
            <div class="text-muted small mb-2">Aucune image principale.</div>
          <?php endif; ?>

          <input class="form-control" type="file" name="cover_image" accept="image/*" <?php echo $needsCover ? "required" : ""; ?>>
          <div class="form-text">JPG / PNG / WebP — max 5MB</div>
        </div>

        <hr>

        <div class="mb-3">
          <label class="form-label fw-semibold">Photos secondaires</label>

          <?php if (count($imgs) === 0): ?>
            <div class="text-muted small mb-2">Aucune photo secondaire.</div>
          <?php else: ?>
            <div class="row g-2 mb-2">
              <?php foreach ($imgs as $im): ?>
                <?php $src = ltrim((string)$im["url"], "/"); ?>
                <div class="col-6 col-md-4">
                  <div class="border rounded p-2 h-100">
                    <img src="<?php echo e($src); ?>" alt=""
                         style="width:100%;height:140px;object-fit:cover;border-radius:10px;">
                    <div class="form-check mt-2">
                      <input class="form-check-input" type="checkbox"
                             name="delete_images[]" value="<?php echo (int)$im["id_image"]; ?>"
                             id="del_<?php echo (int)$im["id_image"]; ?>">
                      <label class="form-check-label small" for="del_<?php echo (int)$im["id_image"]; ?>">
                        Supprimer
                      </label>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="text-muted small">Coche “Supprimer” puis clique “Enregistrer”.</div>
          <?php endif; ?>

          <div class="mt-3">
            <label class="form-label">Ajouter de nouvelles photos</label>
            <input class="form-control" type="file" name="images[]" accept="image/*" multiple>
            <div class="form-text">Optionnel — max 8 photos secondaires au total</div>
          </div>
        </div>

        <hr>

        <div class="mb-3">
          <label class="form-label fw-semibold">Titre</label>
          <input class="form-control" name="titre" value="<?php echo e($a["titre"]); ?>" required maxlength="150">
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Description</label>
          <textarea class="form-control" name="description" rows="4" required><?php echo e($a["description"]); ?></textarea>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Catégorie</label>
            <select class="form-select" name="categorie" id="categorie" required>
              <?php foreach ($CATEGORIES as $cat => $brands): ?>
                <option value="<?php echo e($cat); ?>" <?php echo $currentCategorie === $cat ? "selected" : ""; ?>>
                  <?php echo e($cat); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Marque</label>
            <select class="form-select" name="marque" id="marque" required></select>
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Prix</label>
            <input class="form-control" name="prix" type="number" step="0.01" min="0" value="<?php echo e($a["prix"]); ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Stock</label>
            <input class="form-control" name="stock" type="number" min="0" value="<?php echo e($a["stock"]); ?>" required>
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label fw-semibold">Ville</label>
          <select class="form-select" name="ville" id="ville" required>
            <?php foreach ($MAROC_CITIES as $city): ?>
              <option value="<?php echo e($city); ?>" <?php echo ($currentVille === $city) ? "selected" : ""; ?>>
                <?php echo e($city); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mt-4">
          <label class="form-label fw-semibold">Localisation précise</label>
          <div id="map-picker"></div>
          <div class="form-text">
            Clique sur la carte pour changer l’emplacement exact.
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
              value="<?php echo e($currentLatitude); ?>"
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
              value="<?php echo e($currentLongitude); ?>"
              readonly
            >
          </div>
        </div>

        <div class="alert alert-info mt-4 mb-0">
          Après modification, l’annonce repasse en <b>EN_ATTENTE_VALIDATION</b> pour validation admin.
        </div>

        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-dark" type="submit">
            <i class="bi bi-save"></i> Enregistrer
          </button>
          <a class="btn btn-outline-secondary" href="mes_annonces.php">Annuler</a>
        </div>

      </div>
    </div>
  </form>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const brandsByCategory = <?php echo json_encode($CATEGORIES, JSON_UNESCAPED_UNICODE); ?>;
  const currentBrand = <?php echo json_encode($currentMarque, JSON_UNESCAPED_UNICODE); ?>;
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
      if (selectedBrand === brand) opt.selected = true;
      marqueEl.appendChild(opt);
    });
  }

  loadBrands(currentBrand);

  categorieEl.addEventListener("change", function () {
    loadBrands("");
  });

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

  const oldLat = <?php echo json_encode($currentLatitude !== "" ? (float)$currentLatitude : null); ?>;
  const oldLng = <?php echo json_encode($currentLongitude !== "" ? (float)$currentLongitude : null); ?>;

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

  const LocateControl = L.Control.extend({
    options: { position: "topright" },
    onAdd: function () {
      const btn = L.DomUtil.create("button", "locate-me-btn");
      btn.type = "button";
      btn.innerHTML = '<i class="bi bi-crosshair"></i>';
      btn.title = "Utiliser ma position actuelle";

      L.DomEvent.disableClickPropagation(btn);
      L.DomEvent.on(btn, "click", function () {
        if (!navigator.geolocation) {
          showToast("La géolocalisation n’est pas supportée sur cet appareil.");
          return;
        }

        navigator.geolocation.getCurrentPosition(
          function (position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            setMarker(lat, lng, 15);
          },
          function () {
            showToast("Impossible de récupérer ta position actuelle.");
          },
          {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
          }
        );
      });

      return btn;
    }
  });

  map.addControl(new LocateControl());

  setTimeout(function () {
    map.invalidateSize();
  }, 300);
});
</script>

<?php include "includes/footer.php"; ?>