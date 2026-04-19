<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "GET" && $_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../index.php");
  exit;
}

$userId = currentUserId();

// accepter id via GET ou POST
$idAnnonce = (int)($_GET["id"] ?? ($_POST["id_annonce"] ?? 0));

function fail($msg, $idAnnonce = 0) {
  $_SESSION["flash_error"] = $msg;
  if ($idAnnonce > 0) {
    header("Location: ../annonce.php?id=" . (int)$idAnnonce);
  } else {
    header("Location: ../index.php");
  }
  exit;
}

if ($idAnnonce <= 0) {
  fail("Annonce invalide.");
}

try {
  // 1) vérifier annonce (doit être ACTIVE + payable direct + stock)
  $stmt = $pdo->prepare("
    SELECT id_annonce, stock, statut, mode_vente
    FROM annonce
    WHERE id_annonce = ?
    LIMIT 1
  ");
  $stmt->execute([$idAnnonce]);
  $a = $stmt->fetch();

  if (!$a) fail("Annonce introuvable.", $idAnnonce);

  if (($a["statut"] ?? "") !== "ACTIVE") {
    fail("Annonce non disponible.", $idAnnonce);
  }

  $mode = $a["mode_vente"] ?? "";
  $canBuy = in_array($mode, ["PAIEMENT_DIRECT", "LES_DEUX"], true);
  if (!$canBuy) {
    fail("Cette annonce n’est pas disponible en paiement direct.", $idAnnonce);
  }

  $stock = (int)($a["stock"] ?? 0);
  if ($stock < 1) {
    fail("Stock insuffisant.", $idAnnonce);
  }

  // 2) récupérer / créer panier
  $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE id_user = ? LIMIT 1");
  $stmt->execute([$userId]);
  $p = $stmt->fetch();

  if ($p) {
    $panierId = (int)$p["id_panier"];
  } else {
    $stmt = $pdo->prepare("INSERT INTO panier (id_user) VALUES (?)");
    $stmt->execute([$userId]);
    $panierId = (int)$pdo->lastInsertId();
  }

  // 3) transaction: vider panier + ajouter item
  $pdo->beginTransaction();

  // vider panier
  $pdo->prepare("DELETE FROM panier_item WHERE id_panier = ?")->execute([$panierId]);

  // ajouter item (qty=1)
  $stmt = $pdo->prepare("
    INSERT INTO panier_item (id_panier, id_annonce, quantity)
    VALUES (?, ?, 1)
  ");
  $stmt->execute([$panierId, $idAnnonce]);

  $pdo->commit();

  // 4) redirect checkout
  header("Location: ../checkout.php");
  exit;

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  fail("Erreur serveur: " . $e->getMessage(), $idAnnonce);
}