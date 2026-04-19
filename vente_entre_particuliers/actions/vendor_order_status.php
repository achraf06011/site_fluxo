<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../mes_ventes.php");
  exit;
}

$userId = currentUserId();
$orderId = (int)($_POST["id_order"] ?? 0);
$statutLivraison = strtoupper(trim((string)($_POST["statut_livraison"] ?? "")));

function fail($msg, $orderId = 0) {
  $_SESSION["flash_error"] = $msg;
  header("Location: ../vente.php?id=" . (int)$orderId);
  exit;
}

if ($orderId <= 0) {
  fail("Commande invalide.", $orderId);
}

$stmt = $pdo->prepare("
  SELECT 
    o.id_order,
    o.id_user,
    o.mode_reception,
    o.statut_livraison,
    COALESCE(p.statut, 'EN_ATTENTE') AS pay_statut
  FROM orders o
  JOIN order_details od ON od.id_order = o.id_order
  JOIN annonce a ON a.id_annonce = od.id_annonce
  LEFT JOIN paiement p ON p.id_order = o.id_order
  WHERE o.id_order = ? AND a.id_vendeur = ?
  LIMIT 1
");
$stmt->execute([$orderId, $userId]);
$row = $stmt->fetch();

if (!$row) {
  fail("Accès refusé.", $orderId);
}

if (($row["pay_statut"] ?? "EN_ATTENTE") !== "ACCEPTE") {
  fail("Paiement non accepté.", $orderId);
}

$buyerId = (int)($row["id_user"] ?? 0);
$mode = strtoupper(trim((string)($row["mode_reception"] ?? "PICKUP")));
$currentStatut = strtoupper(trim((string)($row["statut_livraison"] ?? "")));

if ($mode === "LIVRAISON") {
  $allowed = ["PREPARATION", "EN_TRANSIT", "ARRIVEE_VILLE", "EN_LIVRAISON", "LIVREE"];
} else {
  $allowed = ["PREPARATION", "DISPONIBLE", "TERMINEE"];
}

if (!in_array($statutLivraison, $allowed, true)) {
  fail("Statut livraison invalide.", $orderId);
}

if ($currentStatut === $statutLivraison) {
  $_SESSION["flash_success"] = "Aucun changement de statut.";
  header("Location: ../vente.php?id=" . $orderId);
  exit;
}

try {
  $pdo->beginTransaction();

  $stmt = $pdo->prepare("
    UPDATE orders
    SET
      statut_livraison = ?,
      statut_livraison_updated_at = NOW(),
      seller_seen = 1,
      buyer_seen = 0
    WHERE id_order = ?
  ");
  $stmt->execute([$statutLivraison, $orderId]);

  // Notification acheteur
  if ($buyerId > 0) {
    $titreNotif = "Mise à jour de commande";
    $contenuNotif = "Ta commande #".$orderId." est maintenant : ".$statutLivraison.".";

    if ($statutLivraison === "LIVREE") {
      $titreNotif = "Commande livrée";
      $contenuNotif = "Ta commande #".$orderId." a été livrée.";
    } elseif ($statutLivraison === "TERMINEE") {
      $titreNotif = "Commande terminée";
      $contenuNotif = "Ta commande #".$orderId." est terminée.";
    } elseif ($statutLivraison === "DISPONIBLE") {
      $titreNotif = "Commande disponible";
      $contenuNotif = "Ta commande #".$orderId." est prête pour la remise en main propre.";
    }

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
      $buyerId,
      "ORDER_UPDATE",
      $titreNotif,
      $contenuNotif,
      "suivi_commande.php?id=" . $orderId
    ]);
  }

  $pdo->commit();

  $_SESSION["flash_success"] = "Statut de livraison mis à jour.";
  header("Location: ../vente.php?id=" . $orderId);
  exit;

} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  fail("Erreur serveur : " . $e->getMessage(), $orderId);
}