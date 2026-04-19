<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$userId = (int)($_GET["user_id"] ?? 0);
$statut = trim((string)($_GET["statut"] ?? ""));
$statutLivraison = trim((string)($_GET["statut_livraison"] ?? ""));

if ($userId <= 0) {
    echo json_encode([
        "ok" => false,
        "message" => "Utilisateur invalide."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function badgeOrderText($st) {
    $st = strtoupper(trim($st ? $st : "EN_ATTENTE"));
    return $st;
}

function badgePayText($st) {
    $st = strtoupper(trim($st ? $st : "EN_ATTENTE"));
    return $st;
}

function shippingLabel($st, $modeReception = "PICKUP") {
    $st = strtoupper(trim((string)$st));
    $modeReception = strtoupper(trim((string)$modeReception));

    if ($modeReception === "LIVRAISON") {
        switch ($st) {
            case "PREPARATION":
                return "Préparation";
            case "EN_TRANSIT":
                return "En transit";
            case "ARRIVEE_VILLE":
                return "Arrivée à ta ville";
            case "EN_LIVRAISON":
                return "En cours de livraison";
            case "LIVREE":
                return "Livrée";
            default:
                return "Préparation";
        }
    }

    switch ($st) {
        case "PREPARATION":
            return "Préparation";
        case "DISPONIBLE":
            return "Disponible pour remise";
        case "TERMINEE":
            return "Remise effectuée";
        case "LIVREE":
            return "Remise effectuée";
        default:
            return "Préparation";
    }
}

try {
    $where = ["a.id_vendeur = ?"];
    $params = [$userId];

    if ($statut !== "") {
        $where[] = "o.statut = ?";
        $params[] = $statut;
    }

    if ($statutLivraison !== "") {
        $where[] = "COALESCE(o.statut_livraison, 'PREPARATION') = ?";
        $params[] = $statutLivraison;
    }

    try {
        $stmtSeen = $pdo->prepare("
            UPDATE orders o
            JOIN order_details od ON od.id_order = o.id_order
            JOIN annonce a ON a.id_annonce = od.id_annonce
            LEFT JOIN paiement p ON p.id_order = o.id_order
            SET o.seller_seen = 1
            WHERE a.id_vendeur = ?
              AND COALESCE(p.statut, 'EN_ATTENTE') = 'ACCEPTE'
        ");
        $stmtSeen->execute([$userId]);
    } catch (Exception $e) {
    }

    $sql = "
        SELECT
            o.id_order,
            o.date_commande,
            o.statut,
            o.mode_reception,
            o.ville_livraison,
            o.statut_livraison,
            o.statut_livraison_updated_at,
            o.seller_seen,
            u.nom AS acheteur_nom,
            u.email AS acheteur_email,
            COALESCE(p.statut, 'EN_ATTENTE') AS paiement_statut,
            COALESCE(p.methode, 'STRIPE') AS paiement_methode,
            SUM(od.quantite * od.prix_unitaire) AS vendeur_total,
            SUM(od.quantite) AS nb_articles
        FROM orders o
        JOIN user u ON u.id_user = o.id_user
        JOIN order_details od ON od.id_order = o.id_order
        JOIN annonce a ON a.id_annonce = od.id_annonce
        LEFT JOIN paiement p ON p.id_order = o.id_order
        WHERE " . implode(" AND ", $where) . "
        GROUP BY
            o.id_order,
            o.date_commande,
            o.statut,
            o.mode_reception,
            o.ville_livraison,
            o.statut_livraison,
            o.statut_livraison_updated_at,
            o.seller_seen,
            u.nom,
            u.email,
            p.statut,
            p.methode
        ORDER BY o.id_order DESC
        LIMIT 200
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $orders = [];

    foreach ($rows as $r) {
        $orderId = (int)$r["id_order"];

        $stmtFirst = $pdo->prepare("
            SELECT
                a.id_annonce,
                a.titre,
                a.cover_image
            FROM order_details od
            JOIN annonce a ON a.id_annonce = od.id_annonce
            WHERE od.id_order = ?
              AND a.id_vendeur = ?
            ORDER BY od.id_detail ASC
            LIMIT 1
        ");
        $stmtFirst->execute([$orderId, $userId]);
        $first = $stmtFirst->fetch(PDO::FETCH_ASSOC);

        $img = null;
        if (!empty($first["cover_image"])) {
            if (preg_match('#^https?://#i', $first["cover_image"])) {
                $img = $first["cover_image"];
            } else {
                $host = $_SERVER["HTTP_HOST"] ?? "192.168.1.13";
                $basePath = rtrim(dirname(dirname($_SERVER["SCRIPT_NAME"] ?? "")), "/\\");
                $img = "http://" . $host . $basePath . "/uploads/" . ltrim($first["cover_image"], "/");
            }
        }

        $orders[] = [
            "id_order" => $orderId,
            "date_commande" => (string)$r["date_commande"],
            "statut" => badgeOrderText((string)$r["statut"]),
            "mode_reception" => (string)$r["mode_reception"],
            "ville_livraison" => $r["ville_livraison"],
            "statut_livraison" => (string)($r["statut_livraison"] ? $r["statut_livraison"] : "PREPARATION"),
            "statut_livraison_updated_at" => $r["statut_livraison_updated_at"],
            "seller_seen" => (int)($r["seller_seen"] ?? 1),
            "acheteur_nom" => (string)$r["acheteur_nom"],
            "acheteur_email" => (string)$r["acheteur_email"],
            "paiement_statut" => badgePayText((string)$r["paiement_statut"]),
            "paiement_methode" => (string)$r["paiement_methode"],
            "vendeur_total" => (float)$r["vendeur_total"],
            "nb_articles" => (int)$r["nb_articles"],
            "titre" => (string)($first["titre"] ?? "Produits de la vente"),
            "id_annonce" => (int)($first["id_annonce"] ?? 0),
            "cover_image_url" => $img,
            "shipping_text" => shippingLabel(
                (string)($r["statut_livraison"] ? $r["statut_livraison"] : "PREPARATION"),
                (string)($r["mode_reception"] ?? "PICKUP")
            )
        ];
    }

    echo json_encode([
        "ok" => true,
        "orders" => $orders
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur."
    ], JSON_UNESCAPED_UNICODE);
}