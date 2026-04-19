<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../admin/annonces.php");
  exit;
}

$id = (int)($_POST["id_annonce"] ?? 0);

function back(string $msg, bool $ok = false): void {
  if ($ok) {
    $_SESSION["flash_success"] = $msg;
  } else {
    $_SESSION["flash_error"] = $msg;
  }

  header("Location: ../admin/annonces.php");
  exit;
}

if ($id <= 0) {
  back("Annonce invalide.");
}

try {
  // vérifier existence
  $stmt = $pdo->prepare("SELECT id_annonce FROM annonce WHERE id_annonce = ? LIMIT 1");
  $stmt->execute([$id]);
  $ann = $stmt->fetch();

  if (!$ann) {
    back("Annonce introuvable.");
  }

  // réactiver directement
  $stmt = $pdo->prepare("
    UPDATE annonce
    SET statut = 'ACTIVE'
    WHERE id_annonce = ?
    LIMIT 1
  ");
  $stmt->execute([$id]);

  back("Annonce réactivée avec succès.", true);

} catch (Exception $e) {
  back("Erreur réactivation admin : " . $e->getMessage());
}