<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../index.php");
  exit;
}

$currentUserId = (int)currentUserId();
$idAnnonce = (int)($_POST["id_annonce"] ?? 0);
$motif = trim((string)($_POST["motif"] ?? ""));
$description = trim((string)($_POST["description"] ?? ""));

function back(string $msg, int $idAnnonce = 0, bool $ok = false): void {
  if ($ok) {
    $_SESSION["flash_success"] = $msg;
  } else {
    $_SESSION["flash_error"] = $msg;
  }

  if ($idAnnonce > 0) {
    header("Location: ../annonce.php?id=" . $idAnnonce);
  } else {
    header("Location: ../index.php");
  }
  exit;
}

if ($idAnnonce <= 0) {
  back("Annonce invalide.");
}

$allowedMotifs = [
  "ARNAQUE",
  "FAUSSE_ANNONCE",
  "CONTENU_INTERDIT",
  "PRIX_SUSPECT",
  "SPAM",
  "AUTRE"
];

if (!in_array($motif, $allowedMotifs, true)) {
  back("Motif invalide.", $idAnnonce);
}

try {
  $stmt = $pdo->prepare("
    SELECT a.id_annonce, a.id_vendeur, a.titre, a.statut
    FROM annonce a
    WHERE a.id_annonce = ?
    LIMIT 1
  ");
  $stmt->execute([$idAnnonce]);
  $annonce = $stmt->fetch();

  if (!$annonce) {
    back("Annonce introuvable.", $idAnnonce);
  }

  if ((int)$annonce["id_vendeur"] === $currentUserId) {
    back("Tu ne peux pas signaler ta propre annonce.", $idAnnonce);
  }

  $stmt = $pdo->prepare("
    SELECT id_signalement
    FROM signalement
    WHERE id_annonce = ?
      AND id_user = ?
      AND statut = 'EN_ATTENTE'
    LIMIT 1
  ");
  $stmt->execute([$idAnnonce, $currentUserId]);
  $already = $stmt->fetch();

  if ($already) {
    back("Tu as déjà signalé cette annonce.", $idAnnonce);
  }

  $pdo->beginTransaction();

  $stmt = $pdo->prepare("
    INSERT INTO signalement (
      id_annonce,
      id_user,
      motif,
      description,
      statut,
      created_at
    )
    VALUES (?, ?, ?, ?, 'EN_ATTENTE', NOW())
  ");
  $stmt->execute([
    $idAnnonce,
    $currentUserId,
    $motif,
    $description !== "" ? $description : null
  ]);

  $signalementId = (int)$pdo->lastInsertId();

  try {
    $stmtAdmins = $pdo->query("SELECT id_user FROM user WHERE role = 'ADMIN'");
    $admins = $stmtAdmins->fetchAll();

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
      VALUES (?, 'ADMIN_SIGNALEMENT', ?, ?, ?, 0, NOW(), 0)
    ");

    $titreNotif = "Nouveau signalement";
    $contenuNotif = "Un utilisateur a signalé l'annonce \"" . $annonce["titre"] . "\".";
    $lienNotif = "../admin/signalements.php";

    foreach ($admins as $admin) {
      $stmtNotif->execute([
        (int)$admin["id_user"],
        $titreNotif,
        $contenuNotif,
        $lienNotif
      ]);
    }
  } catch (Exception $e) {
  }
try {
  $stmtUserNotif = $pdo->prepare("
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
    VALUES (?, 'SIGNALEMENT_ENVOYE', ?, ?, ?, 0, NOW(), 0)
  ");
  $stmtUserNotif->execute([
    $currentUserId,
    "Signalement envoyé",
    "Ton signalement pour l'annonce \"" . $annonce["titre"] . "\" a bien été envoyé.",
    "annonce.php?id=" . $idAnnonce
  ]);
} catch (Exception $e) {
}
  $pdo->commit();

  back("Signalement envoyé avec succès.", $idAnnonce, true);

} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  back("Erreur lors du signalement : " . $e->getMessage(), $idAnnonce);
}