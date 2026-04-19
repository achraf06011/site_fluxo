<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$userId = (int)($_GET["user_id"] ?? 0);

if ($userId <= 0) {
    echo json_encode([
        "ok" => false,
        "message" => "Utilisateur invalide."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function coverUrl($file) {
    if (!$file || trim((string)$file) === "") {
        return null;
    }

    if (preg_match('#^https?://#i', (string)$file)) {
        return $file;
    }

    return "http://192.168.1.13/pfe_fluxo/vente_entre_particuliers/uploads/" . ltrim((string)$file, "/");
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id_annonce,
            titre,
            prix,
            ancien_prix,
            stock,
            statut,
            date_publication,
            cover_image,
            COALESCE(nb_vues, 0) AS nb_vues
        FROM annonce
        WHERE id_vendeur = ?
        ORDER BY id_annonce DESC
        LIMIT 200
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $annonces = [];

    foreach ($rows as $a) {
        $annonces[] = [
            "id_annonce" => (int)$a["id_annonce"],
            "titre" => (string)$a["titre"],
            "prix" => (float)$a["prix"],
            "ancien_prix" => $a["ancien_prix"] !== null ? (float)$a["ancien_prix"] : null,
            "stock" => (int)$a["stock"],
            "statut" => (string)($a["statut"] ?? "DESACTIVEE"),
            "date_publication" => (string)($a["date_publication"] ?? ""),
            "nb_vues" => (int)($a["nb_vues"] ?? 0),
            "cover_image_url" => coverUrl($a["cover_image"] ?? null),
        ];
    }

    echo json_encode([
        "ok" => true,
        "annonces" => $annonces
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur."
    ], JSON_UNESCAPED_UNICODE);
}