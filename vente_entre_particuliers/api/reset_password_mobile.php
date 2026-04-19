<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";

$data = json_decode(file_get_contents("php://input"), true);

$token = trim((string)($data["token"] ?? ""));
$password = (string)($data["password"] ?? "");
$password2 = (string)($data["password2"] ?? "");

function jsonOut(bool $ok, string $message, array $extra = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(array_merge([
        "ok" => $ok,
        "message" => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($token === "") {
    jsonOut(false, "Lien invalide.");
}

if (strlen($password) < 6) {
    jsonOut(false, "Mot de passe trop court (minimum 6 caractères).");
}

if ($password !== $password2) {
    jsonOut(false, "Les mots de passe ne correspondent pas.");
}

try {
    $tokenHash = hash("sha256", $token);

    $stmt = $pdo->prepare("
        SELECT id_user, reset_token_expire
        FROM user
        WHERE reset_token = ?
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonOut(false, "Lien invalide ou expiré.");
    }

    if (empty($user["reset_token_expire"]) || strtotime($user["reset_token_expire"]) < time()) {
        jsonOut(false, "Lien expiré. Refais une demande.");
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE user
        SET password = ?, reset_token = NULL, reset_token_expire = NULL
        WHERE id_user = ?
    ");
    $stmt->execute([$hash, (int)$user["id_user"]]);

    jsonOut(true, "Mot de passe modifié avec succès. Tu peux maintenant te connecter.");

} catch (Exception $e) {
    jsonOut(false, "Erreur serveur. Réessaie plus tard.", [], 500);
}