<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/mail.php";

$data = json_decode(file_get_contents("php://input"), true);

$idUser = (int)($data["id_user"] ?? 0);

function jsonOut($ok, $message, $extra = [], $codeHttp = 200) {
    http_response_code($codeHttp);
    echo json_encode(array_merge([
        "ok" => $ok,
        "message" => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function generateVerificationCode(): string {
    return (string) random_int(100000, 999999);
}

if ($idUser <= 0) {
    jsonOut(false, "Utilisateur invalide.", [], 400);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM user WHERE id_user = ? LIMIT 1");
    $stmt->execute([$idUser]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonOut(false, "Utilisateur introuvable.", [], 404);
    }

    if ((int)($user["email_verifie"] ?? 0) === 1) {
        jsonOut(false, "Cet email est déjà vérifié.", [], 400);
    }

    $now = time();
    $resendAt = !empty($user["code_verification_renvoi_at"])
        ? strtotime((string)$user["code_verification_renvoi_at"])
        : 0;

    if ($resendAt > $now) {
        $seconds = $resendAt - $now;
        jsonOut(false, "Patiente encore {$seconds}s avant de renvoyer le code.", [], 400);
    }

    $code = generateVerificationCode();
    $expireAt = date("Y-m-d H:i:s", $now + 15 * 60);
    $nextResendAt = date("Y-m-d H:i:s", $now + 60);

    $stmt = $pdo->prepare("
        UPDATE user
        SET code_verification = ?, code_verification_expire = ?, code_verification_renvoi_at = ?
        WHERE id_user = ?
    ");
    $stmt->execute([$code, $expireAt, $nextResendAt, $idUser]);

    $sent = sendVerificationEmail((string)$user["email"], (string)$user["nom"], $code);

    if (!$sent) {
        jsonOut(false, "Le code n'a pas pu être envoyé.", [], 500);
    }

    jsonOut(true, "Un nouveau code a été envoyé à ton email.");
} catch (Exception $e) {
    jsonOut(false, "Erreur serveur. Réessaye plus tard.", [], 500);
}