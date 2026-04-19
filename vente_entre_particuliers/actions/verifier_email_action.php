<?php
require_once "../config/db.php";
require_once "../config/auth.php";

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../verifier_email.php");
    exit;
}

$pendingUserId = (int)($_SESSION["pending_verification_user_id"] ?? 0);
$code = trim($_POST["code"] ?? "");

if ($pendingUserId <= 0) {
    $_SESSION["flash_error"] = "Aucune vérification en attente.";
    header("Location: ../login.php");
    exit;
}

if ($code === "" || !preg_match('/^\d{6}$/', $code)) {
    $_SESSION["flash_error"] = "Code invalide.";
    header("Location: ../verifier_email.php");
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM user
        WHERE id_user = ?
        LIMIT 1
    ");
    $stmt->execute([$pendingUserId]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION["flash_error"] = "Utilisateur introuvable.";
        header("Location: ../login.php");
        exit;
    }

    if ((int)($user["email_verifie"] ?? 0) === 1) {
        unset($_SESSION["pending_verification_user_id"], $_SESSION["pending_verification_email"]);
        $_SESSION["flash_success"] = "Ton email est déjà vérifié. Connecte-toi.";
        header("Location: ../login.php");
        exit;
    }

    if (($user["code_verification"] ?? "") !== $code) {
        $_SESSION["flash_error"] = "Code incorrect.";
        header("Location: ../verifier_email.php");
        exit;
    }

    if (empty($user["code_verification_expire"]) || strtotime($user["code_verification_expire"]) < time()) {
        $_SESSION["flash_error"] = "Le code a expiré. Demande un nouveau code.";
        header("Location: ../verifier_email.php");
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE user
        SET email_verifie = 1,
            code_verification = NULL,
            code_verification_expire = NULL,
            code_verification_renvoi_at = NULL
        WHERE id_user = ?
    ");
    $stmt->execute([(int)$user["id_user"]]);

    unset($_SESSION["pending_verification_user_id"], $_SESSION["pending_verification_email"]);

    $_SESSION["flash_success"] = "Email vérifié avec succès. Tu peux maintenant te connecter.";
    header("Location: ../login.php");
    exit;
} catch (Exception $e) {
    $_SESSION["flash_error"] = "Erreur serveur. Réessaye plus tard.";
    header("Location: ../verifier_email.php");
    exit;
}