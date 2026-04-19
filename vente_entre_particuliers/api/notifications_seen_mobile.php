<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$userId = (int)($data["user_id"] ?? 0);
$id = (int)($data["id_notification"] ?? 0);

if ($userId <= 0 || $id <= 0) {
    echo json_encode([
        "ok" => false,
        "message" => "Paramètres invalides."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE notification
        SET is_popup_seen = 1
        WHERE id_notification = ? AND id_user = ?
    ");
    $stmt->execute([$id, $userId]);

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