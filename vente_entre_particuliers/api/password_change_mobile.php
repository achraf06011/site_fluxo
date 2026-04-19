<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$userId = (int)($data["user_id"] ?? 0);
$oldPassword = (string)($data["old_password"] ?? "");
$newPassword = (string)($data["new_password"] ?? "");
$newPassword2 = (string)($data["new_password2"] ?? "");

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        "ok" => false,
        "message" => "Connexion requise."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($oldPassword === "") {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "Ancien mot de passe obligatoire."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "Le nouveau mot de passe doit contenir au moins 6 caractères."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($newPassword !== $newPassword2) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "Les mots de passe ne correspondent pas."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id_user, password
        FROM user
        WHERE id_user = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        echo json_encode([
            "ok" => false,
            "message" => "Utilisateur introuvable."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $currentHash = (string)($u["password"] ?? "");

    if (!password_verify($oldPassword, $currentHash)) {
        echo json_encode([
            "ok" => false,
            "message" => "Ancien mot de passe incorrect."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE user
        SET password = ?
        WHERE id_user = ?
    ");
    $stmt->execute([$newHash, $userId]);

    echo json_encode([
        "ok" => true,
        "message" => "Mot de passe modifié avec succès."
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur."
    ], JSON_UNESCAPED_UNICODE);
}