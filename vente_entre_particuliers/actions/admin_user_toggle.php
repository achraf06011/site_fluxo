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
$isActive = (int)($_POST["is_active"] ?? 1);

if ($id <= 0) { $_SESSION["flash_error"] = "ID invalide."; header("Location: ../admin/users.php"); exit; }
if (!in_array($isActive, [0,1], true)) $isActive = 1;

// éviter de te désactiver toi-même
if ($id === (int)currentUserId() && $isActive === 0) {
  $_SESSION["flash_error"] = "Tu ne peux pas désactiver ton propre compte.";
  header("Location: ../admin/users.php");
  exit;
}

try {
  // vérifier colonne
  $col = $pdo->query("SHOW COLUMNS FROM user LIKE 'is_active'")->fetch();
  if (!$col) {
    $_SESSION["flash_error"] = "Colonne is_active inexistante. Ajoute: ALTER TABLE user ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1;";
    header("Location: ../admin/users.php");
    exit;
  }

  $stmt = $pdo->prepare("UPDATE user SET is_active = ? WHERE id_user = ?");
  $stmt->execute([$isActive, $id]);

  $_SESSION["flash_success"] = "Utilisateur #$id " . ($isActive ? "réactivé" : "désactivé") . ".";
  header("Location: ../admin/users.php");
  exit;

} catch (Exception $e) {
  $_SESSION["flash_error"] = "Erreur serveur: " . $e->getMessage();
  header("Location: ../admin/users.php");
  exit;
}