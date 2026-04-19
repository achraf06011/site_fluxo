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

function getTableColumns(PDO $pdo, string $table): array {
  try {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
  } catch (Exception $e) {
    return [];
  }
}

try {
  $orderCols = getTableColumns($pdo, "orders");
  $paymentCols = getTableColumns($pdo, "paiement");
  $orderDetailCols = getTableColumns($pdo, "order_details");

  // Détecter colonne date dans orders
  $orderDateCol = null;
  foreach (["created_at", "date_commande", "date_order", "createdon", "date_creation"] as $col) {
    if (in_array($col, $orderCols, true)) {
      $orderDateCol = $col;
      break;
    }
  }

  // Détecter colonne seller_seen
  $hasSellerSeen = in_array("seller_seen", $orderCols, true);

  // Détecter colonne statut paiement
  $paymentStatusCol = null;
  foreach (["statut", "status", "etat"] as $col) {
    if (in_array($col, $paymentCols, true)) {
      $paymentStatusCol = $col;
      break;
    }
  }

  // Détecter colonne prix unitaire
  $priceCol = null;
  foreach (["prix_unitaire", "unit_price", "prix"] as $col) {
    if (in_array($col, $orderDetailCols, true)) {
      $priceCol = $col;
      break;
    }
  }

  if (!$priceCol) {
    throw new Exception("Colonne prix introuvable dans order_details.");
  }

  $stats = [
    "total_ventes" => 0,
    "commandes" => 0,
    "articles_vendus" => 0,
    "nouvelles_ventes" => 0,
  ];

  $chart = [];
  $topAnnonces = [];

  $paymentAcceptedSql = "1=1";
  if ($paymentStatusCol) {
    $paymentAcceptedSql = "COALESCE(p.`$paymentStatusCol`, 'EN_ATTENTE') = 'ACCEPTE'";
  }

  // =========================
  // Total ventes + commandes + articles vendus
  // =========================
  $sqlStats = "
    SELECT
      COALESCE(SUM(od.`$priceCol` * od.quantite), 0) AS total_ventes,
      COUNT(DISTINCT o.id_order) AS commandes,
      COALESCE(SUM(od.quantite), 0) AS articles_vendus
    FROM orders o
    JOIN order_details od ON od.id_order = o.id_order
    JOIN annonce a ON a.id_annonce = od.id_annonce
    LEFT JOIN paiement p ON p.id_order = o.id_order
    WHERE a.id_vendeur = ?
      AND $paymentAcceptedSql
  ";
  $stmt = $pdo->prepare($sqlStats);
  $stmt->execute([$userId]);
  $row = $stmt->fetch();

  if ($row) {
    $stats["total_ventes"] = (float)($row["total_ventes"] ?? 0);
    $stats["commandes"] = (int)($row["commandes"] ?? 0);
    $stats["articles_vendus"] = (int)($row["articles_vendus"] ?? 0);
  }

  // =========================
  // Nouvelles ventes
  // =========================
  if ($hasSellerSeen) {
    $sqlNew = "
      SELECT COUNT(DISTINCT o.id_order) AS nouvelles_ventes
      FROM orders o
      JOIN order_details od ON od.id_order = o.id_order
      JOIN annonce a ON a.id_annonce = od.id_annonce
      LEFT JOIN paiement p ON p.id_order = o.id_order
      WHERE a.id_vendeur = ?
        AND $paymentAcceptedSql
        AND COALESCE(o.seller_seen, 0) = 0
    ";
    $stmt = $pdo->prepare($sqlNew);
    $stmt->execute([$userId]);
    $stats["nouvelles_ventes"] = (int)($stmt->fetchColumn() ?: 0);
  } else {
    $stats["nouvelles_ventes"] = 0;
  }

  // =========================
  // Ventes 30 derniers jours
  // =========================
  if ($orderDateCol) {
    $sqlChart = "
      SELECT
        DATE(o.`$orderDateCol`) AS jour,
        COALESCE(SUM(od.`$priceCol` * od.quantite), 0) AS montant
      FROM orders o
      JOIN order_details od ON od.id_order = o.id_order
      JOIN annonce a ON a.id_annonce = od.id_annonce
      LEFT JOIN paiement p ON p.id_order = o.id_order
      WHERE a.id_vendeur = ?
        AND $paymentAcceptedSql
        AND o.`$orderDateCol` >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
      GROUP BY DATE(o.`$orderDateCol`)
      ORDER BY jour ASC
    ";
    $stmt = $pdo->prepare($sqlChart);
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
  } else {
    $rows = [];
  }

  $map = [];
  foreach ($rows as $r) {
    $map[$r["jour"]] = (float)$r["montant"];
  }

  for ($i = 29; $i >= 0; $i--) {
    $date = date("Y-m-d", strtotime("-$i day"));
    $chart[] = [
      "date" => $date,
      "label" => substr($date, 5),
      "montant" => isset($map[$date]) ? (float)$map[$date] : 0
    ];
  }

  // =========================
  // Top annonces
  // =========================
  $sqlTop = "
    SELECT
      a.id_annonce,
      a.titre,
      a.cover_image,
      COALESCE(SUM(od.quantite), 0) AS qty_vendue,
      COALESCE(SUM(od.`$priceCol` * od.quantite), 0) AS montant
    FROM order_details od
    JOIN orders o ON o.id_order = od.id_order
    JOIN annonce a ON a.id_annonce = od.id_annonce
    LEFT JOIN paiement p ON p.id_order = o.id_order
    WHERE a.id_vendeur = ?
      AND $paymentAcceptedSql
    GROUP BY a.id_annonce, a.titre, a.cover_image
    ORDER BY montant DESC, qty_vendue DESC, a.id_annonce DESC
    LIMIT 6
  ";
  $stmt = $pdo->prepare($sqlTop);
  $stmt->execute([$userId]);
  $topRows = $stmt->fetchAll();

  foreach ($topRows as $r) {
    $img = null;

    if (!empty($r["cover_image"])) {
      if (preg_match('#^https?://#i', $r["cover_image"])) {
        $img = $r["cover_image"];
      } else {
        $host = $_SERVER["HTTP_HOST"] ?? "192.168.1.13";
        $basePath = rtrim(dirname(dirname($_SERVER["SCRIPT_NAME"] ?? "")), "/\\");
        $img = "http://" . $host . $basePath . "/uploads/" . ltrim($r["cover_image"], "/");
      }
    }

    $topAnnonces[] = [
      "id_annonce" => (int)$r["id_annonce"],
      "titre" => (string)$r["titre"],
      "cover_image_url" => $img,
      "qty_vendue" => (int)$r["qty_vendue"],
      "montant" => (float)$r["montant"],
    ];
  }

  echo json_encode([
    "ok" => true,
    "stats" => $stats,
    "chart" => $chart,
    "top_annonces" => $topAnnonces
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "message" => "Erreur serveur.",
    "debug" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}