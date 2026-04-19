<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$userId = (int)($data["user_id"] ?? 0);
$idAnnonce = (int)($data["id_annonce"] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        "ok" => false,
        "message" => "Connexion requise."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($idAnnonce <= 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "Annonce invalide."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id_annonce
        FROM annonce
        WHERE id_annonce = ?
        LIMIT 1
    ");
    $stmt->execute([$idAnnonce]);
    $annonce = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$annonce) {
        http_response_code(404);
        echo json_encode([
            "ok" => false,
            "message" => "Annonce introuvable."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id_favori
        FROM favoris
        WHERE id_user = ? AND id_annonce = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $idAnnonce]);
    $fav = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fav) {
        $stmt = $pdo->prepare("
            DELETE FROM favoris
            WHERE id_user = ? AND id_annonce = ?
        ");
        $stmt->execute([$userId, $idAnnonce]);

        echo json_encode([
            "ok" => true,
            "favori" => false,
            "message" => "Retiré des favoris."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO favoris (id_user, id_annonce)
        VALUES (?, ?)
    ");
    $stmt->execute([$userId, $idAnnonce]);

    echo json_encode([
        "ok" => true,
        "favori" => true,
        "message" => "Ajouté aux favoris."
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}