<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

ini_set("display_errors", "0");
error_reporting(E_ALL);

ob_start();

register_shutdown_function(function () {
    $error = error_get_last();

    if ($error !== null) {
        if (ob_get_length()) {
            ob_clean();
        }

        http_response_code(500);
        echo json_encode([
            "ok" => false,
            "message" => "Erreur PHP fatale: " . ($error["message"] ?? "Erreur inconnue")
        ], JSON_UNESCAPED_UNICODE);
    }
});

require_once __DIR__ . "/../config/db.php";

$autoloadPath = __DIR__ . "/../vendor/autoload.php";
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "vendor/autoload.php introuvable. Fais composer install."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $autoloadPath;

if (!class_exists('\Stripe\Stripe')) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Librairie Stripe absente. Lance: composer require stripe/stripe-php"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stripeConfigPath = __DIR__ . "/../config/stripe.php";
if (!file_exists($stripeConfigPath)) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "config/stripe.php introuvable."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$cfg = require $stripeConfigPath;

if (!is_array($cfg) || empty($cfg["secret_key"]) || empty($cfg["currency"])) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Configuration Stripe invalide."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

\Stripe\Stripe::setApiKey($cfg["secret_key"]);

function jsonFail(string $message, int $status = 400): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($status);
    echo json_encode([
        "ok" => false,
        "message" => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function safeStr(mixed $value): string
{
    return trim((string)($value ?? ""));
}

try {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw ?: "{}", true);

    if (!is_array($data)) {
        jsonFail("Données JSON invalides.");
    }

    $userId = (int)($data["user_id"] ?? 0);
    $modeReception = strtoupper(safeStr($data["mode_reception"] ?? "PICKUP"));
    $villeLivraison = safeStr($data["ville_livraison"] ?? "");
    $telephoneClient = safeStr($data["telephone_client"] ?? "");
    $adresseLivraison = safeStr($data["adresse_livraison"] ?? "");

    $MAROC_CITIES = [
        "Agadir","Al Hoceima","Asilah","Azrou","Beni Mellal","Berkane","Boujdour",
        "Casablanca","Chefchaouen","Dakhla","El Jadida","Errachidia","Essaouira",
        "Fès","Guelmim","Ifrane","Kenitra","Khemisset","Khouribga","Laâyoune",
        "Larache","Marrakech","Meknès","Mohammedia","Nador","Ouarzazate",
        "Oujda","Rabat","Safi","Salé","Settat","Sidi Ifni","Tanger","Tarfaya",
        "Taza","Tétouan"
    ];

    if ($userId <= 0) {
        jsonFail("Connexion requise.", 401);
    }

    if (!in_array($modeReception, ["PICKUP", "LIVRAISON"], true)) {
        $modeReception = "PICKUP";
    }

    if ($telephoneClient === "" || mb_strlen($telephoneClient) < 8) {
        jsonFail("Numéro de téléphone invalide.");
    }

    if ($modeReception === "LIVRAISON") {
        if (!in_array($villeLivraison, $MAROC_CITIES, true)) {
            $villeLivraison = "Marrakech";
        }

        if ($adresseLivraison === "" || mb_strlen($adresseLivraison) < 5) {
            jsonFail("Adresse de livraison obligatoire.");
        }
    } else {
        $villeLivraison = "";
    }

    $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE id_user = ? LIMIT 1");
    $stmt->execute([$userId]);
    $panier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$panier) {
        jsonFail("Panier introuvable.");
    }

    $panierId = (int)$panier["id_panier"];

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
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$items || count($items) === 0) {
        jsonFail("Ton panier est vide.");
    }

    $sellerId = 0;
    $sellerCity = "";
    $sellerLivOn = 0;
    $sellerFeeSame = 15.0;
    $sellerFeeOther = 40.0;

    foreach ($items as $it) {
        $vid = (int)($it["id_vendeur"] ?? 0);

        if ($sellerId === 0) {
            $sellerId = $vid;
            $sellerCity = safeStr($it["ville"] ?? "");
            $sellerLivOn = (int)($it["livraison_active"] ?? 0);
            $sellerFeeSame = (float)($it["livraison_prix_same_city"] ?? 15);
            $sellerFeeOther = (float)($it["livraison_prix_other_city"] ?? 40);
        } elseif ($sellerId !== $vid) {
            jsonFail("Une commande doit contenir un seul vendeur.");
        }
    }

    if ($modeReception === "LIVRAISON" && $sellerLivOn !== 1) {
        jsonFail("Le vendeur n’a pas activé la livraison.");
    }

    $lineItems = [];
    $cartMapParts = [];

    foreach ($items as $it) {
        $statutAnnonce = safeStr($it["statut"] ?? "");
        $modeVente = safeStr($it["mode_vente"] ?? "");
        $qty = (int)($it["quantity"] ?? 0);
        $stock = (int)($it["stock"] ?? 0);
        $price = (float)($it["prix"] ?? 0);
        $titre = safeStr($it["titre"] ?? "Produit");
        $idAnnonce = (int)($it["id_annonce"] ?? 0);

        if ($statutAnnonce !== "ACTIVE") {
            jsonFail("Annonce inactive dans le panier.");
        }

        if (!in_array($modeVente, ["PAIEMENT_DIRECT", "LES_DEUX"], true)) {
            jsonFail("Une annonce n’est pas disponible en paiement direct.");
        }

        if ($qty < 1) {
            jsonFail("Quantité invalide.");
        }

        if ($stock < $qty) {
            jsonFail("Stock insuffisant.");
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

        $cartMapParts[] = $idAnnonce . ":" . $qty;
    }

    $fraisLivraison = 0.0;

    if ($modeReception === "LIVRAISON") {
        $sameCity = mb_strtolower($villeLivraison) === mb_strtolower($sellerCity);
        $fraisLivraison = $sameCity ? $sellerFeeSame : $sellerFeeOther;

        if ($fraisLivraison > 0) {
            $lineItems[] = [
                "quantity" => 1,
                "price_data" => [
                    "currency" => strtolower((string)$cfg["currency"]),
                    "unit_amount" => (int) round($fraisLivraison * 100),
                    "product_data" => [
                        "name" => "Livraison",
                    ],
                ],
            ];
        }
    }

    $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
    $host = $_SERVER["HTTP_HOST"] ?? "192.168.1.13";
    $root = $scheme . "://" . $host . "/pfe_fluxo/vente_entre_particuliers";

    $session = \Stripe\Checkout\Session::create([
        "mode" => "payment",
        "line_items" => $lineItems,
        "success_url" => $root . "/stripe_success.php?session_id={CHECKOUT_SESSION_ID}",
        "cancel_url" => $root . "/stripe_cancel.php",
        "metadata" => [
            "user_id" => (string)$userId,
            "mode_reception" => (string)$modeReception,
            "ville_livraison" => (string)$villeLivraison,
            "telephone_client" => (string)$telephoneClient,
            "adresse_livraison" => (string)$adresseLivraison,
            "seller_id" => (string)$sellerId,
            "cart_map" => (string)implode(",", $cartMapParts),
            "frais_livraison" => (string)$fraisLivraison,
        ],
    ]);

    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode([
        "ok" => true,
        "url" => $session->url
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}