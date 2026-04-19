<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/mail.php";

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../forgot_password.php");
  exit;
}

$email = trim($_POST["email"] ?? "");

$_SESSION["flash_old"] = [
  "email" => $email
];

function doneMessage(): void {
  $_SESSION["flash_success"] = "Si cet email existe, un lien de réinitialisation a été envoyé.";
  unset($_SESSION["flash_old"]);
  header("Location: ../forgot_password.php");
  exit;
}

if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $_SESSION["flash_error"] = "Email invalide.";
  header("Location: ../forgot_password.php");
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT id_user, nom, email, statut, email_verifie FROM user WHERE email = ? LIMIT 1");
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  if (!$user) {
    doneMessage();
  }

  if (($user["statut"] ?? "ACTIVE") === "BLOQUE") {
    doneMessage();
  }

  if ((int)($user["email_verifie"] ?? 0) !== 1) {
    doneMessage();
  }

  $token = bin2hex(random_bytes(32));
  $tokenHash = hash("sha256", $token);
  $expireAt = date("Y-m-d H:i:s", time() + 60 * 60); // 1 heure

  $stmt = $pdo->prepare("
    UPDATE user
    SET reset_token = ?, reset_token_expire = ?
    WHERE id_user = ?
  ");
  $stmt->execute([$tokenHash, $expireAt, (int)$user["id_user"]]);

  $baseUrl = ((isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http")
    . "://"
    . $_SERVER["HTTP_HOST"]
    . rtrim(basePath(), "/");

  $resetLink = $baseUrl . "/reset_password.php?token=" . urlencode($token);

  sendResetPasswordEmail($user["email"], $user["nom"], $resetLink);

  doneMessage();

} catch (Exception $e) {
  $_SESSION["flash_error"] = "Erreur serveur. Réessaie plus tard.";
  header("Location: ../forgot_password.php");
  exit;
}