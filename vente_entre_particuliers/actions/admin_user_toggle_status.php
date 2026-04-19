<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../admin/users.php");
  exit;
}

$id = (int)($_POST["id_user"] ?? 0);
if ($id <= 0) {
  $_SESSION["flash_error"] = "Utilisateur invalide.";
  header("Location: ../admin/users.php");
  exit;
}

if ($id === currentUserId()) {
  $_SESSION["flash_error"] = "Tu ne peux pas te bloquer toi-même.";
  header("Location: ../admin/users.php");
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT statut FROM user WHERE id_user = ? LIMIT 1");
  $stmt->execute([$id]);
  $u = $stmt->fetch();
  if (!$u) {
    $_SESSION["flash_error"] = "Utilisateur introuvable.";
    header("Location: ../admin/users.php");
    exit;
  }

  $new = (($u["statut"] ?? "ACTIVE") === "ACTIVE") ? "BLOQUE" : "ACTIVE";

  $stmt = $pdo->prepare("UPDATE user SET statut = ? WHERE id_user = ?");
  $stmt->execute([$new, $id]);

  $_SESSION["flash_success"] = "Statut utilisateur mis à jour : " . $new;
  header("Location: ../admin/users.php");
  exit;

} catch (Exception $e) {
  $_SESSION["flash_error"] = "Erreur serveur.";
  header("Location: ../admin/users.php");
  exit;
}