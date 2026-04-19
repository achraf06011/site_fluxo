<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/vendor/autoload.php";
$cfg = require __DIR__ . "/config/stripe.php";
\Stripe\Stripe::setApiKey($cfg["secret_key"]);

function go($url) {
  header("Location: " . $url);
  exit;
}

function failAndGo($msg) {
  $_SESSION["flash_error"] = $msg;
  go("panier.php");
}

$sessionId = trim($_GET["session_id"] ?? "");
if ($sessionId === "") {
  failAndGo("Session Stripe manquante.");
}

$userId = currentUserId();

try {
  $session = \Stripe\Checkout\Session::retrieve($sessionId);

  if (!$session) {
    failAndGo("Session Stripe introuvable.");
  }

  $paymentStatus = (string)($session->payment_status ?? "unpaid");
  if ($paymentStatus !== "paid") {
    $_SESSION["flash_error"] = "Paiement non confirmé.";
    go("panier.php");
  }

  $metaUserId = (int)($session->metadata->user_id ?? 0);
  if ($metaUserId !== $userId) {
    failAndGo("Cette session Stripe ne t’appartient pas.");
  }

  $existingPayStmt = $pdo->prepare("
    SELECT p.id_paiement, p.id_order
    FROM paiement p
    WHERE p.stripe_session_id = ?
    LIMIT 1
  ");
  $existingPayStmt->execute([$sessionId]);
  $existingPay = $existingPayStmt->fetch();

  if ($existingPay && !empty($existingPay["id_order"])) {
    go("checkout_success.php?id=" . (int)$existingPay["id_order"]);
  }

  $modeReception = strtoupper(trim((string)($session->metadata->mode_reception ?? "PICKUP")));
  $villeLivraison = trim((string)($session->metadata->ville_livraison ?? ""));
  $telephoneClient = trim((string)($session->metadata->telephone_client ?? ""));
  $adresseLivraison = trim((string)($session->metadata->adresse_livraison ?? ""));
  $sellerId = (int)($session->metadata->seller_id ?? 0);
  $cartMap = trim((string)($session->metadata->cart_map ?? ""));
  $fraisLivraison = (float)($session->metadata->frais_livraison ?? 0);
  $stripePaymentIntent = (string)($session->payment_intent ?? "");

  if ($cartMap === "") {
    failAndGo("Panier Stripe introuvable.");
  }

  $pairs = array_filter(array_map('trim', explode(",", $cartMap)));
  if (count($pairs) === 0) {
    failAndGo("Panier Stripe vide.");
  }

  $cartItems = [];
  foreach ($pairs as $pair) {
    $parts = explode(":", $pair);
    if (count($parts) !== 2) {
      continue;
    }

    $idAnnonce = (int)$parts[0];
    $qty = (int)$parts[1];

    if ($idAnnonce > 0 && $qty > 0) {
      $cartItems[] = [
        "id_annonce" => $idAnnonce,
        "qty" => $qty,
      ];
    }
  }

  if (count($cartItems) === 0) {
    failAndGo("Articles Stripe invalides.");
  }

  $pdo->beginTransaction();

  $subtotal = 0.0;
  $firstSellerId = 0;

  $stmtAnnonce = $pdo->prepare("
    SELECT id_annonce, id_vendeur, titre, prix, stock, statut, mode_vente
    FROM annonce
    WHERE id_annonce = ?
    LIMIT 1
    FOR UPDATE
  ");

  $validatedItems = [];

  foreach ($cartItems as $ci) {
    $stmtAnnonce->execute([(int)$ci["id_annonce"]]);
    $a = $stmtAnnonce->fetch();

    if (!$a) {
      $pdo->rollBack();
      failAndGo("Une annonce n’existe plus.");
    }

    if (($a["statut"] ?? "") !== "ACTIVE") {
      $pdo->rollBack();
      failAndGo("Une annonce n’est plus active.");
    }

    if (!in_array(($a["mode_vente"] ?? ""), ["PAIEMENT_DIRECT", "LES_DEUX"], true)) {
      $pdo->rollBack();
      failAndGo("Une annonce n’est plus disponible en paiement direct.");
    }

    $qty = (int)$ci["qty"];
    $stock = (int)$a["stock"];
    $price = (float)$a["prix"];
    $annonceSeller = (int)$a["id_vendeur"];

    if ($qty < 1) {
      $pdo->rollBack();
      failAndGo("Quantité invalide.");
    }

    if ($stock < $qty) {
      $pdo->rollBack();
      failAndGo("Stock insuffisant après paiement.");
    }

    if ($firstSellerId === 0) {
      $firstSellerId = $annonceSeller;
    } elseif ($firstSellerId !== $annonceSeller) {
      $pdo->rollBack();
      failAndGo("La commande doit contenir un seul vendeur.");
    }

    $subtotal += ($price * $qty);

    $validatedItems[] = [
      "id_annonce" => (int)$a["id_annonce"],
      "id_vendeur" => $annonceSeller,
      "titre" => (string)$a["titre"],
      "prix" => $price,
      "qty" => $qty,
    ];
  }

  if ($sellerId > 0 && $firstSellerId !== 0 && $sellerId !== $firstSellerId) {
    $pdo->rollBack();
    failAndGo("Vendeur Stripe invalide.");
  }

  $total = $subtotal + $fraisLivraison;

  $stmtOrder = $pdo->prepare("
    INSERT INTO orders (
      date_commande,
      statut,
      total,
      id_user,
      ville_livraison,
      mode_reception,
      frais_livraison,
      seller_seen,
      buyer_seen,
      statut_livraison,
      statut_livraison_updated_at,
      telephone_client,
      adresse_livraison
    )
    VALUES (CURDATE(), 'PAYE', ?, ?, ?, ?, ?, 0, 1, 'PREPARATION', NOW(), ?, ?)
  ");
  $stmtOrder->execute([
    $total,
    $userId,
    $modeReception === "LIVRAISON" ? $villeLivraison : null,
    $modeReception,
    $fraisLivraison,
    $telephoneClient,
    $adresseLivraison !== "" ? $adresseLivraison : null
  ]);
  $orderId = (int)$pdo->lastInsertId();

  $stmtDetail = $pdo->prepare("
    INSERT INTO order_details (quantite, prix_unitaire, id_order, id_annonce)
    VALUES (?, ?, ?, ?)
  ");

  $stmtStock = $pdo->prepare("
    UPDATE annonce
    SET stock = stock - ?
    WHERE id_annonce = ?
  ");

  foreach ($validatedItems as $it) {
    $stmtDetail->execute([
      (int)$it["qty"],
      (float)$it["prix"],
      $orderId,
      (int)$it["id_annonce"]
    ]);

    $stmtStock->execute([
      (int)$it["qty"],
      (int)$it["id_annonce"]
    ]);
  }

  $stmtPay = $pdo->prepare("
    INSERT INTO paiement (
      date_paiement,
      montant,
      statut,
      methode,
      id_order,
      stripe_session_id,
      stripe_payment_intent
    )
    VALUES (CURDATE(), ?, 'ACCEPTE', 'STRIPE', ?, ?, ?)
  ");
  $stmtPay->execute([
    $total,
    $orderId,
    $sessionId,
    $stripePaymentIntent !== "" ? $stripePaymentIntent : null
  ]);

  if ($firstSellerId > 0) {
    try {
      $stmtNotif = $pdo->prepare("
        INSERT INTO notification (
          id_user,
          type_notification,
          titre,
          contenu,
          lien,
          is_read,
          created_at,
          is_popup_seen
        )
        VALUES (?, ?, ?, ?, ?, 0, NOW(), 0)
      ");
      $stmtNotif->execute([
        $firstSellerId,
        "NEW_ORDER",
        "Nouvelle commande",
        "Tu as reçu une nouvelle commande #" . $orderId . ".",
        "vente.php?id=" . $orderId
      ]);
    } catch (Exception $e) {
    }
  }

  $stmtPanier = $pdo->prepare("SELECT id_panier FROM panier WHERE id_user = ? LIMIT 1");
  $stmtPanier->execute([$userId]);
  $panier = $stmtPanier->fetch();

  if ($panier) {
    $pdo->prepare("DELETE FROM panier_item WHERE id_panier = ?")->execute([(int)$panier["id_panier"]]);
  }

  $pdo->commit();

  $_SESSION["flash_success"] = "Paiement Stripe confirmé ✅";
  go("checkout_success.php?id=" . $orderId);

} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  failAndGo("Erreur Stripe: " . $e->getMessage());
}