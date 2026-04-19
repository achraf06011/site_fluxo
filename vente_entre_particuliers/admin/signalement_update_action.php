<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (currentUserRole() !== "ADMIN") {
  header("Location: ../index.php");
  exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: signalements.php");
  exit;
}

$idSignalement = (int)($_POST["id_signalement"] ?? 0);
$newStatut = trim((string)($_POST["new_statut"] ?? ""));
$adminId = (int)currentUserId();

function back(string $msg, bool $ok = false): void {
  if ($ok) {
    $_SESSION["flash_success"] = $msg;
  } else {
    $_SESSION["flash_error"] = $msg;
  }
  header("Location: signalements.php");
  exit;
}

if ($idSignalement <= 0) {
  back("Signalement invalide.");
}

if (!in_array($newStatut, ["TRAITE", "REJETE"], true)) {
  back("Statut invalide.");
}

try {
  $stmt = $pdo->prepare("
    UPDATE signalement
    SET statut = ?, treated_at = NOW(), treated_by = ?
    WHERE id_signalement = ?
  ");
  $stmt->execute([$newStatut, $adminId, $idSignalement]);

  back("Signalement mis à jour avec succès.", true);

} catch (Exception $e) {
  back("Erreur : " . $e->getMessage());
}