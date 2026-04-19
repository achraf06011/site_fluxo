<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../mes_annonces.php");
  exit;
}

$userId = currentUserId();

$id = (int)($_POST["id_annonce"] ?? 0);
$titre = trim($_POST["titre"] ?? "");
$description = trim($_POST["description"] ?? "");
$prix = trim($_POST["prix"] ?? "");
$stock = trim($_POST["stock"] ?? "");
$ville = trim($_POST["ville"] ?? "");
$categorie = trim($_POST["categorie"] ?? "");
$marque = trim($_POST["marque"] ?? "");
$latitude = trim($_POST["latitude"] ?? "");
$longitude = trim($_POST["longitude"] ?? "");

$deleteImages = $_POST["delete_images"] ?? [];
if (!is_array($deleteImages)) $deleteImages = [];

function backToEdit(int $id, string $msg, bool $ok = false): void {
  if ($ok) $_SESSION["flash_success"] = $msg;
  else $_SESSION["flash_error"] = $msg;
  header("Location: ../annonce_edit.php?id=" . $id);
  exit;
}

$allowedCategories = [
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

$MAROC_CITIES = [
  "Agadir","Al Hoceima","Asilah","Azrou","Beni Mellal","Berkane","Boujdour",
  "Casablanca","Chefchaouen","Dakhla","El Jadida","Errachidia","Essaouira",
  "Fès","Guelmim","Ifrane","Kenitra","Khemisset","Khouribga","Laâyoune",
  "Larache","Marrakech","Meknès","Mohammedia","Nador","Ouarzazate",
  "Oujda","Rabat","Safi","Salé","Settat","Sidi Ifni","Tanger","Tarfaya",
  "Taza","Tétouan"
];

if ($id <= 0) backToEdit($id, "Annonce invalide.");
if ($titre === "" || mb_strlen($titre) < 3) backToEdit($id, "Titre invalide.");
if ($description === "" || mb_strlen($description) < 10) backToEdit($id, "Description trop courte.");
if (!is_numeric($prix) || (float)$prix < 0) backToEdit($id, "Prix invalide.");
if (!ctype_digit((string)$stock) || (int)$stock < 0) backToEdit($id, "Stock invalide.");
if (!in_array($ville, $MAROC_CITIES, true)) backToEdit($id, "Ville invalide.");
if (!array_key_exists($categorie, $allowedCategories)) backToEdit($id, "Catégorie invalide.");
if (!in_array($marque, $allowedCategories[$categorie], true)) backToEdit($id, "Marque invalide.");
if ($latitude === "" || $longitude === "") backToEdit($id, "Choisis la localisation sur la carte.");
if (!is_numeric($latitude) || !is_numeric($longitude)) backToEdit($id, "Coordonnées GPS invalides.");

$latitude = (float)$latitude;
$longitude = (float)$longitude;

if ($latitude < -90 || $latitude > 90) backToEdit($id, "Latitude invalide.");
if ($longitude < -180 || $longitude > 180) backToEdit($id, "Longitude invalide.");

$stmt = $pdo->prepare("
  SELECT id_annonce, cover_image, prix, ancien_prix
  FROM annonce
  WHERE id_annonce = ? AND id_vendeur = ?
  LIMIT 1
");
$stmt->execute([$id, $userId]);
$ann = $stmt->fetch();
if (!$ann) backToEdit($id, "Annonce introuvable.");

$currentCover = $ann["cover_image"] ?? null;
$currentPrice = (float)($ann["prix"] ?? 0);
$currentOldPrice = isset($ann["ancien_prix"]) && $ann["ancien_prix"] !== null ? (float)$ann["ancien_prix"] : null;
$newPrice = (float)$prix;

$ancienPrixToSave = null;

if ($newPrice < $currentPrice) {
  $ancienPrixToSave = $currentPrice;
} elseif ($newPrice > $currentPrice) {
  $ancienPrixToSave = null;
} else {
  if ($currentOldPrice !== null && $currentOldPrice > $newPrice) {
    $ancienPrixToSave = $currentOldPrice;
  }
}

function saveUpload(array $file, string $uploadsAbs): ?string {
  if (!isset($file["tmp_name"]) || ($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    return null;
  }

  $tmp = $file["tmp_name"];
  $name = $file["name"] ?? "img";
  $size = (int)($file["size"] ?? 0);
  if ($size <= 0 || $size > 5 * 1024 * 1024) return null;

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($finfo, $tmp);
  finfo_close($finfo);

  $ext = match ($mime) {
    "image/jpeg" => "jpg",
    "image/png"  => "png",
    "image/webp" => "webp",
    default      => null,
  };
  if (!$ext) return null;

  if (!is_dir($uploadsAbs)) @mkdir($uploadsAbs, 0777, true);

  $safeBase = preg_replace("/[^a-zA-Z0-9_-]+/", "_", pathinfo($name, PATHINFO_FILENAME));
  if (!$safeBase) $safeBase = "img";

  $filename = $safeBase . "_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
  $destAbs = rtrim($uploadsAbs, "/\\") . DIRECTORY_SEPARATOR . $filename;

  if (!move_uploaded_file($tmp, $destAbs)) return null;
  return $filename;
}

$uploadsAbs = realpath(__DIR__ . "/../uploads");
if ($uploadsAbs === false) {
  @mkdir(__DIR__ . "/../uploads", 0777, true);
  $uploadsAbs = realpath(__DIR__ . "/../uploads");
  if ($uploadsAbs === false) backToEdit($id, "Impossible de créer le dossier uploads.");
}

$uploadedCover = null;
if (!empty($_FILES["cover_image"]["name"])) {
  $uploadedCover = saveUpload($_FILES["cover_image"], $uploadsAbs);
  if ($uploadedCover === null) {
    backToEdit($id, "Image principale invalide.");
  }
} else {
  if (empty($currentCover)) {
    backToEdit($id, "Tu dois ajouter une photo principale.");
  }
}

try {
  $pdo->beginTransaction();

  if (count($deleteImages) > 0) {
    $delIds = array_values(array_filter(array_map("intval", $deleteImages), fn($x) => $x > 0));

    if (count($delIds) > 0) {
      $in = implode(",", array_fill(0, count($delIds), "?"));

      $stmt = $pdo->prepare("
        SELECT id_image, url
        FROM annonce_image
        WHERE id_annonce = ? AND id_image IN ($in)
      ");
      $stmt->execute(array_merge([$id], $delIds));
      $toDel = $stmt->fetchAll();

      $stmt = $pdo->prepare("
        DELETE FROM annonce_image
        WHERE id_annonce = ? AND id_image IN ($in)
      ");
      $stmt->execute(array_merge([$id], $delIds));

      foreach ($toDel as $row) {
        $url = (string)($row["url"] ?? "");
        $rel = ltrim($url, "/");
        if (str_starts_with($rel, "uploads/")) {
          $abs = __DIR__ . "/../" . $rel;
          if (is_file($abs)) @unlink($abs);
        }
      }
    }
  }

  if (!empty($_FILES["images"]) && !empty($_FILES["images"]["name"][0])) {
    $names = $_FILES["images"]["name"];
    $tmps  = $_FILES["images"]["tmp_name"];
    $errs  = $_FILES["images"]["error"];
    $sizes = $_FILES["images"]["size"];

    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM annonce_image WHERE id_annonce = ?");
    $stmtCount->execute([$id]);
    $existingCount = (int)$stmtCount->fetchColumn();

    $stmtImg = $pdo->prepare("INSERT INTO annonce_image (id_annonce, url) VALUES (?, ?)");

    for ($i = 0; $i < count($names); $i++) {
      if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
      if ($existingCount >= 8) break;

      $file = [
        "name" => $names[$i],
        "tmp_name" => $tmps[$i],
        "error" => $errs[$i],
        "size" => $sizes[$i],
      ];

      $fn = saveUpload($file, $uploadsAbs);
      if ($fn) {
        $stmtImg->execute([$id, "/uploads/" . $fn]);
        $existingCount++;
      }
    }
  }

  if ($uploadedCover) {
    $stmt = $pdo->prepare("
      UPDATE annonce
      SET titre = ?, description = ?, prix = ?, ancien_prix = ?, stock = ?, ville = ?,
          categorie = ?, marque = ?, latitude = ?, longitude = ?,
          cover_image = ?,
          statut = 'EN_ATTENTE_VALIDATION',
          is_modified = 1,
          date_modification = NOW()
      WHERE id_annonce = ? AND id_vendeur = ?
    ");
    $stmt->execute([
      $titre,
      $description,
      $newPrice,
      $ancienPrixToSave,
      (int)$stock,
      $ville,
      $categorie,
      $marque,
      $latitude,
      $longitude,
      $uploadedCover,
      $id,
      $userId
    ]);
  } else {
    $stmt = $pdo->prepare("
      UPDATE annonce
      SET titre = ?, description = ?, prix = ?, ancien_prix = ?, stock = ?, ville = ?,
          categorie = ?, marque = ?, latitude = ?, longitude = ?,
          statut = 'EN_ATTENTE_VALIDATION',
          is_modified = 1,
          date_modification = NOW()
      WHERE id_annonce = ? AND id_vendeur = ?
    ");
    $stmt->execute([
      $titre,
      $description,
      $newPrice,
      $ancienPrixToSave,
      (int)$stock,
      $ville,
      $categorie,
      $marque,
      $latitude,
      $longitude,
      $id,
      $userId
    ]);
  }

  try {
    $stmtAdmins = $pdo->query("SELECT id_user FROM user WHERE role = 'ADMIN'");
    $admins = $stmtAdmins->fetchAll();

    $stmtNotif = $pdo->prepare("
      INSERT INTO notification
        (id_user, type_notification, titre, contenu, lien, is_read, is_popup_seen)
      VALUES
        (?, 'ADMIN_ANNONCE_WAIT', ?, ?, ?, 0, 0)
    ");

    foreach ($admins as $ad) {
      $stmtNotif->execute([
        (int)$ad["id_user"],
        "Annonce modifiée à valider",
        "L’annonce \"" . $titre . "\" a été modifiée et attend une nouvelle validation admin.",
        "../admin/annonces.php?statut=EN_ATTENTE_VALIDATION"
      ]);
    }
  } catch (Exception $e) {
  }

  $pdo->commit();

  $_SESSION["flash_success"] = "Annonce modifiée. Elle est en attente de validation admin.";
  header("Location: ../mes_annonces.php");
  exit;

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  backToEdit($id, "Erreur serveur : " . $e->getMessage());
}