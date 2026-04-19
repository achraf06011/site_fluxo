<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/mail.php";

$data = json_decode(file_get_contents("php://input"), true);
$email = trim((string)($data["email"] ?? ""));

function jsonOut(bool $ok, string $message, array $extra = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(array_merge([
        "ok" => $ok,
        "message" => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonOut(false, "Email invalide.");
}

try {
    $stmt = $pdo->prepare("
        SELECT id_user, nom, email, statut, email_verifie
        FROM user
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonOut(true, "Si cet email existe, un lien de réinitialisation a été envoyé.");
    }

    if (($user["statut"] ?? "ACTIVE") === "BLOQUE") {
        jsonOut(true, "Si cet email existe, un lien de réinitialisation a été envoyé.");
    }

    if ((int)($user["email_verifie"] ?? 0) !== 1) {
        jsonOut(true, "Si cet email existe, un lien de réinitialisation a été envoyé.");
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash("sha256", $token);
    $expireAt = date("Y-m-d H:i:s", time() + 60 * 60);

    $stmt = $pdo->prepare("
        UPDATE user
        SET reset_token = ?, reset_token_expire = ?
        WHERE id_user = ?
    ");
    $stmt->execute([$tokenHash, $expireAt, (int)$user["id_user"]]);

    // Lien mobile avec le scheme de ton app
    $resetLink = "fluxomobile://reset-password?token=" . urlencode($token) . "&email=" . urlencode((string)$user["email"]);

    sendResetPasswordEmail($user["email"], $user["nom"], $resetLink);

    jsonOut(true, "Si cet email existe, un lien de réinitialisation a été envoyé.");

} catch (Exception $e) {
    jsonOut(false, "Erreur serveur. Réessaie plus tard.", [], 500);
}