<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$action = $_GET["action"] ?? "add";
$id_annonce = (int)($_GET["id"] ?? 0);
$qty = (int)($_POST["qty"] ?? ($_GET["qty"] ?? 1));
if ($qty < 1) $qty = 1;

$userId = currentUserId();

function go($url) {
  header("Location: " . $url);
  exit;
}

try {
  // 1) récupérer ou créer panier
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
    $_SESSION["flash_success"] = "Panier vidé.";
    go("../panier.php");
  }

  if ($id_annonce <= 0) {
    $_SESSION["flash_error"] = "Produit invalide.";
    go("../panier.php");
  }

  // vérifier annonce + stock
  $stmt = $pdo->prepare("SELECT id_annonce, stock, mode_vente, statut FROM annonce WHERE id_annonce = ? LIMIT 1");
  $stmt->execute([$id_annonce]);
  $a = $stmt->fetch();

  if (!$a || $a["statut"] !== "ACTIVE") {
    $_SESSION["flash_error"] = "Annonce introuvable ou inactive.";
    go("../panier.php");
  }

  // on n'ajoute au panier que si paiement direct possible
  if (!in_array($a["mode_vente"], ["PAIEMENT_DIRECT", "LES_DEUX"], true)) {
    $_SESSION["flash_error"] = "Ce produit n'est pas disponible en paiement direct.";
    go("../annonce.php?id=" . $id_annonce);
  }

  $stock = (int)$a["stock"];
  if ($stock <= 0) {
    $_SESSION["flash_error"] = "Stock insuffisant.";
    go("../annonce.php?id=" . $id_annonce);
  }

  // chercher item existant
  $stmt = $pdo->prepare("SELECT id_panier_item, quantity FROM panier_item WHERE id_panier = ? AND id_annonce = ? LIMIT 1");
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
      $pdo->prepare("INSERT INTO panier_item (id_panier, id_annonce, quantity) VALUES (?, ?, ?)")
          ->execute([$panierId, $id_annonce, $addQty]);
    }

    $_SESSION["flash_success"] = "Ajouté au panier.";
    go("../panier.php");
  }

  if ($action === "update") {
    if (!$item) {
      $_SESSION["flash_error"] = "Produit introuvable dans le panier.";
      go("../panier.php");
    }

    $newQty = min(max(1, $qty), $stock);
    $pdo->prepare("UPDATE panier_item SET quantity = ? WHERE id_panier_item = ?")
        ->execute([$newQty, (int)$item["id_panier_item"]]);

    $_SESSION["flash_success"] = "Quantité mise à jour.";
    go("../panier.php");
  }

  if ($action === "delete") {
    $pdo->prepare("DELETE FROM panier_item WHERE id_panier = ? AND id_annonce = ?")
        ->execute([$panierId, $id_annonce]);

    $_SESSION["flash_success"] = "Article supprimé.";
    go("../panier.php");
  }

  $_SESSION["flash_error"] = "Action inconnue.";
  go("../panier.php");

} catch (Exception $e) {
  $_SESSION["flash_error"] = "Erreur serveur.";
  go("../panier.php");
}