<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

function jsonOut($ok, $message, $extra = [], $status = 200)
{
    http_response_code($status);
    echo json_encode(array_merge([
        "ok" => $ok,
        "message" => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)($_POST["user_id"] ?? 0);

$titre = trim((string)($_POST["titre"] ?? ""));
$description = trim((string)($_POST["description"] ?? ""));
$prix = trim((string)($_POST["prix"] ?? ""));
$stock = trim((string)($_POST["stock"] ?? ""));
$type = trim((string)($_POST["type"] ?? ""));
$mode_vente = trim((string)($_POST["mode_vente"] ?? ""));
$categorie = trim((string)($_POST["categorie"] ?? ""));
$marque = trim((string)($_POST["marque"] ?? ""));
$ville = trim((string)($_POST["ville"] ?? "Marrakech"));
$latitude = trim((string)($_POST["latitude"] ?? ""));
$longitude = trim((string)($_POST["longitude"] ?? ""));
$livraison_active = !empty($_POST["livraison_active"]) ? 1 : 0;
$livraison_prix_same_city = (float)($_POST["livraison_prix_same_city"] ?? 15);
$livraison_prix_other_city = (float)($_POST["livraison_prix_other_city"] ?? 40);

$allowedType = ["NEUF", "OCCASION"];
$allowedMode = ["PAIEMENT_DIRECT", "POSSIBILITE_CONTACTE", "LES_DEUX"];

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

if ($userId <= 0) {
    jsonOut(false, "Connexion requise.", [], 401);
}

if ($titre === "" || mb_strlen($titre) < 3) {
    jsonOut(false, "Titre invalide (min 3 caractères).", [], 422);
}

if ($description === "" || mb_strlen($description) < 10) {
    jsonOut(false, "Description trop courte (min 10 caractères).", [], 422);
}

if (!is_numeric($prix) || (float)$prix < 0) {
    jsonOut(false, "Prix invalide.", [], 422);
}

if (!ctype_digit((string)$stock) || (int)$stock < 0) {
    jsonOut(false, "Stock invalide.", [], 422);
}

if (!in_array($type, $allowedType, true)) {
    jsonOut(false, "Type invalide.", [], 422);
}

if (!in_array($mode_vente, $allowedMode, true)) {
    jsonOut(false, "Mode de vente invalide.", [], 422);
}

if (!array_key_exists($categorie, $allowedCategories)) {
    jsonOut(false, "Catégorie invalide.", [], 422);
}

if (!in_array($marque, $allowedCategories[$categorie], true)) {
    jsonOut(false, "Marque invalide pour cette catégorie.", [], 422);
}

if (!in_array($ville, $MAROC_CITIES, true)) {
    jsonOut(false, "Ville invalide.", [], 422);
}

if ($latitude === "" || $longitude === "") {
    jsonOut(false, "Latitude et longitude obligatoires.", [], 422);
}

if (!is_numeric($latitude) || !is_numeric($longitude)) {
    jsonOut(false, "Coordonnées GPS invalides.", [], 422);
}

$latitude = (float)$latitude;
$longitude = (float)$longitude;

if ($latitude < -90 || $latitude > 90) {
    jsonOut(false, "Latitude invalide.", [], 422);
}

if ($longitude < -180 || $longitude > 180) {
    jsonOut(false, "Longitude invalide.", [], 422);
}

if ($livraison_prix_same_city < 0 || $livraison_prix_same_city > 300) {
    jsonOut(false, "Prix livraison même ville invalide.", [], 422);
}

if ($livraison_prix_other_city < 0 || $livraison_prix_other_city > 600) {
    jsonOut(false, "Prix livraison autre ville invalide.", [], 422);
}

$hasCover = !empty($_FILES["cover_image"]["name"]);
$hasOther = !empty($_FILES["images"]["name"][0]);

if (!$hasCover && !$hasOther) {
    jsonOut(false, "Ajoute au moins une photo.", [], 422);
}

function saveUpload($file, $uploadsAbs)
{
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

$uploadsAbs = realpath(__DIR__ . "/../uploads");
if ($uploadsAbs === false) {
    @mkdir(__DIR__ . "/../uploads", 0777, true);
    $uploadsAbs = realpath(__DIR__ . "/../uploads");
    if ($uploadsAbs === false) {
        jsonOut(false, "Impossible de créer le dossier uploads.", [], 500);
    }
}

try {
    $pdo->beginTransaction();

    $coverFileName = null;

    if ($hasCover) {
        $coverFileName = saveUpload($_FILES["cover_image"], $uploadsAbs);
        if ($coverFileName === null) {
            jsonOut(false, "Image principale invalide.", [], 422);
        }
    }

    $statut = "EN_ATTENTE_VALIDATION";

    $stmt = $pdo->prepare("
        INSERT INTO annonce (
            titre, description, prix, date_publication, statut, type, categorie, marque,
            mode_vente, stock, id_vendeur, cover_image, ville, latitude, longitude,
            livraison_active, livraison_prix_same_city, livraison_prix_other_city
        )
        VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $titre,
        $description,
        (float)$prix,
        $statut,
        $type,
        $categorie,
        $marque,
        $mode_vente,
        (int)$stock,
        $userId,
        $coverFileName,
        $ville,
        $latitude,
        $longitude,
        (int)$livraison_active,
        (float)$livraison_prix_same_city,
        (float)$livraison_prix_other_city
    ]);

    $annonceId = (int)$pdo->lastInsertId();
    $savedAny = ($coverFileName !== null);

    if ($hasOther) {
        $names = $_FILES["images"]["name"];
        $tmps = $_FILES["images"]["tmp_name"];
        $errs = $_FILES["images"]["error"];
        $sizes = $_FILES["images"]["size"];

        $stmtImg = $pdo->prepare("
            INSERT INTO annonce_image (id_annonce, url)
            VALUES (?, ?)
        ");

        $validSecondaryCount = 0;

        for ($i = 0; $i < count($names); $i++) {
            if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            if ($validSecondaryCount >= 8) {
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
                $savedAny = true;
                $validSecondaryCount++;
                $stmtImg->execute([$annonceId, "/uploads/" . $fn]);
            }
        }
    }

    if (!$savedAny) {
        $pdo->rollBack();
        jsonOut(false, "Aucune image valide n'a été envoyée.", [], 422);
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
                "Nouvelle annonce à valider",
                "Une nouvelle annonce \"" . $titre . "\" a été publiée et attend une validation admin.",
                "../admin/annonces.php?statut=EN_ATTENTE_VALIDATION"
            ]);
        }
    } catch (Exception $e) {
    }

    $pdo->commit();

    jsonOut(true, "Annonce envoyée ! Elle sera visible après validation admin.", [
        "id_annonce" => $annonceId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonOut(false, "Erreur serveur : " . $e->getMessage(), [], 500);
}