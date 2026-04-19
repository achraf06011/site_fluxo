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

  header("Location: ../admin/annonces.php");
  exit;
}

if ($id <= 0) back("Annonce invalide.");

try {
  $pdo->beginTransaction();

  $stmt = $pdo->prepare("
    SELECT a.id_annonce, a.titre, a.id_vendeur, u.nom AS vendeur_nom
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
    SET statut = 'REFUSEE'
    WHERE id_annonce = ?
  ");
  $stmt->execute([$id]);

  $contenu = "Ton annonce \"" . $a["titre"] . "\" a été refusée par l’administrateur.";
  if ($reason !== "") {
    $contenu .= " Raison : " . $reason;
  }

  // notif vendeur
  $stmtNotif = $pdo->prepare("
    INSERT INTO notification
      (id_user, type_notification, titre, contenu, lien, is_read, is_popup_seen)
    VALUES
      (?, 'ADMIN_ANNONCE_REFUSED', ?, ?, ?, 0, 0)
  ");
  $stmtNotif->execute([
    (int)$a["id_vendeur"],
    "Annonce refusée",
    $contenu,
    "mes_annonces.php"
  ]);

  $pdo->commit();
  back("Annonce refusée.", true);

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  back("Erreur refus admin : " . $e->getMessage());
}