<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$userId = (int)($data["user_id"] ?? 0);

if ($userId <= 0) {
    echo json_encode([
        "ok" => false,
        "message" => "Connexion requise."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE notification
        SET is_read = 1
        WHERE id_user = ?
          AND COALESCE(is_read, 0) = 0
    ");
    $stmt->execute([$userId]);

    echo json_encode([
        "ok" => true
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur."
    ], JSON_UNESCAPED_UNICODE);
}