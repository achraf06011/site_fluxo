<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$userId = (int)($data["user_id"] ?? 0);
$idAnnonce = (int)($data["id_annonce"] ?? 0);
$qty = (int)($data["qty"] ?? 1);
if ($qty < 1) $qty = 1;

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        "ok" => false,
        "message" => "Connexion requise."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($idAnnonce <= 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "Annonce invalide."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
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

    $stmt = $pdo->prepare("
        SELECT id_annonce, stock, mode_vente, statut
        FROM annonce
        WHERE id_annonce = ?
        LIMIT 1
    ");
    $stmt->execute([$idAnnonce]);
    $a = $stmt->fetch();

    if (!$a || ($a["statut"] ?? "") !== "ACTIVE") {
        echo json_encode([
            "ok" => false,
            "message" => "Annonce introuvable ou inactive."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!in_array($a["mode_vente"], ["PAIEMENT_DIRECT", "LES_DEUX"], true)) {
        echo json_encode([
            "ok" => false,
            "message" => "Ce produit n'est pas disponible en paiement direct."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stock = (int)$a["stock"];
    if ($stock <= 0) {
        echo json_encode([
            "ok" => false,
            "message" => "Stock insuffisant."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id_panier_item, quantity
        FROM panier_item
        WHERE id_panier = ? AND id_annonce = ?
        LIMIT 1
    ");
    $stmt->execute([$panierId, $idAnnonce]);
    $item = $stmt->fetch();

    if ($item) {
        $newQty = (int)$item["quantity"] + $qty;
        if ($newQty > $stock) $newQty = $stock;

        $pdo->prepare("UPDATE panier_item SET quantity = ? WHERE id_panier_item = ?")
            ->execute([$newQty, (int)$item["id_panier_item"]]);
    } else {
        $addQty = min($qty, $stock);
        $pdo->prepare("
            INSERT INTO panier_item (id_panier, id_annonce, quantity)
            VALUES (?, ?, ?)
        ")->execute([$panierId, $idAnnonce, $addQty]);
    }

    echo json_encode([
        "ok" => true,
        "message" => "Ajouté au panier."
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur."
    ], JSON_UNESCAPED_UNICODE);
}