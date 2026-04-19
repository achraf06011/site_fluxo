<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/mail.php";

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php");
    exit;
}

$email = trim($_POST["email"] ?? "");
$password = (string)($_POST["password"] ?? "");

$_SESSION["flash_old"] = ["email" => $email];

function fail($msg) {
    $_SESSION["flash_error"] = $msg;
    header("Location: ../login.php");
    exit;
}

function generateVerificationCode(): string {
    return (string) random_int(100000, 999999);
}

if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail("Email invalide.");
}

if ($password === "") {
    fail("Mot de passe obligatoire.");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        fail("Identifiants invalides.");
    }

    if (($user["statut"] ?? "ACTIVE") === "BLOQUE") {
        fail("Compte bloqué par l’admin. Contacte le support.");
    }

    if (!password_verify($password, $user["password"])) {
        fail("Identifiants invalides.");
    }

    if ((int)($user["email_verifie"] ?? 0) !== 1) {
        $now = time();
        $resendAt = !empty($user["code_verification_renvoi_at"]) ? strtotime($user["code_verification_renvoi_at"]) : 0;

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

            sendVerificationEmail($user["email"], $user["nom"], $code);
        }

        $_SESSION["pending_verification_user_id"] = (int)$user["id_user"];
        $_SESSION["pending_verification_email"] = $user["email"];
        $_SESSION["flash_success"] = "Ton email n’est pas encore vérifié. Termine la vérification pour te connecter.";

        header("Location: ../verifier_email.php");
        exit;
    }

    $_SESSION["user"] = [
        "id_user" => (int)$user["id_user"],
        "nom" => $user["nom"],
        "email" => $user["email"],
        "role" => $user["role"] ?? "USER",
    ];

    unset($_SESSION["flash_old"]);
    $_SESSION["flash_success"] = "Connexion réussie. Bienvenue !";

    if (($_SESSION["user"]["role"] ?? "USER") === "ADMIN") {
        header("Location: ../admin/index.php");
        exit;
    }

    header("Location: ../index.php");
    exit;
} catch (Exception $e) {
    fail("Erreur serveur. Réessaye plus tard.");
}