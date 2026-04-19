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
$reason = trim($_POST["reason"] ?? "");

function back(string $msg, bool $ok = false): void {
  if ($ok) $_SESSION["flash_success"] = $msg;
  else $_SESSION["flash_error"] = $msg;

  header("Location: ../admin/annonces.php?statut=EN_ATTENTE_VALIDATION");
  exit;
}

if ($id <= 0) {
  back("ID annonce invalide.");
}

try {
  $pdo->beginTransaction();

  $stmt = $pdo->prepare("
    SELECT a.id_annonce, a.titre, a.id_vendeur, a.statut
    FROM annonce a
    WHERE a.id_annonce = ?
    LIMIT 1
  ");
  $stmt->execute([$id]);
  $annonce = $stmt->fetch();

  if (!$annonce) {
    $pdo->rollBack();
    back("Annonce introuvable.");
  }

  $stmt = $pdo->prepare("
    UPDATE annonce
    SET statut = 'REFUSEE'
    WHERE id_annonce = ?
      AND statut = 'EN_ATTENTE_VALIDATION'
  ");
  $stmt->execute([$id]);

  if ($stmt->rowCount() <= 0) {
    $pdo->rollBack();
    back("Annonce non modifiée.");
  }

  $contenu = "Ton annonce \"" . $annonce["titre"] . "\" a été refusée par l'administrateur.";
  if ($reason !== "") {
    $contenu .= " Raison : " . $reason;
  }

  // notification vendeur
  try {
    $stmtNotif = $pdo->prepare("
      INSERT INTO notification
        (id_user, type_notification, titre, contenu, lien, is_read, is_popup_seen, created_at)
      VALUES
        (?, ?, ?, ?, ?, 0, 0, NOW())
    ");
    $stmtNotif->execute([
      (int)$annonce["id_vendeur"],
      "ANNONCE_REFUSED",
      "Annonce refusée",
      $contenu,
      "mes_annonces.php"
    ]);
  } catch (Exception $e) {
      // on ne bloque pas le refus si la notif échoue
  }

  $pdo->commit();
  back("Annonce #".$id." refusée.", true);

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $_SESSION["flash_error"] = "Erreur serveur : " . $e->getMessage();
  header("Location: ../admin/annonces.php");
  exit;
}