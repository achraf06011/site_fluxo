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

  header("Location: ../admin/annonces.php");
  exit;
}

if ($id <= 0) back("Annonce invalide.");

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
  $a = $stmt->fetch();

  if (!$a) {
    $pdo->rollBack();
    back("Annonce introuvable.");
  }

  $stmt = $pdo->prepare("
    UPDATE annonce
    SET statut = 'ACTIVE',
        is_modifiee = 0
    WHERE id_annonce = ?
  ");
  $stmt->execute([$id]);

  // notif vendeur
  $stmtNotif = $pdo->prepare("
    INSERT INTO notification
      (id_user, type_notification, titre, contenu, lien, is_read, is_popup_seen)
    VALUES
      (?, 'ADMIN_ANNONCE_VALIDATED', ?, ?, ?, 0, 0)
  ");
  $stmtNotif->execute([
    (int)$a["id_vendeur"],
    "Annonce validée",
    "Ton annonce \"" . $a["titre"] . "\" a été validée par l’administrateur.",
    "annonce.php?id=" . (int)$a["id_annonce"]
  ]);

  $pdo->commit();
  back("Annonce validée.", true);

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  back("Erreur validation admin : " . $e->getMessage());
}