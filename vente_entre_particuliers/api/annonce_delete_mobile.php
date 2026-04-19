<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$userId = (int)($data["user_id"] ?? 0);
$id = (int)($data["id_annonce"] ?? 0);

if ($userId <= 0) {
    echo json_encode([
        "ok" => false,
        "message" => "Connexion requise."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($id <= 0) {
    echo json_encode([
        "ok" => false,
        "message" => "Annonce invalide."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id_annonce, titre, statut, cover_image
        FROM annonce
        WHERE id_annonce = ? AND id_vendeur = ?
        LIMIT 1
    ");
    $stmt->execute([$id, $userId]);
    $ann = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ann) {
        $pdo->rollBack();
        echo json_encode([
            "ok" => false,
            "message" => "Annonce introuvable."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM order_details
        WHERE id_annonce = ?
    ");
    $stmt->execute([$id]);
    $usedInOrders = (int)$stmt->fetchColumn() > 0;

    if ($usedInOrders) {
        $stmt = $pdo->prepare("
            UPDATE annonce
            SET statut = 'DESACTIVEE'
            WHERE id_annonce = ? AND id_vendeur = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $userId]);

        try {
            $pdo->prepare("DELETE FROM panier_item WHERE id_annonce = ?")->execute([$id]);
        } catch (Exception $e) {
        }

        $pdo->commit();

        echo json_encode([
            "ok" => true,
            "message" => "Annonce désactivée car elle est déjà liée à une commande."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id_image, url
        FROM annonce_image
        WHERE id_annonce = ?
    ");
    $stmt->execute([$id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    try {
        $pdo->prepare("DELETE FROM annonce_image WHERE id_annonce = ?")->execute([$id]);
    } catch (Exception $e) {
    }

    try {
        $pdo->prepare("DELETE FROM review WHERE id_annonce = ?")->execute([$id]);
    } catch (Exception $e) {
    }

    try {
        $pdo->prepare("DELETE FROM message WHERE id_annonce = ?")->execute([$id]);
    } catch (Exception $e) {
    }

    try {
        $pdo->prepare("DELETE FROM panier_item WHERE id_annonce = ?")->execute([$id]);
    } catch (Exception $e) {
    }

    $stmt = $pdo->prepare("
        DELETE FROM annonce
        WHERE id_annonce = ? AND id_vendeur = ?
        LIMIT 1
    ");
    $stmt->execute([$id, $userId]);

    $pdo->commit();

    $cover = $ann["cover_image"] ?? null;
    if (!empty($cover) && !str_starts_with((string)$cover, "http")) {
        $coverAbs = __DIR__ . "/../uploads/" . $cover;
        if (is_file($coverAbs)) {
            @unlink($coverAbs);
        }
    }

    foreach ($images as $img) {
        $url = (string)($img["url"] ?? "");
        $rel = ltrim($url, "/");
        if (str_starts_with($rel, "uploads/")) {
            $abs = __DIR__ . "/../" . $rel;
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }

    echo json_encode([
        "ok" => true,
        "message" => "Annonce supprimée avec succès."
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur suppression : " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}