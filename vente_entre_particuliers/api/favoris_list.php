<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$userId = (int)($_GET["user_id"] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        "ok" => false,
        "message" => "Connexion requise."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function imgUrlFromRow($x) {
    $file = isset($x["cover_image"]) ? $x["cover_image"] : null;

    if (!$file) {
        return null;
    }

    if (strpos($file, "http") === 0) {
        return $file;
    }

    return "http://" . ($_SERVER["HTTP_HOST"] ?? "192.168.1.13") . "/pfe_fluxo/vente_entre_particuliers/uploads/" . ltrim($file, "/");
}

function modeLabel($mode) {
    switch ($mode) {
        case "PAIEMENT_DIRECT":
            return "PAIEMENT DIRECT";

        case "POSSIBILITE_CONTACTE":
            return "CONTACTER LE VENDEUR";

        case "LES_DEUX":
            return "PAIEMENT DIRECT OU CONTACTER VENDEUR";

        default:
            return $mode;
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT
            a.id_annonce,
            a.id_vendeur,
            a.titre,
            a.prix,
            a.ancien_prix,
            a.ville,
            a.stock,
            a.mode_vente,
            a.cover_image,
            u.nom AS vendeur_nom
        FROM favoris f
        JOIN annonce a ON a.id_annonce = f.id_annonce
        JOIN user u ON u.id_user = a.id_vendeur
        WHERE f.id_user = ?
          AND a.statut = 'ACTIVE'
        ORDER BY f.id_favori DESC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $favoris = [];

    foreach ($rows as $a) {
        $modeV = (string)($a["mode_vente"] ?? "");

        $favoris[] = [
            "id_annonce" => (int)($a["id_annonce"] ?? 0),
            "id_vendeur" => (int)($a["id_vendeur"] ?? 0),
            "titre" => (string)($a["titre"] ?? ""),
            "prix" => (float)($a["prix"] ?? 0),
            "ancien_prix" => isset($a["ancien_prix"]) ? (float)$a["ancien_prix"] : null,
            "ville" => (string)($a["ville"] ?? ""),
            "stock" => (int)($a["stock"] ?? 0),
            "mode_vente" => $modeV,
            "mode_label" => modeLabel($modeV),
            "vendeur_nom" => (string)($a["vendeur_nom"] ?? ""),
            "cover_image_url" => imgUrlFromRow($a),
            "is_favori" => true,
            "can_buy" => in_array($modeV, ["PAIEMENT_DIRECT", "LES_DEUX"], true),
            "can_chat" => in_array($modeV, ["POSSIBILITE_CONTACTE", "LES_DEUX"], true)
        ];
    }

    echo json_encode([
        "ok" => true,
        "favoris" => $favoris
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur."
    ], JSON_UNESCAPED_UNICODE);
}