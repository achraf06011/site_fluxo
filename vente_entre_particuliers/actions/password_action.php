<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();
if ($_SERVER["REQUEST_METHOD"] !== "POST") { header("Location: ../profil.php"); exit; }

$userId = currentUserId();
$old = (string)($_POST["old_password"] ?? "");
$new = (string)($_POST["new_password"] ?? "");
$new2 = (string)($_POST["new_password2"] ?? "");

function fail($m){ $_SESSION["flash_error"]=$m; header("Location: ../profil.php"); exit; }

if ($new === "" || strlen($new) < 6) fail("Nouveau mot de passe trop court (min 6).");
if ($new !== $new2) fail("Confirmation incorrecte.");

try {
  $st = $pdo->prepare("SELECT password FROM user WHERE id_user = ? LIMIT 1");
  $st->execute([$userId]);
  $u = $st->fetch();
  if (!$u) fail("Utilisateur introuvable.");

  if (!password_verify($old, $u["password"])) fail("Ancien mot de passe incorrect.");

  $hash = password_hash($new, PASSWORD_DEFAULT);
  $st = $pdo->prepare("UPDATE user SET password = ? WHERE id_user = ?");
  $st->execute([$hash, $userId]);

  $_SESSION["flash_success"] = "Mot de passe modifié.";
  header("Location: ../profil.php");
  exit;
} catch (Exception $e) {
  fail("Erreur serveur: " . $e->getMessage());
}