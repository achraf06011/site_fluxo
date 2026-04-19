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

function imgAbsoluteUrl(?string $file, int $annonceId = 0): ?string {
  if (!$file) return null;

  if (preg_match('#^https?://#i', $file)) {
    return $file;
  }

  $host = $_SERVER["HTTP_HOST"] ?? "192.168.1.13";
  $basePath = rtrim(dirname(dirname($_SERVER["SCRIPT_NAME"] ?? "")), "/\\");
  return "http://" . $host . $basePath . "/uploads/" . ltrim($file, "/");
}

try {
  $stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM order_details od
    JOIN annonce a ON a.id_annonce = od.id_annonce
    WHERE od.id_order = ? AND a.id_vendeur = ?
  ");
  $stmt->execute([$id, $userId]);

  if ((int)$stmt->fetchColumn() <= 0) {
    echo json_encode([
      "ok" => false,
      "message" => "Accès refusé."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  try {
    $stmt = $pdo->prepare("UPDATE orders SET seller_seen = 1 WHERE id_order = ?");
    $stmt->execute([$id]);
  } catch (Exception $e) {}

  $stmt = $pdo->prepare("
    SELECT
      o.*,
      o.id_user AS acheteur_id,
      u.nom AS acheteur_nom,
      u.email AS acheteur_email,
      p.statut AS paiement_statut,
      p.methode AS paiement_methode
    FROM orders o
    JOIN user u ON u.id_user = o.id_user
    LEFT JOIN paiement p ON p.id_order = o.id_order
    WHERE o.id_order = ?
    LIMIT 1
  ");
  $stmt->execute([$id]);
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
      od.quantite,
      od.prix_unitaire,
      a.id_annonce,
      a.titre,
      a.cover_image
    FROM order_details od
    JOIN annonce a ON a.id_annonce = od.id_annonce
    WHERE od.id_order = ?
      AND a.id_vendeur = ?
  ");
  $stmt->execute([$id, $userId]);
  $detailsRows = $stmt->fetchAll();

  $details = [];
  foreach ($detailsRows as $d) {
    $details[] = [
      "id_annonce" => (int)$d["id_annonce"],
      "titre" => (string)$d["titre"],
      "quantite" => (int)$d["quantite"],
      "prix_unitaire" => (float)$d["prix_unitaire"],
      "line_total" => (float)$d["prix_unitaire"] * (int)$d["quantite"],
      "cover_image_url" => imgAbsoluteUrl($d["cover_image"] ?? null, (int)$d["id_annonce"]),
    ];
  }

  $modeReception = strtoupper(trim((string)($o["mode_reception"] ?? "PICKUP")));
  $statutLivraison = strtoupper(trim((string)($o["statut_livraison"] ?? "PREPARATION")));
  $paiementStatut = strtoupper(trim((string)($o["paiement_statut"] ?? "EN_ATTENTE")));

  $optionsLivraison = [];
  if ($modeReception === "LIVRAISON") {
    $optionsLivraison = ["PREPARATION","EN_TRANSIT","ARRIVEE_VILLE","EN_LIVRAISON","LIVREE"];
  } else {
    $optionsLivraison = ["PREPARATION","DISPONIBLE","TERMINEE"];
  }

  echo json_encode([
    "ok" => true,
    "order" => [
      "id_order" => (int)$o["id_order"],
      "acheteur_id" => (int)$o["acheteur_id"],
      "acheteur_nom" => (string)$o["acheteur_nom"],
      "acheteur_email" => (string)$o["acheteur_email"],
      "telephone_client" => (string)($o["telephone_client"] ?? ""),
      "mode_reception" => $modeReception,
      "ville_livraison" => $o["ville_livraison"],
      "adresse_livraison" => (string)($o["adresse_livraison"] ?? ""),
      "frais_livraison" => (float)($o["frais_livraison"] ?? 0),
      "statut_livraison" => $statutLivraison,
      "statut_livraison_updated_at" => $o["statut_livraison_updated_at"],
      "date_commande" => (string)($o["date_commande"] ?? ""),
      "paiement_statut" => $paiementStatut,
      "paiement_methode" => (string)($o["paiement_methode"] ?? "STRIPE"),
      "can_manage_shipping" => ($paiementStatut === "ACCEPTE"),
      "options_livraison" => $optionsLivraison,
    ],
    "details" => $details
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "message" => "Erreur serveur."
  ], JSON_UNESCAPED_UNICODE);
}