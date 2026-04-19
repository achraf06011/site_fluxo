<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/mail.php";

if (session_status() === PHP_SESSION_NONE) session_start();

$pendingUserId = (int)($_SESSION["pending_verification_user_id"] ?? 0);

if ($pendingUserId <= 0) {
    $_SESSION["flash_error"] = "Aucune vérification en attente.";
    header("Location: ../login.php");
    exit;
}

function generateVerificationCode(): string {
    return (string) random_int(100000, 999999);
}

try {
    $stmt = $pdo->prepare("
        SELECT id_user, nom, email, email_verifie, code_verification_renvoi_at
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
        $_SESSION["flash_success"] = "Ton email est déjà vérifié.";
        header("Location: ../login.php");
        exit;
    }

    $now = time();
    $resendAt = !empty($user["code_verification_renvoi_at"]) ? strtotime($user["code_verification_renvoi_at"]) : 0;

    if ($resendAt > $now) {
        $seconds = $resendAt - $now;
        $_SESSION["flash_error"] = "Patiente encore {$seconds} seconde(s) avant de renvoyer un code.";
        header("Location: ../verifier_email.php");
        exit;
    }

    $code = generateVerificationCode();
    $expireAt = date("Y-m-d H:i:s", $now + 15 * 60);
    $nextResendAt = date("Y-m-d H:i:s", $now + 60);

    $stmt = $pdo->prepare("
        UPDATE user
        SET code_verification = ?, code_verification_expire = ?, code_verification_renvoi_at = ?
        WHERE id_user = ?
    ");
    $stmt->execute([$code, $expireAt, $nextResendAt, (int)$user["id_user"]]);

    $sent = sendVerificationEmail($user["email"], $user["nom"], $code);

    if ($sent) {
        $_SESSION["flash_success"] = "Un nouveau code a été envoyé.";
    } else {
        $_SESSION["flash_error"] = "Impossible d’envoyer le code pour le moment.";
    }

    header("Location: ../verifier_email.php");
    exit;
} catch (Exception $e) {
    $_SESSION["flash_error"] = "Erreur serveur. Réessaye plus tard.";
    header("Location: ../verifier_email.php");
    exit;
}