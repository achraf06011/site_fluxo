<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../checkout.php");
  exit;
}

require_once __DIR__ . "/../vendor/autoload.php";

$cfg = require __DIR__ . "/../config/stripe.php";
\Stripe\Stripe::setApiKey($cfg["secret_key"]);

$userId = currentUserId();

function fail($msg) {
  $_SESSION["flash_error"] = $msg;
  header("Location: ../checkout.php");
  exit;
}

$MAROC_CITIES = [
  "Agadir","Al Hoceima","Asilah","Azrou","Beni Mellal","Berkane","Boujdour",
  "Casablanca","Chefchaouen","Dakhla","El Jadida","Errachidia","Essaouira",
  "Fès","Guelmim","Ifrane","Kenitra","Khemisset","Khouribga","Laâyoune",
  "Larache","Marrakech","Meknès","Mohammedia","Nador","Ouarzazate",
  "Oujda","Rabat","Safi","Salé","Settat","Sidi Ifni","Tanger","Tarfaya",
  "Taza","Tétouan"
];

$modeReception = $_POST["mode_reception"] ?? "PICKUP";
$villeLivraison = trim($_POST["ville_livraison"] ?? "");
$telephoneClient = trim($_POST["telephone_client"] ?? "");
$adresseLivraison = trim($_POST["adresse_livraison"] ?? "");

$_SESSION["checkout_telephone_client"] = $telephoneClient;
$_SESSION["checkout_adresse_livraison"] = $adresseLivraison;
$_SESSION["checkout_mode_reception"] = $modeReception;
$_SESSION["checkout_ville_livraison"] = $villeLivraison;

if (!in_array($modeReception, ["PICKUP", "LIVRAISON"], true)) {
  $modeReception = "PICKUP";
}

if ($telephoneClient === "" || mb_strlen($telephoneClient) < 8) {
  fail("Numéro de téléphone invalide.");
}

if ($modeReception === "LIVRAISON") {
  if (!in_array($villeLivraison, $MAROC_CITIES, true)) {
    $villeLivraison = "Marrakech";
  }

  if ($adresseLivraison === "" || mb_strlen($adresseLivraison) < 5) {
    fail("Adresse de livraison obligatoire.");
  }
} else {
  $villeLivraison = "";
}

try {
  $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE id_user = ? LIMIT 1");
  $stmt->execute([$userId]);
  $p = $stmt->fetch();

  if (!$p) {
    fail("Panier introuvable.");
  }

  $panierId = (int)$p["id_panier"];

  $stmt = $pdo->prepare("
    SELECT
      pi.quantity,
      a.id_annonce,
      a.titre,
      a.prix,
      a.stock,
      a.statut,
      a.mode_vente,
      a.id_vendeur,
      a.ville,
      a.livraison_active,
      a.livraison_prix_same_city,
      a.livraison_prix_other_city
    FROM panier_item pi
    JOIN annonce a ON a.id_annonce = pi.id_annonce
    WHERE pi.id_panier = ?
    ORDER BY pi.id_panier_item ASC
  ");
  $stmt->execute([$panierId]);
  $items = $stmt->fetchAll();

  if (count($items) === 0) {
    fail("Ton panier est vide.");
  }

  $sellerId = 0;
  $sellerCity = "";
  $sellerLivOn = 0;
  $sellerFeeSame = 15.0;
  $sellerFeeOther = 40.0;

  foreach ($items as $it) {
    $vid = (int)$it["id_vendeur"];

    if ($sellerId === 0) {
      $sellerId = $vid;
      $sellerCity = (string)($it["ville"] ?? "");
      $sellerLivOn = (int)($it["livraison_active"] ?? 0);
      $sellerFeeSame = (float)($it["livraison_prix_same_city"] ?? 15);
      $sellerFeeOther = (float)($it["livraison_prix_other_city"] ?? 40);
    } elseif ($sellerId !== $vid) {
      fail("Checkout supporte un seul vendeur par commande. Retire les autres produits.");
    }
  }

  if ($modeReception === "LIVRAISON" && $sellerLivOn !== 1) {
    fail("Le vendeur n’a pas activé la livraison. Choisis main propre.");
  }

  $lineItems = [];
  $cartMap = [];
  $fraisLivraison = 0.0;

  foreach ($items as $it) {
    if (($it["statut"] ?? "") !== "ACTIVE") {
      fail("Annonce inactive dans le panier.");
    }

    if (!in_array($it["mode_vente"], ["PAIEMENT_DIRECT", "LES_DEUX"], true)) {
      fail("Annonce non payable en direct.");
    }

    $qty = (int)$it["quantity"];
    $stock = (int)$it["stock"];
    $price = (float)$it["prix"];
    $idAnnonce = (int)$it["id_annonce"];
    $titre = (string)$it["titre"];

    if ($qty < 1) {
      fail("Quantité invalide.");
    }

    if ($stock < $qty) {
      fail("Stock insuffisant.");
    }

    $lineItems[] = [
      "quantity" => $qty,
      "price_data" => [
        "currency" => strtolower((string)$cfg["currency"]),
        "unit_amount" => (int) round($price * 100),
        "product_data" => [
          "name" => $titre,
        ],
      ],
    ];

    $cartMap[] = $idAnnonce . ":" . $qty;
  }

  if ($modeReception === "LIVRAISON") {
    $sameCity = (mb_strtolower((string)$villeLivraison) === mb_strtolower((string)$sellerCity));
    $fraisLivraison = $sameCity ? $sellerFeeSame : $sellerFeeOther;

    if ($fraisLivraison > 0) {
      $lineItems[] = [
        "quantity" => 1,
        "price_data" => [
          "currency" => strtolower((string)$cfg["currency"]),
          "unit_amount" => (int) round($fraisLivraison * 100),
          "product_data" => [
            "name" => "Livraison (" . ($sameCity ? "même ville" : "autre ville") . ")",
          ],
        ],
      ];
    }
  }

  $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
  $base = $scheme . "://" . $_SERVER["HTTP_HOST"] . rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\");
  $root = preg_replace("#/actions$#", "", $base);

  $session = \Stripe\Checkout\Session::create([
    "mode" => "payment",
    "line_items" => $lineItems,
    "success_url" => $root . "/stripe_success.php?session_id={CHECKOUT_SESSION_ID}",
    "cancel_url"  => $root . "/stripe_cancel.php",
    "metadata" => [
      "user_id" => (string)$userId,
      "mode_reception" => (string)$modeReception,
      "ville_livraison" => (string)$villeLivraison,
      "telephone_client" => (string)$telephoneClient,
      "adresse_livraison" => (string)$adresseLivraison,
      "seller_id" => (string)$sellerId,
      "cart_map" => (string)implode(",", $cartMap),
      "frais_livraison" => (string)$fraisLivraison,
    ],
  ]);

  header("Location: " . $session->url);
  exit;

} catch (Exception $e) {
  fail("Erreur Stripe/serveur: " . $e->getMessage());
}