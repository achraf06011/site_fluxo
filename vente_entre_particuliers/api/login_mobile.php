<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/mail.php";

$data = json_decode(file_get_contents("php://input"), true);

$email = trim((string)($data["email"] ?? ""));
$password = (string)($data["password"] ?? "");

function jsonOut($ok, $message, $extra = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge([
        "ok" => $ok,
        "message" => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function generateVerificationCode() {
    return (string) random_int(100000, 999999);
}

if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonOut(false, "Email invalide.", [], 400);
}

if ($password === "") {
    jsonOut(false, "Mot de passe obligatoire.", [], 400);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonOut(false, "Identifiants invalides.", [], 401);
    }

    if (($user["statut"] ?? "ACTIVE") === "BLOQUE") {
        jsonOut(false, "Compte bloqué par l’admin. Contacte le support.", [], 403);
    }

    if (!password_verify($password, (string)$user["password"])) {
        jsonOut(false, "Identifiants invalides.", [], 401);
    }

    if ((int)($user["email_verifie"] ?? 0) !== 1) {
        $now = time();
        $resendAt = !empty($user["code_verification_renvoi_at"])
            ? strtotime((string)$user["code_verification_renvoi_at"])
            : 0;

        if ($resendAt <= $now) {
            $code = generateVerificationCode();
            $expireAt = date("Y-m-d H:i:s", $now + 15 * 60);
            $nextResendAt = date("Y-m-d H:i:s", $now + 60);

            $stmt = $pdo->prepare("
                UPDATE user
                SET code_verification = ?, code_verification_expire = ?, code_verification_renvoi_at = ?
                WHERE id_user = ?
            ");
            $stmt->execute([$code, $expireAt, $nextResendAt, (int)$user["id_user"]]);

            sendVerificationEmail((string)$user["email"], (string)$user["nom"], $code);
        }

        jsonOut(true, "Ton email n’est pas encore vérifié. Termine la vérification pour te connecter.", [
            "need_verification" => true,
            "id_user" => (int)$user["id_user"],
            "email" => (string)$user["email"]
        ]);
    }

    jsonOut(true, "Connexion réussie. Bienvenue !", [
        "user" => [
            "id_user" => (int)$user["id_user"],
            "nom" => (string)$user["nom"],
            "email" => (string)$user["email"],
            "role" => (string)($user["role"] ?? "USER")
        ]
    ]);
} catch (Exception $e) {
    jsonOut(false, "Erreur serveur. Réessaye plus tard.", [], 500);
}