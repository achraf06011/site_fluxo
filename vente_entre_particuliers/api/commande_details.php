<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$userId = (int)($_GET["user_id"] ?? 0);
$orderId = (int)($_GET["id"] ?? 0);

if ($userId <= 0 || $orderId <= 0) {
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
  $o = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$o) {
    echo json_encode([
      "ok" => false,
      "message" => "Commande introuvable."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt = $pdo->prepare("
    SELECT
      od.quantite,
      od.prix_unitaire,
      a.id_annonce,
      a.titre,
      a.id_vendeur,
      u.nom AS vendeur_nom
    FROM order_details od
    JOIN annonce a ON a.id_annonce = od.id_annonce
    JOIN user u ON u.id_user = a.id_vendeur
    WHERE od.id_order = ?
  ");
  $stmt->execute([$orderId]);
  $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $stmt = $pdo->prepare("
    SELECT *
    FROM paiement
    WHERE id_order = ?
    LIMIT 1
  ");
  $stmt->execute([$orderId]);
  $pay = $stmt->fetch(PDO::FETCH_ASSOC);

  $vendors = [];
  foreach ($details as $d) {
    $vid = (int)$d["id_vendeur"];
    if (!isset($vendors[$vid])) {
      $vendors[$vid] = [
        "id_seller" => $vid,
        "vendeur_nom" => $d["vendeur_nom"],
        "annonces" => [],
      ];
    }
    $vendors[$vid]["annonces"][] = [
      "id_annonce" => (int)$d["id_annonce"],
      "titre" => $d["titre"],
    ];
  }

  $reviewedSellers = [];
  try {
    $stmt = $pdo->prepare("
      SELECT id_seller
      FROM review
      WHERE id_order = ? AND id_user = ?
    ");
    $stmt->execute([$orderId, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
      $reviewedSellers[(int)$r["id_seller"]] = true;
    }
  } catch (Exception $e) {
  }

  $vendorsArr = [];
  foreach ($vendors as $v) {
    $vendorsArr[] = [
      "id_seller" => (int)$v["id_seller"],
      "vendeur_nom" => (string)$v["vendeur_nom"],
      "annonces" => $v["annonces"],
      "already_reviewed" => !empty($reviewedSellers[(int)$v["id_seller"]]),
    ];
  }

  echo json_encode([
    "ok" => true,
    "order" => [
      "id_order" => (int)$o["id_order"],
      "statut" => (string)($o["statut"] ?? "EN_ATTENTE"),
      "total" => (float)($o["total"] ?? 0),
      "date_commande" => (string)($o["date_commande"] ?? ""),
      "mode_reception" => (string)($o["mode_reception"] ?? "PICKUP"),
      "ville_livraison" => (string)($o["ville_livraison"] ?? ""),
      "frais_livraison" => (float)($o["frais_livraison"] ?? 0),
      "telephone_client" => (string)($o["telephone_client"] ?? ""),
      "adresse_livraison" => (string)($o["adresse_livraison"] ?? ""),
    ],
    "payment" => [
      "statut" => (string)($pay["statut"] ?? "EN_ATTENTE"),
      "methode" => (string)($pay["methode"] ?? "STRIPE"),
    ],
    "details" => array_map(function ($d) {
      return [
        "quantite" => (int)$d["quantite"],
        "prix_unitaire" => (float)$d["prix_unitaire"],
        "id_annonce" => (int)$d["id_annonce"],
        "titre" => (string)$d["titre"],
        "id_vendeur" => (int)$d["id_vendeur"],
        "vendeur_nom" => (string)$d["vendeur_nom"],
      ];
    }, $details),
    "vendors" => $vendorsArr
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "message" => "Erreur serveur."
  ], JSON_UNESCAPED_UNICODE);
}