<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$userId = (int)($data["user_id"] ?? 0);
$idAnnonce = (int)($data["id_annonce"] ?? 0);

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
    $stmt = $pdo->prepare("
        SELECT id_annonce, stock, statut, mode_vente
        FROM annonce
        WHERE id_annonce = ?
        LIMIT 1
    ");
    $stmt->execute([$idAnnonce]);
    $a = $stmt->fetch();

    if (!$a) {
        echo json_encode([
            "ok" => false,
            "message" => "Annonce introuvable."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (($a["statut"] ?? "") !== "ACTIVE") {
        echo json_encode([
            "ok" => false,
            "message" => "Annonce non disponible."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!in_array($a["mode_vente"], ["PAIEMENT_DIRECT", "LES_DEUX"], true)) {
        echo json_encode([
            "ok" => false,
            "message" => "Cette annonce n’est pas disponible en paiement direct."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stock = (int)($a["stock"] ?? 0);
    if ($stock < 1) {
        echo json_encode([
            "ok" => false,
            "message" => "Stock insuffisant."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

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

    $pdo->beginTransaction();

    $pdo->prepare("DELETE FROM panier_item WHERE id_panier = ?")->execute([$panierId]);

    $stmt = $pdo->prepare("
        INSERT INTO panier_item (id_panier, id_annonce, quantity)
        VALUES (?, ?, 1)
    ");
    $stmt->execute([$panierId, $idAnnonce]);

    $pdo->commit();

    echo json_encode([
        "ok" => true,
        "message" => "Produit prêt pour le checkout."
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur."
    ], JSON_UNESCAPED_UNICODE);
}