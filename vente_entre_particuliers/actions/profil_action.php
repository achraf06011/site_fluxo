<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();
if ($_SERVER["REQUEST_METHOD"] !== "POST") { header("Location: ../profil.php"); exit; }

$userId = currentUserId();

$nom = trim($_POST["nom"] ?? "");
$email = trim($_POST["email"] ?? "");

function fail($m){ $_SESSION["flash_error"]=$m; header("Location: ../profil.php"); exit; }

if ($nom === "" || mb_strlen($nom) < 2) fail("Nom invalide.");
if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) fail("Email invalide.");

try {
  // Email unique (hors moi)
  $st = $pdo->prepare("SELECT id_user FROM user WHERE email = ? AND id_user <> ? LIMIT 1");
  $st->execute([$email, $userId]);
  if ($st->fetch()) fail("Cet email est déjà utilisé.");

  $st = $pdo->prepare("UPDATE user SET nom = ?, email = ? WHERE id_user = ?");
  $st->execute([$nom, $email, $userId]);

  // mettre à jour session
  $_SESSION["user"]["nom"] = $nom;
  $_SESSION["user"]["email"] = $email;

  $_SESSION["flash_success"] = "Profil mis à jour.";
  header("Location: ../profil.php");
  exit;
} catch (Exception $e) {
  fail("Erreur serveur: " . $e->getMessage());
}