<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$userId = (int)($_POST["user_id"] ?? 0);
$id = (int)($_POST["id_annonce"] ?? 0);
$titre = trim((string)($_POST["titre"] ?? ""));
$description = trim((string)($_POST["description"] ?? ""));
$prix = $_POST["prix"] ?? null;
$stock = $_POST["stock"] ?? null;
$ville = trim((string)($_POST["ville"] ?? ""));
$categorie = trim((string)($_POST["categorie"] ?? ""));
$marque = trim((string)($_POST["marque"] ?? ""));
$latitude = $_POST["latitude"] ?? null;
$longitude = $_POST["longitude"] ?? null;
$deleteImages = $_POST["delete_images"] ?? [];

if (!is_array($deleteImages)) {
    $deleteImages = [$deleteImages];
}

$allowedCategories = [
    "VOITURE" => ["TOYOTA","VOLKSWAGEN","BMW","MERCEDES-BENZ","AUDI","HYUNDAI","KIA","TESLA","FORD","RENAULT","PEUGEOT","HONDA","NISSAN","PORSCHE","VOLVO","MAZDA","SUZUKI","AUTRE"],
    "MOTO" => ["HONDA","YAMAHA","KAWASAKI","SUZUKI","BMW","KTM","DUCATI","TRIUMPH","HARLEY-DAVIDSON","INDIAN","ROYAL ENFIELD","APRILIA","MOTO GUZZI","HUSQVARNA","GASGAS","CFMOTO","BENELLI","AUTRE"],
    "TELEPHONE" => ["APPLE","SAMSUNG","XIAOMI","HUAWEI","GOOGLE","OPPO","VIVO","HONOR","REALME","MOTOROLA","SONY","ASUS","NOKIA","ONEPLUS","NOTHING","TECNO","INFINIX","AUTRE"],
    "INFORMATIQUE" => ["APPLE","LENOVO","HP","DELL","ASUS","ACER","MSI","SAMSUNG","MICROSOFT","RAZER","NVIDIA","INTEL","AMD","GIGABYTE","CORSAIR","LOGITECH","HUAWEI","AUTRE"],
    "TV_AUDIO" => ["SAMSUNG","LG","SONY","PANASONIC","TCL","HISENSE","PHILIPS","BOZE","SONOS","JBL","MARSHALL","BANG & OLUFSEN","DENON","SENNHEISER","BEATS","YAMAHA","APPLE","AUTRE"],
    "ELECTROMENAGER" => ["MIELE","BOSCH","SIEMENS","SAMSUNG","LG","WHIRLPOOL","ELECTROLUX","BEKO","HAIER","DYSON","MOULINEX","ROWENTA","TEFAL","SMEG","DE DIETRICH","LIEBHERR","SHARP","AUTRE"],
    "MODE" => ["ZARA","H&M","MANGO","BERSHKA","PULL&BEAR","STRADIVARIUS","MASSIMO DUTTI","UNIQLO","GAP","LEVI'S","GUESS","CALVIN KLEIN","TOMMY HILFIGER","RALPH LAUREN","LACOSTE","ASOS","SHEIN","AUTRE"],
    "MAISON" => ["IKEA","MAISONS DU MONDE","ZARA HOME","H&M HOME","LEROY MERLIN","CASTORAMA","BUT","CONFORAMA","WESTELM","POTTERY BARN","AUTRE"],
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

function jsonOut($ok, $message, $status = 200) {
    http_response_code($status);
    echo json_encode([
        "ok" => $ok,
        "message" => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function saveUpload($file, $uploadsAbs) {
    if (!isset($file["tmp_name"]) || (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
        return null;
    }

    $tmp = $file["tmp_name"];
    $name = $file["name"] ?? "img";
    $size = (int)($file["size"] ?? 0);

    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp);
    finfo_close($finfo);

    switch ($mime) {
        case "image/jpeg":
            $ext = "jpg";
            break;
        case "image/png":
            $ext = "png";
            break;
        case "image/webp":
            $ext = "webp";
            break;
        default:
            $ext = null;
            break;
    }

    if (!$ext) {
        return null;
    }

    if (!is_dir($uploadsAbs)) {
        @mkdir($uploadsAbs, 0777, true);
    }

    $safeBase = preg_replace("/[^a-zA-Z0-9_-]+/", "_", pathinfo($name, PATHINFO_FILENAME));
    if (!$safeBase) {
        $safeBase = "img";
    }

    $filename = $safeBase . "_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    $destAbs = rtrim($uploadsAbs, "/\\") . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $destAbs)) {
        return null;
    }

    return $filename;
}

if ($userId <= 0) jsonOut(false, "Connexion requise.", 400);
if ($id <= 0) jsonOut(false, "Annonce invalide.", 400);
if ($titre === "" || mb_strlen($titre) < 3) jsonOut(false, "Titre invalide.", 400);
if ($description === "" || mb_strlen($description) < 10) jsonOut(false, "Description trop courte.", 400);
if (!is_numeric((string)$prix) || (float)$prix < 0) jsonOut(false, "Prix invalide.", 400);
if (!is_numeric((string)$stock) || (int)$stock < 0) jsonOut(false, "Stock invalide.", 400);
if (!in_array($ville, $MAROC_CITIES, true)) jsonOut(false, "Ville invalide.", 400);
if (!array_key_exists($categorie, $allowedCategories)) jsonOut(false, "Catégorie invalide.", 400);
if (!in_array($marque, $allowedCategories[$categorie], true)) jsonOut(false, "Marque invalide.", 400);
if (!is_numeric((string)$latitude) || !is_numeric((string)$longitude)) jsonOut(false, "Coordonnées GPS invalides.", 400);

$latitude = (float)$latitude;
$longitude = (float)$longitude;

if ($latitude < -90 || $latitude > 90) jsonOut(false, "Latitude invalide.", 400);
if ($longitude < -180 || $longitude > 180) jsonOut(false, "Longitude invalide.", 400);

$uploadsAbs = realpath(__DIR__ . "/../uploads");
if ($uploadsAbs === false) {
    @mkdir(__DIR__ . "/../uploads", 0777, true);
    $uploadsAbs = realpath(__DIR__ . "/../uploads");
    if ($uploadsAbs === false) {
        jsonOut(false, "Impossible de créer le dossier uploads.", 500);
    }
}

try {
    $stmt = $pdo->prepare("
      SELECT id_annonce, cover_image, prix, ancien_prix
      FROM annonce
      WHERE id_annonce = ? AND id_vendeur = ?
      LIMIT 1
    ");
    $stmt->execute([$id, $userId]);
    $ann = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ann) {
        jsonOut(false, "Annonce introuvable.", 404);
    }

    $currentPrice = (float)($ann["prix"] ?? 0);
    $currentOldPrice = isset($ann["ancien_prix"]) && $ann["ancien_prix"] !== null
        ? (float)$ann["ancien_prix"]
        : null;

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

    $uploadedCover = null;
    if (!empty($_FILES["cover_image"]["name"])) {
        $uploadedCover = saveUpload($_FILES["cover_image"], $uploadsAbs);
        if ($uploadedCover === null) {
            jsonOut(false, "Image principale invalide.", 400);
        }
    }

    $pdo->beginTransaction();

    $tmpDeleteIds = array_map("intval", $deleteImages);
    $deleteIds = [];
    foreach ($tmpDeleteIds as $x) {
        if ($x > 0) {
            $deleteIds[] = $x;
        }
    }

    if (count($deleteIds) > 0) {
        $in = implode(",", array_fill(0, count($deleteIds), "?"));

        $stmt = $pdo->prepare("
            SELECT id_image, url
            FROM annonce_image
            WHERE id_annonce = ? AND id_image IN ($in)
        ");
        $stmt->execute(array_merge([$id], $deleteIds));
        $toDel = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            DELETE FROM annonce_image
            WHERE id_annonce = ? AND id_image IN ($in)
        ");
        $stmt->execute(array_merge([$id], $deleteIds));

        foreach ($toDel as $row) {
            $url = (string)($row["url"] ?? "");
            $rel = ltrim($url, "/");
            if (strpos($rel, "uploads/") === 0) {
                $abs = __DIR__ . "/../" . $rel;
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }
        }
    }

    if (!empty($_FILES["images"]) && !empty($_FILES["images"]["name"][0])) {
        $names = $_FILES["images"]["name"];
        $tmps = $_FILES["images"]["tmp_name"];
        $errs = $_FILES["images"]["error"];
        $sizes = $_FILES["images"]["size"];

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM annonce_image WHERE id_annonce = ?");
        $stmtCount->execute([$id]);
        $existingCount = (int)$stmtCount->fetchColumn();

        $stmtImg = $pdo->prepare("INSERT INTO annonce_image (id_annonce, url) VALUES (?, ?)");

        for ($i = 0; $i < count($names); $i++) {
            if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            if ($existingCount >= 8) {
                break;
            }

            $file = [
                "name" => $names[$i],
                "tmp_name" => $tmps[$i],
                "error" => $errs[$i],
                "size" => $sizes[$i]
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
        $admins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);

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

    jsonOut(true, "Annonce modifiée. Elle est en attente de validation admin.");
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonOut(false, "Erreur serveur : " . $e->getMessage(), 500);
}