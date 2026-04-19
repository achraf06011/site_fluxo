<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$userId = (int)($_GET["user_id"] ?? 0);
$id = (int)($_GET["id"] ?? 0);

if ($userId <= 0 || $id <= 0) {
  echo json_encode([
    "ok" => false,
    "message" => "Paramètres invalides."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT * FROM orders WHERE id_order = ? AND id_user = ? LIMIT 1");
  $stmt->execute([$id, $userId]);
  $o = $stmt->fetch();

  if (!$o) {
    echo json_encode([
      "ok" => false,
      "message" => "Commande introuvable."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt = $pdo->prepare("
    SELECT 
      od.quantite, od.prix_unitaire,
      a.id_annonce, a.titre,
      a.id_vendeur, u.nom AS vendeur_nom
    FROM order_details od
    JOIN annonce a ON a.id_annonce = od.id_annonce
    JOIN user u ON u.id_user = a.id_vendeur
    WHERE od.id_order = ?
  ");
  $stmt->execute([$id]);
  $details = $stmt->fetchAll();

  $stmt = $pdo->prepare("SELECT * FROM paiement WHERE id_order = ? LIMIT 1");
  $stmt->execute([$id]);
  $pay = $stmt->fetch();

  $modeReception    = strtoupper(trim((string)($o["mode_reception"] ?? "PICKUP")));
  $villeLivraison   = $o["ville_livraison"] ?? null;
  $fraisLivraison   = (float)($o["frais_livraison"] ?? 0);
  $telephoneClient  = $o["telephone_client"] ?? "";
  $adresseLivraison = $o["adresse_livraison"] ?? "";

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
    $stmt->execute([$id, $userId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
      $reviewedSellers[(int)$r["id_seller"]] = true;
    }
  } catch (Exception $e) {}

  $detailsOut = [];
  foreach ($details as $d) {
    $q = (int)$d["quantite"];
    $pu = (float)$d["prix_unitaire"];
    $line = $q * $pu;

    $detailsOut[] = [
      "id_annonce" => (int)$d["id_annonce"],
      "titre" => (string)$d["titre"],
      "quantite" => $q,
      "prix_unitaire" => $pu,
      "line_total" => $line,
      "id_vendeur" => (int)$d["id_vendeur"],
      "vendeur_nom" => (string)$d["vendeur_nom"],
    ];
  }

  $vendorsOut = [];
  foreach ($vendors as $v) {
    $vendorsOut[] = [
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
      "mode_reception" => $modeReception,
      "ville_livraison" => $villeLivraison,
      "frais_livraison" => $fraisLivraison,
      "telephone_client" => $telephoneClient,
      "adresse_livraison" => $adresseLivraison,
    ],
    "paiement" => [
      "methode" => (string)($pay["methode"] ?? "STRIPE"),
      "statut" => (string)($pay["statut"] ?? "EN_ATTENTE"),
    ],
    "details" => $detailsOut,
    "vendors" => $vendorsOut,
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "message" => "Erreur serveur."
  ], JSON_UNESCAPED_UNICODE);
}