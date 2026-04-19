<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$userId = (int)($_GET["user_id"] ?? 0);
$id = (int)($_GET["id"] ?? 0);

if ($userId <= 0) {
    echo json_encode([
        "ok" => false,
        "message" => "Connexion requise."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($id <= 0) {
    echo json_encode([
        "ok" => false,
        "message" => "Annonce invalide."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function fullImageUrl($fileOrUrl) {
    if (!$fileOrUrl || trim((string)$fileOrUrl) === "") {
        return null;
    }

    $value = (string)$fileOrUrl;

    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }

    if (str_starts_with($value, "/uploads/")) {
        return "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers" . $value;
    }

    return "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/uploads/" . ltrim($value, "/");
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id_annonce,
            titre,
            description,
            prix,
            ancien_prix,
            stock,
            statut,
            ville,
            categorie,
            marque,
            latitude,
            longitude,
            cover_image
        FROM annonce
        WHERE id_annonce = ? AND id_vendeur = ?
        LIMIT 1
    ");
    $stmt->execute([$id, $userId]);
    $a = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$a) {
        echo json_encode([
            "ok" => false,
            "message" => "Annonce introuvable."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmtImgs = $pdo->prepare("
        SELECT id_image, url
        FROM annonce_image
        WHERE id_annonce = ?
        ORDER BY id_image DESC
    ");
    $stmtImgs->execute([$id]);
    $imgs = $stmtImgs->fetchAll(PDO::FETCH_ASSOC);

    $existingImages = [];
    foreach ($imgs as $img) {
        $existingImages[] = [
            "id_image" => (int)$img["id_image"],
            "image_url" => fullImageUrl($img["url"] ?? null),
        ];
    }

    echo json_encode([
        "ok" => true,
        "annonce" => [
            "id_annonce" => (int)$a["id_annonce"],
            "titre" => (string)$a["titre"],
            "description" => (string)$a["description"],
            "prix" => (float)$a["prix"],
            "ancien_prix" => $a["ancien_prix"] !== null ? (float)$a["ancien_prix"] : null,
            "stock" => (int)$a["stock"],
            "statut" => (string)($a["statut"] ?? ""),
            "ville" => (string)($a["ville"] ?? "Marrakech"),
            "categorie" => (string)($a["categorie"] ?? "AUTRE"),
            "marque" => (string)($a["marque"] ?? "AUTRE"),
            "latitude" => $a["latitude"] !== null ? (float)$a["latitude"] : null,
            "longitude" => $a["longitude"] !== null ? (float)$a["longitude"] : null,
            "cover_image_url" => fullImageUrl($a["cover_image"] ?? null),
            "existing_images" => $existingImages,
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur : " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}