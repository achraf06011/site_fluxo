<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/mail.php";

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../register.php");
    exit;
}

$nom = trim($_POST["nom"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = (string)($_POST["password"] ?? "");
$password2 = (string)($_POST["password2"] ?? "");

$_SESSION["flash_old"] = [
    "nom" => $nom,
    "email" => $email,
];

function fail($msg) {
    $_SESSION["flash_error"] = $msg;
    header("Location: ../register.php");
    exit;
}

function generateVerificationCode(): string {
    return (string) random_int(100000, 999999);
}

if ($nom === "" || mb_strlen($nom) < 2) {
    fail("Nom invalide (minimum 2 caractères).");
}

if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail("Email invalide.");
}

if (strlen($password) < 6) {
    fail("Mot de passe trop court (minimum 6 caractères).");
}

if ($password !== $password2) {
    fail("Les mots de passe ne correspondent pas.");
}

try {
    $stmt = $pdo->prepare("SELECT id_user FROM user WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $exists = $stmt->fetch();

    if ($exists) {
        fail("Cet email est déjà utilisé. Essaie de te connecter.");
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

    $newId = (int) $pdo->lastInsertId();

    $emailSent = sendVerificationEmail($email, $nom, $code);

    $_SESSION["pending_verification_user_id"] = $newId;
    $_SESSION["pending_verification_email"] = $email;

    unset($_SESSION["flash_old"]);

    if ($emailSent) {
        $_SESSION["flash_success"] = "Compte créé. Un code de vérification a été envoyé à ton email.";
    } else {
        $_SESSION["flash_error"] = "Compte créé, mais l'email n'a pas pu être envoyé. Réessaie avec “Renvoyer le code”.";
    }

    header("Location: ../verifier_email.php");
    exit;
} catch (Exception $e) {
    fail("Erreur serveur. Réessaye plus tard.");
}