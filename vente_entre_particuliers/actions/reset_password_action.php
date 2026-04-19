<?php
require_once "../config/db.php";
require_once "../config/auth.php";

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../login.php");
  exit;
}

$token = trim($_POST["token"] ?? "");
$password = (string)($_POST["password"] ?? "");
$password2 = (string)($_POST["password2"] ?? "");

function failBack(string $token, string $msg): void {
  $_SESSION["flash_error"] = $msg;
  header("Location: ../reset_password.php?token=" . urlencode($token));
  exit;
}

if ($token === "") {
  $_SESSION["flash_error"] = "Lien invalide.";
  header("Location: ../login.php");
  exit;
}

if (strlen($password) < 6) {
  failBack($token, "Mot de passe trop court (minimum 6 caractères).");
}

if ($password !== $password2) {
  failBack($token, "Les mots de passe ne correspondent pas.");
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
  $user = $stmt->fetch();

  if (!$user) {
    $_SESSION["flash_error"] = "Lien invalide ou expiré.";
    header("Location: ../login.php");
    exit;
  }

  if (empty($user["reset_token_expire"]) || strtotime($user["reset_token_expire"]) < time()) {
    $_SESSION["flash_error"] = "Lien expiré. Refais une demande.";
    header("Location: ../forgot_password.php");
    exit;
  }

  $hash = password_hash($password, PASSWORD_DEFAULT);

  $stmt = $pdo->prepare("
    UPDATE user
    SET password = ?, reset_token = NULL, reset_token_expire = NULL
    WHERE id_user = ?
  ");
  $stmt->execute([$hash, (int)$user["id_user"]]);

  $_SESSION["flash_success"] = "Mot de passe modifié avec succès. Tu peux maintenant te connecter.";
  header("Location: ../login.php");
  exit;

} catch (Exception $e) {
  failBack($token, "Erreur serveur. Réessaie plus tard.");
}