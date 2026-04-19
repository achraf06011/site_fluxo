<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../checkout.php");
  exit;
}

$userId = currentUserId();

function fail($msg) {
  $_SESSION["flash_error"] = $msg;
  header("Location: ../checkout.php");
  exit;
}

$modeReception    = strtoupper(trim($_POST["mode_reception"] ?? "PICKUP"));
$villeLivraison   = trim($_POST["ville_livraison"] ?? "");
$fraisLivraison   = (float)($_POST["frais_livraison"] ?? 0);
$telephoneClient  = trim($_POST["telephone_client"] ?? "");
$adresseLivraison = trim($_POST["adresse_livraison"] ?? "");

// mémoriser en session
$_SESSION["checkout_mode_reception"]   = $modeReception;
$_SESSION["checkout_ville_livraison"]  = $villeLivraison;
$_SESSION["checkout_telephone_client"] = $telephoneClient;
$_SESSION["checkout_adresse_livraison"] = $adresseLivraison;

if (!in_array($modeReception, ["PICKUP", "LIVRAISON"], true)) {
  fail("Mode de réception invalide.");
}

if ($telephoneClient === "" || mb_strlen($telephoneClient) < 8) {
  fail("Numéro de téléphone invalide.");
}

if ($modeReception === "LIVRAISON" && $adresseLivraison === "") {
  fail("Adresse de livraison obligatoire.");
}

try {
  // panier
  $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE id_user = ? LIMIT 1");
  $stmt->execute([$userId]);
  $p = $stmt->fetch();
  if (!$p) fail("Panier introuvable.");

  $panierId = (int)$p["id_panier"];

  $pdo->beginTransaction();

  // items + infos vendeur/livraison
  $stmt = $pdo->prepare("
    SELECT pi.id_panier_item, pi.quantity,
           a.id_annonce, a.prix, a.stock, a.statut, a.mode_vente,
           a.id_vendeur, a.ville,
           a.livraison_active, a.livraison_prix_same_city, a.livraison_prix_other_city
    FROM panier_item pi
    JOIN annonce a ON a.id_annonce = pi.id_annonce
    WHERE pi.id_panier = ?
    FOR UPDATE
  ");
  $stmt->execute([$panierId]);
  $items = $stmt->fetchAll();

  if (count($items) === 0) {
    $pdo->rollBack();
    fail("Ton panier est vide.");
  }

  $total = 0.0;
  $sellerId = 0;
  $sellerCity = "";
  $sellerLivOn = 0;
  $sellerFeeSame = 15.0;
  $sellerFeeOther = 40.0;

  foreach ($items as $it) {
    if (($it["statut"] ?? "") !== "ACTIVE") {
      $pdo->rollBack();
      fail("Une annonce est inactive. Retire-la du panier.");
    }

    if (!in_array($it["mode_vente"], ["PAIEMENT_DIRECT", "LES_DEUX"], true)) {
      $pdo->rollBack();
      fail("Une annonce n'est pas disponible en paiement direct.");
    }

    $qty = (int)$it["quantity"];
    $stock = (int)$it["stock"];

    if ($qty < 1) {
      $pdo->rollBack();
      fail("Quantité invalide.");
    }

    if ($stock < $qty) {
      $pdo->rollBack();
      fail("Stock insuffisant pour un produit du panier.");
    }

    $total += ((float)$it["prix"]) * $qty;

    $vid = (int)$it["id_vendeur"];
    if ($sellerId === 0) {
      $sellerId = $vid;
      $sellerCity = (string)($it["ville"] ?? "");
      $sellerLivOn = (int)($it["livraison_active"] ?? 0);
      $sellerFeeSame = (float)($it["livraison_prix_same_city"] ?? 15);
      $sellerFeeOther = (float)($it["livraison_prix_other_city"] ?? 40);
    } elseif ($sellerId !== $vid) {
      $pdo->rollBack();
      fail("Une commande doit contenir un seul vendeur.");
    }
  }

  // recalcul frais livraison côté serveur
  $realFraisLivraison = 0.0;
  if ($modeReception === "LIVRAISON") {
    if ($sellerLivOn !== 1) {
      $pdo->rollBack();
      fail("Le vendeur n’a pas activé la livraison.");
    }

    $sameCity = (mb_strtolower($villeLivraison) === mb_strtolower($sellerCity));
    $realFraisLivraison = $sameCity ? $sellerFeeSame : $sellerFeeOther;
  }

  $totalFinal = $total + $realFraisLivraison;

  // statut livraison initial
  $statutLivraison = ($modeReception === "LIVRAISON") ? "PREPARATION" : "PREPARATION";

  // créer order
  $stmt = $pdo->prepare("
    INSERT INTO orders (
      date_commande,
      statut,
      total,
      id_user,
      ville_livraison,
      mode_reception,
      frais_livraison,
      statut_livraison,
      telephone_client,
      adresse_livraison
    )
    VALUES (CURDATE(), 'EN_ATTENTE', ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $stmt->execute([
    $totalFinal,
    $userId,
    ($modeReception === "LIVRAISON" ? $villeLivraison : null),
    $modeReception,
    $realFraisLivraison,
    $statutLivraison,
    $telephoneClient,
    ($modeReception === "LIVRAISON" ? $adresseLivraison : null)
  ]);
  $orderId = (int)$pdo->lastInsertId();

  // détails + stock
  $stmtDetail = $pdo->prepare("
    INSERT INTO order_details (quantite, prix_unitaire, id_order, id_annonce)
    VALUES (?, ?, ?, ?)
  ");

  $stmtStock = $pdo->prepare("
    UPDATE annonce
    SET stock = stock - ?
    WHERE id_annonce = ?
  ");

  foreach ($items as $it) {
    $qty = (int)$it["quantity"];
    $price = (float)$it["prix"];
    $idAnnonce = (int)$it["id_annonce"];

    $stmtDetail->execute([$qty, $price, $orderId, $idAnnonce]);
    $stmtStock->execute([$qty, $idAnnonce]);
  }

  // paiement EN_ATTENTE
  $stmt = $pdo->prepare("
    INSERT INTO paiement (date_paiement, montant, statut, methode, id_order)
    VALUES (CURDATE(), ?, 'EN_ATTENTE', 'STRIPE', ?)
  ");
  $stmt->execute([$totalFinal, $orderId]);

  // vider panier
  $pdo->prepare("DELETE FROM panier_item WHERE id_panier = ?")->execute([$panierId]);

  $pdo->commit();

  // nettoyer session checkout
  unset(
    $_SESSION["checkout_mode_reception"],
    $_SESSION["checkout_ville_livraison"],
    $_SESSION["checkout_telephone_client"],
    $_SESSION["checkout_adresse_livraison"]
  );

  $_SESSION["last_order_id"] = $orderId;
  $_SESSION["flash_success"] = "Commande créée avec succès.";

  header("Location: ../checkout_success.php?id=" . $orderId);
  exit;

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  fail("Erreur serveur: " . $e->getMessage());
}