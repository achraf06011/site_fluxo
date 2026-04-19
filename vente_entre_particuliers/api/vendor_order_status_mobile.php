<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$userId = (int)($data["user_id"] ?? 0);
$orderId = (int)($data["id_order"] ?? 0);
$statutLivraison = strtoupper(trim((string)($data["statut_livraison"] ?? "")));

if ($userId <= 0 || $orderId <= 0 || $statutLivraison === "") {
  echo json_encode([
    "ok" => false,
    "message" => "Paramètres invalides."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $stmt = $pdo->prepare("
    SELECT
      o.id_order,
      o.mode_reception,
      COALESCE(p.statut, 'EN_ATTENTE') AS paiement_statut
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
    echo json_encode([
      "ok" => false,
      "message" => "Accès refusé."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if (strtoupper((string)$row["paiement_statut"]) !== "ACCEPTE") {
    echo json_encode([
      "ok" => false,
      "message" => "Le paiement doit être accepté."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $modeReception = strtoupper(trim((string)($row["mode_reception"] ?? "PICKUP")));

  $allowed = ($modeReception === "LIVRAISON")
    ? ["PREPARATION","EN_TRANSIT","ARRIVEE_VILLE","EN_LIVRAISON","LIVREE"]
    : ["PREPARATION","DISPONIBLE","TERMINEE"];

  if (!in_array($statutLivraison, $allowed, true)) {
    echo json_encode([
      "ok" => false,
      "message" => "Statut livraison invalide."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt = $pdo->prepare("
    UPDATE orders
    SET statut_livraison = ?, statut_livraison_updated_at = NOW(), buyer_seen = 0
    WHERE id_order = ?
  ");
  $stmt->execute([$statutLivraison, $orderId]);

  echo json_encode([
    "ok" => true,
    "message" => "Suivi mis à jour."
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "message" => "Erreur serveur."
  ], JSON_UNESCAPED_UNICODE);
}