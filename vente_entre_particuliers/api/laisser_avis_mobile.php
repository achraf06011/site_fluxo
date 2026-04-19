<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$userId   = (int)($_GET["user_id"] ?? 0);
$orderId  = (int)($_GET["order"] ?? 0);
$sellerId = (int)($_GET["seller"] ?? 0);

if ($userId <= 0 || $orderId <= 0 || $sellerId <= 0) {
  echo json_encode([
    "ok" => false,
    "message" => "Paramètres invalides."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $stmt = $pdo->prepare("
    SELECT *
    FROM orders
    WHERE id_order = ? AND id_user = ?
    LIMIT 1
  ");
  $stmt->execute([$orderId, $userId]);
  $o = $stmt->fetch();

  if (!$o) {
    echo json_encode([
      "ok" => false,
      "message" => "Commande introuvable."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if (($o["statut"] ?? "") !== "PAYE") {
    echo json_encode([
      "ok" => false,
      "message" => "Tu peux laisser un avis seulement après paiement."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt = $pdo->prepare("
    SELECT COUNT(*) AS c
    FROM order_details od
    JOIN annonce a ON a.id_annonce = od.id_annonce
    WHERE od.id_order = ? AND a.id_vendeur = ?
  ");
  $stmt->execute([$orderId, $sellerId]);
  $has = (int)($stmt->fetch()["c"] ?? 0);

  if ($has <= 0) {
    echo json_encode([
      "ok" => false,
      "message" => "Ce vendeur n'est pas lié à cette commande."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt = $pdo->prepare("
    SELECT id_review
    FROM review
    WHERE id_order = ? AND id_seller = ? AND id_user = ?
    LIMIT 1
  ");
  $stmt->execute([$orderId, $sellerId, $userId]);

  if ($stmt->fetch()) {
    echo json_encode([
      "ok" => false,
      "message" => "Tu as déjà noté ce vendeur pour cette commande."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt = $pdo->prepare("
    SELECT id_user, nom, email
    FROM user
    WHERE id_user = ?
    LIMIT 1
  ");
  $stmt->execute([$sellerId]);
  $seller = $stmt->fetch();

  if (!$seller) {
    echo json_encode([
      "ok" => false,
      "message" => "Vendeur introuvable."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo json_encode([
    "ok" => true,
    "seller" => [
      "id_user" => (int)$seller["id_user"],
      "nom" => (string)$seller["nom"],
      "email" => (string)$seller["email"],
    ],
    "order" => [
      "id_order" => (int)$o["id_order"],
      "statut" => (string)$o["statut"],
    ],
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "message" => "Erreur serveur."
  ], JSON_UNESCAPED_UNICODE);
}