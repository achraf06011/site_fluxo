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

function currentShippingLabel($st, $modeReception = "PICKUP") {
    $st = strtoupper(trim((string)$st));
    $modeReception = strtoupper(trim((string)$modeReception));

    if ($modeReception === "LIVRAISON") {
        switch ($st) {
            case "PREPARATION":
                return "Préparation vendeur";
            case "EN_TRANSIT":
                return "En transit";
            case "ARRIVEE_VILLE":
                return "Arrivée à ta ville";
            case "EN_LIVRAISON":
                return "En cours de livraison";
            case "LIVREE":
                return "Livrée";
            default:
                return "Préparation vendeur";
        }
    }

    switch ($st) {
        case "PREPARATION":
            return "Préparation vendeur";
        case "DISPONIBLE":
            return "Disponible pour remise";
        case "TERMINEE":
            return "Remise effectuée";
        case "LIVREE":
            return "Remise effectuée";
        default:
            return "Préparation vendeur";
    }
}

try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id_order = ? AND id_user = ? LIMIT 1");
    $stmt->execute([$id, $userId]);
    $o = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$o) {
        echo json_encode([
            "ok" => false,
            "message" => "Commande introuvable."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM paiement WHERE id_order = ? LIMIT 1");
    $stmt->execute([$id]);
    $pay = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT 
            od.quantite,
            od.prix_unitaire,
            a.id_annonce,
            a.titre,
            a.id_vendeur,
            u.nom AS vendeur_nom
        FROM order_details od
        JOIN annonce a ON a.id_annonce = od.id_annonce
        JOIN user u ON u.id_user = a.id_vendeur
        WHERE od.id_order = ?
    ");
    $stmt->execute([$id]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $modeReception = strtoupper(trim((string)($o["mode_reception"] ?? "PICKUP")));
    $statutLivraison = strtoupper(trim((string)($o["statut_livraison"] ?? "PREPARATION")));

    $allStepsLivraison = [
        "PREPARATION" => "Préparation vendeur",
        "EN_TRANSIT" => "En transit",
        "ARRIVEE_VILLE" => "Arrivée à ta ville",
        "EN_LIVRAISON" => "En cours de livraison",
        "LIVREE" => "Livrée"
    ];

    $allStepsPickup = [
        "PREPARATION" => "Préparation vendeur",
        "DISPONIBLE" => "Disponible pour remise",
        "TERMINEE" => "Remise effectuée"
    ];

    $sourceSteps = ($modeReception === "LIVRAISON") ? $allStepsLivraison : $allStepsPickup;
    $keys = array_keys($sourceSteps);
    $currentIndex = array_search($statutLivraison, $keys, true);

    if ($currentIndex === false) {
        $currentIndex = 0;
    }

    $steps = [];
    foreach ($keys as $index => $key) {
        $state = "En attente";

        if ($index < $currentIndex) {
            $state = "Terminée";
        }

        if ($index === $currentIndex) {
            $state = "En cours";
        }

        $steps[] = [
            "key" => $key,
            "label" => $sourceSteps[$key],
            "state" => $state
        ];
    }

    $detailsOut = [];
    foreach ($details as $d) {
        $q = (int)$d["quantite"];
        $pu = (float)$d["prix_unitaire"];

        $detailsOut[] = [
            "id_annonce" => (int)$d["id_annonce"],
            "titre" => (string)$d["titre"],
            "quantite" => $q,
            "prix_unitaire" => $pu,
            "line_total" => $q * $pu,
            "vendeur_nom" => (string)$d["vendeur_nom"]
        ];
    }

    echo json_encode([
        "ok" => true,
        "order" => [
            "id_order" => (int)$o["id_order"],
            "statut" => (string)($o["statut"] ?? "EN_ATTENTE"),
            "paiement_statut" => (string)($pay["statut"] ?? "EN_ATTENTE"),
            "date_commande" => (string)($o["date_commande"] ?? ""),
            "updated_at" => (string)($o["statut_livraison_updated_at"] ?? ""),
            "mode_reception" => $modeReception,
            "telephone_client" => (string)($o["telephone_client"] ?? ""),
            "ville_livraison" => (string)($o["ville_livraison"] ?? ""),
            "adresse_livraison" => (string)($o["adresse_livraison"] ?? ""),
            "frais_livraison" => (float)($o["frais_livraison"] ?? 0),
            "statut_livraison" => $statutLivraison,
            "current_shipping_label" => currentShippingLabel($statutLivraison, $modeReception)
        ],
        "steps" => $steps,
        "details" => $detailsOut
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur."
    ], JSON_UNESCAPED_UNICODE);
}