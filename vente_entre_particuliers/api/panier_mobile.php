<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$userId = (int)($_GET["user_id"] ?? 0);

if ($userId <= 0) {
  echo json_encode([
    "ok" => false,
    "message" => "Utilisateur invalide."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

function coverUrl($x) {
  $file = $x["cover_image"] ?? null;
  if (!$file) {
    return "https://picsum.photos/seed/" . ((int)($x["id_annonce"] ?? 0) ?: rand(1, 9999)) . "/800/600";
  }
  if (preg_match('#^https?://#i', $file)) return $file;

  $host = $_SERVER["HTTP_HOST"] ?? "192.168.1.13";
  $basePath = rtrim(dirname(dirname($_SERVER["SCRIPT_NAME"] ?? "")), "/\\");

  return "http://" . $host . $basePath . "/uploads/" . ltrim($file, "/");
}

try {
  $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE id_user = ? LIMIT 1");
  $stmt->execute([$userId]);
  $p = $stmt->fetch();

  if (!$p) {
    echo json_encode([
      "ok" => true,
      "items" => [],
      "total" => 0
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $panierId = (int)$p["id_panier"];

  $stmt = $pdo->prepare("
    SELECT
      pi.id_panier_item,
      pi.quantity,
      a.id_annonce,
      a.titre,
      a.prix,
      a.stock,
      a.cover_image,
      a.mode_vente,
      a.statut
    FROM panier_item pi
    JOIN annonce a ON a.id_annonce = pi.id_annonce
    WHERE pi.id_panier = ?
    ORDER BY pi.id_panier_item DESC
  ");
  $stmt->execute([$panierId]);
  $rows = $stmt->fetchAll();

  $items = [];
  $total = 0;

  foreach ($rows as $it) {
    $qty = (int)$it["quantity"];
    $prix = (float)$it["prix"];
    $sub = $qty * $prix;
    $total += $sub;

    $items[] = [
      "id_panier_item" => (int)$it["id_panier_item"],
      "id_annonce" => (int)$it["id_annonce"],
      "titre" => (string)$it["titre"],
      "prix" => $prix,
      "stock" => (int)$it["stock"],
      "quantity" => $qty,
      "subtotal" => $sub,
      "cover_image_url" => coverUrl($it),
    ];
  }

  echo json_encode([
    "ok" => true,
    "items" => $items,
    "total" => $total
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "message" => "Erreur serveur."
  ], JSON_UNESCAPED_UNICODE);
}