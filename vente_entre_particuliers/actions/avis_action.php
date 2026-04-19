<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../index.php");
  exit;
}

$userId   = currentUserId();
$orderId  = (int)($_POST["id_order"] ?? 0);
$sellerId = (int)($_POST["id_seller"] ?? 0);
$rating   = (int)($_POST["rating"] ?? 0);
$comment  = trim($_POST["comment"] ?? "");

function fail($msg, $orderId = 0, $sellerId = 0) {
  $_SESSION["flash_error"] = $msg;
  if ($orderId > 0 && $sellerId > 0) {
    header("Location: ../laisser_avis.php?order=" . (int)$orderId . "&seller=" . (int)$sellerId);
  } else {
    header("Location: ../index.php");
  }
  exit;
}

if ($orderId <= 0 || $sellerId <= 0) fail("Paramètres invalides.");
if ($rating < 1 || $rating > 5) fail("Note invalide (1 à 5).", $orderId, $sellerId);
if ($comment !== "" && mb_strlen($comment) > 1000) fail("Commentaire trop long (max 1000 caractères).", $orderId, $sellerId);

try {
  // 1) Vérifier commande user
  $stmt = $pdo->prepare("SELECT * FROM orders WHERE id_order = ? AND id_user = ? LIMIT 1");
  $stmt->execute([$orderId, $userId]);
  $o = $stmt->fetch();

  if (!$o) fail("Commande introuvable.", $orderId, $sellerId);
  if (($o["statut"] ?? "") !== "PAYE") fail("Commande non payée.", $orderId, $sellerId);

  // 2) Vérifier que cette commande contient une annonce de ce vendeur
  $stmt = $pdo->prepare("
    SELECT od.id_annonce
    FROM order_details od
    JOIN annonce a ON a.id_annonce = od.id_annonce
    WHERE od.id_order = ? AND a.id_vendeur = ?
    ORDER BY od.id_detail ASC
    LIMIT 1
  ");
  $stmt->execute([$orderId, $sellerId]);
  $row = $stmt->fetch();

  if (!$row) fail("Ce vendeur n'est pas lié à cette commande.", $orderId, $sellerId);

  $idAnnonce = (int)$row["id_annonce"];

  // 3) Anti doublon : même user + même commande + même vendeur
  $stmt = $pdo->prepare("
    SELECT id_review
    FROM review
    WHERE id_order = ? AND id_seller = ? AND id_user = ?
    LIMIT 1
  ");
  $stmt->execute([$orderId, $sellerId, $userId]);

  if ($stmt->fetch()) {
    fail("Tu as déjà noté ce vendeur pour cette commande.", $orderId, $sellerId);
  }

  // 4) Insert dans ta vraie structure review
  $stmt = $pdo->prepare("
    INSERT INTO review (note, commentaire, id_user, id_annonce, id_order, id_seller, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
  ");
  $stmt->execute([
    $rating,
    ($comment !== "" ? $comment : null),
    $userId,
    $idAnnonce,
    $orderId,
    $sellerId
  ]);

  $_SESSION["flash_success"] = "Avis envoyé avec succès.";
  header("Location: ../checkout_success.php?id=" . $orderId);
  exit;

} catch (Exception $e) {
  fail("Erreur serveur: " . $e->getMessage(), $orderId, $sellerId);
}