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
    $file = isset($x["cover_image"]) ? $x["cover_image"] : null;

    if (!$file) {
        return null;
    }

    if (preg_match('#^https?://#i', $file)) {
        return $file;
    }

    $host = $_SERVER["HTTP_HOST"] ?? "192.168.1.13";
    $basePath = rtrim(dirname(dirname($_SERVER["SCRIPT_NAME"] ?? "")), "/\\");
    return "http://" . $host . $basePath . "/uploads/" . ltrim($file, "/");
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
    $stmt = $pdo->prepare("
        SELECT
            o.id_order,
            o.date_commande,
            o.statut,
            o.total,
            o.buyer_seen,
            o.statut_livraison,
            o.mode_reception,
            o.statut_livraison_updated_at,
            p.statut AS paiement_statut,
            p.methode
        FROM orders o
        LEFT JOIN paiement p ON p.id_order = o.id_order
        WHERE o.id_user = ?
        ORDER BY o.id_order DESC
        LIMIT 200
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $orderIds = [];
    foreach ($rows as $r) {
        $orderIds[] = (int)$r["id_order"];
    }

    $summaryByOrder = [];

    if (count($orderIds) > 0) {
        $placeholders = implode(",", array_fill(0, count($orderIds), "?"));

        $sqlFirst = "
            SELECT
                od.id_order,
                a.id_annonce,
                a.titre,
                a.cover_image,
                a.id_vendeur,
                u.nom AS vendeur_nom
            FROM order_details od
            JOIN annonce a ON a.id_annonce = od.id_annonce
            JOIN user u ON u.id_user = a.id_vendeur
            JOIN (
                SELECT id_order, MIN(id_detail) AS min_detail
                FROM order_details
                WHERE id_order IN ($placeholders)
                GROUP BY id_order
            ) x ON x.id_order = od.id_order AND x.min_detail = od.id_detail
        ";
        $stmt = $pdo->prepare($sqlFirst);
        $stmt->execute($orderIds);
        $firstItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($firstItems as $fi) {
            $oid = (int)$fi["id_order"];
            $summaryByOrder[$oid] = [
                "id_annonce" => (int)$fi["id_annonce"],
                "titre" => (string)($fi["titre"] ?? ""),
                "cover_image" => $fi["cover_image"] ?? null,
                "count_items" => 0,
                "id_vendeur" => (int)($fi["id_vendeur"] ?? 0),
                "vendeur_nom" => (string)($fi["vendeur_nom"] ?? "")
            ];
        }

        $sqlCnt = "
            SELECT id_order, COUNT(*) AS cnt
            FROM order_details
            WHERE id_order IN ($placeholders)
            GROUP BY id_order
        ";
        $stmt = $pdo->prepare($sqlCnt);
        $stmt->execute($orderIds);
        $cntRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cntRows as $c) {
            $oid = (int)$c["id_order"];

            if (!isset($summaryByOrder[$oid])) {
                $summaryByOrder[$oid] = [
                    "id_annonce" => 0,
                    "titre" => "",
                    "cover_image" => null,
                    "count_items" => 0,
                    "id_vendeur" => 0,
                    "vendeur_nom" => ""
                ];
            }

            $summaryByOrder[$oid]["count_items"] = (int)$c["cnt"];
        }
    }

    $orders = [];

    foreach ($rows as $o) {
        $orderId = (int)$o["id_order"];
        $sum = isset($summaryByOrder[$orderId]) ? $summaryByOrder[$orderId] : null;

        $orders[] = [
            "id_order" => $orderId,
            "date_commande" => (string)$o["date_commande"],
            "statut" => (string)($o["statut"] ?? "EN_ATTENTE"),
            "total" => (float)($o["total"] ?? 0),
            "paiement_statut" => (string)($o["paiement_statut"] ?? "—"),
            "methode" => (string)($o["methode"] ?? ""),
            "statut_livraison" => (string)($o["statut_livraison"] ?? ""),
            "shipping_text" => shippingLabel(
                $o["statut_livraison"] ?? "",
                (string)($o["mode_reception"] ?? "PICKUP")
            ),
            "statut_livraison_updated_at" => $o["statut_livraison_updated_at"] ?? null,
            "buyer_seen" => (int)($o["buyer_seen"] ?? 1),
            "titre" => (string)($sum["titre"] ?? ""),
            "cover_image_url" => $sum ? coverUrl($sum) : null,
            "count_items" => (int)($sum["count_items"] ?? 0),
            "id_vendeur" => (int)($sum["id_vendeur"] ?? 0),
            "vendeur_nom" => (string)($sum["vendeur_nom"] ?? "")
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