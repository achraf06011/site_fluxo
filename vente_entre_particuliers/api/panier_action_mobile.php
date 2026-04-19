<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$input = json_decode(file_get_contents("php://input"), true);

$userId = (int)($input["user_id"] ?? 0);
$action = trim((string)($input["action"] ?? "add"));
$id_annonce = (int)($input["id_annonce"] ?? 0);
$qty = (int)($input["qty"] ?? 1);
if ($qty < 1) $qty = 1;

if ($userId <= 0) {
  echo json_encode([
    "ok" => false,
    "message" => "Utilisateur invalide."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE id_user = ? LIMIT 1");
  $stmt->execute([$userId]);
  $p = $stmt->fetch();

  if (!$p) {
    $stmt = $pdo->prepare("INSERT INTO panier (id_user) VALUES (?)");
    $stmt->execute([$userId]);
    $panierId = (int)$pdo->lastInsertId();
  } else {
    $panierId = (int)$p["id_panier"];
  }

  if ($action === "clear") {
    $pdo->prepare("DELETE FROM panier_item WHERE id_panier = ?")->execute([$panierId]);

    echo json_encode([
      "ok" => true,
      "message" => "Panier vidé."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($id_annonce <= 0) {
    echo json_encode([
      "ok" => false,
      "message" => "Produit invalide."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt = $pdo->prepare("
    SELECT id_annonce, stock, mode_vente, statut
    FROM annonce
    WHERE id_annonce = ?
    LIMIT 1
  ");
  $stmt->execute([$id_annonce]);
  $a = $stmt->fetch();

  if (!$a || $a["statut"] !== "ACTIVE") {
    echo json_encode([
      "ok" => false,
      "message" => "Annonce introuvable ou inactive."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if (!in_array($a["mode_vente"], ["PAIEMENT_DIRECT", "LES_DEUX"], true)) {
    echo json_encode([
      "ok" => false,
      "message" => "Ce produit n'est pas disponible en paiement direct."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stock = (int)$a["stock"];
  if ($stock <= 0) {
    echo json_encode([
      "ok" => false,
      "message" => "Stock insuffisant."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt = $pdo->prepare("
    SELECT id_panier_item, quantity
    FROM panier_item
    WHERE id_panier = ? AND id_annonce = ?
    LIMIT 1
  ");
  $stmt->execute([$panierId, $id_annonce]);
  $item = $stmt->fetch();

  if ($action === "add") {
    if ($item) {
      $newQty = (int)$item["quantity"] + 1;
      if ($newQty > $stock) $newQty = $stock;

      $pdo->prepare("UPDATE panier_item SET quantity = ? WHERE id_panier_item = ?")
          ->execute([$newQty, (int)$item["id_panier_item"]]);
    } else {
      $addQty = min(1, $stock);

      $pdo->prepare("
        INSERT INTO panier_item (id_panier, id_annonce, quantity)
        VALUES (?, ?, ?)
      ")->execute([$panierId, $id_annonce, $addQty]);
    }

    echo json_encode([
      "ok" => true,
      "message" => "Ajouté au panier."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($action === "update") {
    if (!$item) {
      echo json_encode([
        "ok" => false,
        "message" => "Produit introuvable dans le panier."
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $newQty = min(max(1, $qty), $stock);

    $pdo->prepare("UPDATE panier_item SET quantity = ? WHERE id_panier_item = ?")
        ->execute([$newQty, (int)$item["id_panier_item"]]);

    echo json_encode([
      "ok" => true,
      "message" => "Quantité mise à jour."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($action === "delete") {
    $pdo->prepare("DELETE FROM panier_item WHERE id_panier = ? AND id_annonce = ?")
        ->execute([$panierId, $id_annonce]);

    echo json_encode([
      "ok" => true,
      "message" => "Article supprimé."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo json_encode([
    "ok" => false,
    "message" => "Action inconnue."
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "message" => "Erreur serveur."
  ], JSON_UNESCAPED_UNICODE);
}