<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../messages.php");
  exit;
}

$userId = currentUserId();

$idAnnonce = (int)($_POST["id_annonce"] ?? 0);
$toId      = (int)($_POST["to"] ?? 0);
$contenu   = trim($_POST["contenu"] ?? "");

function backToThread(int $annonceId, int $toId, string $msg, bool $ok = false): void {
  if ($ok) {
    $_SESSION["flash_success"] = $msg;
  } else {
    $_SESSION["flash_error"] = $msg;
  }
  header("Location: ../messages.php?annonce=" . $annonceId . "&to=" . $toId);
  exit;
}

if ($idAnnonce <= 0 || $toId <= 0) {
  header("Location: ../messages.php");
  exit;
}

if ($contenu === "" || mb_strlen($contenu) < 1) {
  backToThread($idAnnonce, $toId, "Message vide.");
}

if (mb_strlen($contenu) > 2000) {
  backToThread($idAnnonce, $toId, "Message trop long (max 2000 caractères).");
}

if ($toId === $userId) {
  backToThread($idAnnonce, $toId, "Tu ne peux pas t’envoyer un message à toi-même.");
}

try {
  // vérifier annonce
  $stmt = $pdo->prepare("
    SELECT id_annonce, titre, mode_vente, statut, id_vendeur
    FROM annonce
    WHERE id_annonce = ?
    LIMIT 1
  ");
  $stmt->execute([$idAnnonce]);
  $annonce = $stmt->fetch();

  if (!$annonce) {
    backToThread($idAnnonce, $toId, "Annonce introuvable.");
  }

  // vérifier destinataire
  $stmt = $pdo->prepare("
    SELECT id_user, nom
    FROM user
    WHERE id_user = ?
    LIMIT 1
  ");
  $stmt->execute([$toId]);
  $dest = $stmt->fetch();

  if (!$dest) {
    backToThread($idAnnonce, $toId, "Destinataire introuvable.");
  }

  // mode discussion autorisé
  $mode = (string)($annonce["mode_vente"] ?? "");
  $canChat = in_array($mode, ["POSSIBILITE_CONTACTE", "LES_DEUX"], true);

  if (!$canChat) {
    backToThread($idAnnonce, $toId, "Cette annonce n’autorise pas la discussion.");
  }

  // insertion message
  $stmt = $pdo->prepare("
    INSERT INTO message (
      contenu,
      date_envoi,
      id_expediteur,
      id_destinataire,
      id_annonce,
      is_lu
    )
    VALUES (?, NOW(), ?, ?, ?, 0)
  ");
  $stmt->execute([
    $contenu,
    $userId,
    $toId,
    $idAnnonce
  ]);

  // notification popup nouveau message
  $titreNotif = "Nouveau message";
  $contenuNotif = "Tu as reçu un nouveau message à propos de : " . ($annonce["titre"] ?? ("Annonce #" . $idAnnonce));

  $stmtNotif = $pdo->prepare("
    INSERT INTO notification (
      id_user,
      type_notification,
      titre,
      contenu,
      lien,
      is_read,
      created_at,
      is_popup_seen
    )
    VALUES (?, ?, ?, ?, ?, 0, NOW(), 0)
  ");
  $stmtNotif->execute([
    $toId,
    "NEW_MESSAGE",
    $titreNotif,
    $contenuNotif,
    "messages.php?annonce=" . $idAnnonce . "&to=" . $userId
  ]);

  backToThread($idAnnonce, $toId, "Message envoyé.", true);

} catch (Exception $e) {
  backToThread($idAnnonce, $toId, "Erreur serveur.");
}