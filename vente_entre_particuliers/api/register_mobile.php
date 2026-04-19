<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/mail.php";

$data = json_decode(file_get_contents("php://input"), true);

$nom = trim((string)($data["nom"] ?? ""));
$email = trim((string)($data["email"] ?? ""));
$password = (string)($data["password"] ?? "");
$password2 = (string)($data["password2"] ?? "");

function jsonOut($ok, $message, $extra = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge([
        "ok" => $ok,
        "message" => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function generateVerificationCode(): string {
    return (string) random_int(100000, 999999);
}

if ($nom === "" || mb_strlen($nom) < 2) {
    jsonOut(false, "Nom invalide (minimum 2 caractères).", [], 400);
}

if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonOut(false, "Email invalide.", [], 400);
}

if (strlen($password) < 6) {
    jsonOut(false, "Mot de passe trop court (minimum 6 caractères).", [], 400);
}

if ($password !== $password2) {
    jsonOut(false, "Les mots de passe ne correspondent pas.", [], 400);
}

try {
    $stmt = $pdo->prepare("SELECT id_user FROM user WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
        jsonOut(false, "Cet email est déjà utilisé. Essaie de te connecter.", [], 400);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $code = generateVerificationCode();
    $expireAt = date("Y-m-d H:i:s", time() + 15 * 60);
    $resendAvailableAt = date("Y-m-d H:i:s", time() + 60);

    $hasStatut = false;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM user LIKE 'statut'")->fetch();
        $hasStatut = !empty($col);
    } catch (Exception $e) {
        $hasStatut = false;
    }

    if ($hasStatut) {
        $stmt = $pdo->prepare("
            INSERT INTO user (
                nom, email, password, date_inscription, role, statut,
                email_verifie, code_verification, code_verification_expire, code_verification_renvoi_at
            )
            VALUES (?, ?, ?, CURDATE(), 'USER', 'ACTIVE', 0, ?, ?, ?)
        ");
        $stmt->execute([$nom, $email, $hash, $code, $expireAt, $resendAvailableAt]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO user (
                nom, email, password, date_inscription, role,
                email_verifie, code_verification, code_verification_expire, code_verification_renvoi_at
            )
            VALUES (?, ?, ?, CURDATE(), 'USER', 0, ?, ?, ?)
        ");
        $stmt->execute([$nom, $email, $hash, $code, $expireAt, $resendAvailableAt]);
    }

    $newId = (int)$pdo->lastInsertId();

    $emailSent = sendVerificationEmail($email, $nom, $code);

    if ($emailSent) {
        jsonOut(true, "Compte créé. Un code de vérification a été envoyé à ton email.", [
            "id_user" => $newId,
            "email" => $email
        ]);
    }

    jsonOut(true, "Compte créé, mais l'email n'a pas pu être envoyé. Réessaie avec “Renvoyer le code”.", [
        "id_user" => $newId,
        "email" => $email
    ]);
} catch (Exception $e) {
    jsonOut(false, "Erreur serveur. Réessaye plus tard.", [], 500);
}