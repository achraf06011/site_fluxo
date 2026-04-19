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
    SELECT a.id_annonce, a.titre, a.id_vendeur, a.statut, u.nom AS vendeur_nom
    FROM annonce a
    JOIN user u ON u.id_user = a.id_vendeur
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
    SET statut = 'ACTIVE',
        is_modified = 0
    WHERE id_annonce = ?
      AND statut = 'EN_ATTENTE_VALIDATION'
  ");
  $stmt->execute([$id]);

  if ($stmt->rowCount() <= 0) {
    $pdo->rollBack();
    back("Annonce non modifiée.");
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
      "ANNONCE_VALIDATED",
      "Annonce validée",
      "Ton annonce \"" . $annonce["titre"] . "\" a été validée par l'administrateur.",
      "mes_annonces.php"
    ]);
  } catch (Exception $e) {
    // on ne bloque pas la validation si la notif échoue
  }

  $pdo->commit();
  back("Annonce #".$id." validée ✅", true);

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $_SESSION["flash_error"] = "Erreur serveur : " . $e->getMessage();
  header("Location: ../admin/annonces.php");
  exit;
}