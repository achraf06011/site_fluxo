<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$id = (int)($_GET["id"] ?? 0);
$currentUserId = (int)($_GET["current_user_id"] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "ID annonce invalide"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function fullImageUrl(?string $file): ?string {
    if (!$file || trim($file) === "") return null;
    if (str_starts_with($file, "http")) return $file;
    return "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/uploads/" . ltrim($file, "/");
}

try {
    $stmt = $pdo->prepare("
        SELECT
            a.*,
            u.nom AS vendeur_nom,
            u.email AS vendeur_email
        FROM annonce a
        JOIN user u ON u.id_user = a.id_vendeur
        WHERE a.id_annonce = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $annonce = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$annonce) {
        http_response_code(404);
        echo json_encode([
            "ok" => false,
            "message" => "Annonce introuvable"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $isFavori = false;
    if ($currentUserId > 0) {
        $stmtFav = $pdo->prepare("
            SELECT id_favori
            FROM favoris
            WHERE id_user = ? AND id_annonce = ?
            LIMIT 1
        ");
        $stmtFav->execute([$currentUserId, $id]);
        $isFavori = (bool)$stmtFav->fetch(PDO::FETCH_ASSOC);
    }

    $stmtImgs = $pdo->prepare("
        SELECT url
        FROM annonce_image
        WHERE id_annonce = ?
        ORDER BY id_image DESC
    ");
    $stmtImgs->execute([$id]);
    $imagesDb = $stmtImgs->fetchAll(PDO::FETCH_ASSOC);

    $gallery = [];

    $cover = fullImageUrl($annonce["cover_image"] ?? null);
    if ($cover) {
        $gallery[] = $cover;
    }

    foreach ($imagesDb as $img) {
        $url = $img["url"] ?? "";
        if (!$url) continue;

        if (str_starts_with($url, "http")) {
            $gallery[] = $url;
        } else {
            $gallery[] = "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/" . ltrim($url, "/");
        }
    }

    $gallery = array_values(array_unique(array_filter($gallery)));

    $similarStmt = $pdo->prepare("
        SELECT
            a.id_annonce,
            a.id_vendeur,
            a.titre,
            a.prix,
            a.cover_image,
            a.ville
        FROM annonce a
        WHERE a.id_annonce <> ?
          AND a.statut = 'ACTIVE'
          AND a.categorie = ?
        ORDER BY a.date_publication DESC, a.id_annonce DESC
        LIMIT 4
    ");
    $similarStmt->execute([
        (int)$annonce["id_annonce"],
        (string)($annonce["categorie"] ?? "")
    ]);
    $similarItems = $similarStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($similarItems as &$s) {
        $s["image_url"] = fullImageUrl($s["cover_image"] ?? null);
    }

    $modeVente = (string)($annonce["mode_vente"] ?? "");
    $stock = (int)($annonce["stock"] ?? 0);
    $statut = (string)($annonce["statut"] ?? "");

    $canDirectBuy = in_array($modeVente, ["PAIEMENT_DIRECT", "LES_DEUX"], true) && $stock > 0 && $statut === "ACTIVE";
    $canContact = in_array($modeVente, ["POSSIBILITE_CONTACTE", "LES_DEUX"], true);

    echo json_encode([
        "ok" => true,
        "annonce" => [
            "id_annonce" => (int)$annonce["id_annonce"],
            "id_vendeur" => (int)$annonce["id_vendeur"],
            "titre" => $annonce["titre"],
            "description" => $annonce["description"],
            "prix" => (float)$annonce["prix"],
            "ancien_prix" => $annonce["ancien_prix"] !== null ? (float)$annonce["ancien_prix"] : null,
            "ville" => $annonce["ville"],
            "stock" => $stock,
            "mode_vente" => $modeVente,
            "statut" => $statut,
            "categorie" => $annonce["categorie"],
            "marque" => $annonce["marque"],
            "vendeur_nom" => $annonce["vendeur_nom"],
            "vendeur_email" => $annonce["vendeur_email"],
            "cover_image_url" => $cover,
            "gallery" => $gallery,
            "latitude" => $annonce["latitude"] !== null ? (float)$annonce["latitude"] : null,
            "longitude" => $annonce["longitude"] !== null ? (float)$annonce["longitude"] : null,
            "is_favori" => $isFavori,
            "can_add_to_cart" => $canDirectBuy,
            "can_buy_now" => $canDirectBuy,
            "can_contact" => $canContact,
        ],
        "similarItems" => $similarItems
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur"
    ], JSON_UNESCAPED_UNICODE);
}