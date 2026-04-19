<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  echo json_encode([
    "ok" => false,
    "message" => "Méthode non autorisée."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$userId   = (int)($data["user_id"] ?? 0);
$orderId  = (int)($data["id_order"] ?? 0);
$sellerId = (int)($data["id_seller"] ?? 0);
$rating   = (int)($data["rating"] ?? 0);
$comment  = trim((string)($data["comment"] ?? ""));

if ($userId <= 0 || $orderId <= 0 || $sellerId <= 0) {
  echo json_encode([
    "ok" => false,
    "message" => "Paramètres invalides."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($rating < 1 || $rating > 5) {
  echo json_encode([
    "ok" => false,
    "message" => "Note invalide (1 à 5)."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($comment !== "" && mb_strlen($comment) > 1000) {
  echo json_encode([
    "ok" => false,
    "message" => "Commentaire trop long (max 1000 caractères)."
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
      "message" => "Commande non payée."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

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

  if (!$row) {
    echo json_encode([
      "ok" => false,
      "message" => "Ce vendeur n'est pas lié à cette commande."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $idAnnonce = (int)$row["id_annonce"];

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
    INSERT INTO review (
      note,
      commentaire,
      id_user,
      id_annonce,
      id_order,
      id_seller,
      created_at
    )
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

  echo json_encode([
    "ok" => true,
    "message" => "Avis envoyé avec succès."
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "message" => "Erreur serveur: " . $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}