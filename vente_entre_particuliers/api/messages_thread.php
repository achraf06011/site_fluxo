<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$userId    = (int)($_GET["user_id"] ?? 0);
$annonceId = (int)($_GET["annonce"] ?? 0);
$toId      = (int)($_GET["to"] ?? 0);

if ($userId <= 0 || $annonceId <= 0 || $toId <= 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "Paramètres invalides"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $hasReadCols = false;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM message")->fetchAll(PDO::FETCH_COLUMN, 0);
        $hasReadCols = in_array("is_lu", $cols, true) && in_array("date_lu", $cols, true);
    } catch (Exception $e) {
        $hasReadCols = false;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM annonce
        WHERE id_annonce = ?
        LIMIT 1
    ");
    $stmt->execute([$annonceId]);
    $annonce = $stmt->fetch();

    if (!$annonce) {
        http_response_code(404);
        echo json_encode([
            "ok" => false,
            "message" => "Annonce introuvable"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id_user, nom
        FROM user
        WHERE id_user = ?
        LIMIT 1
    ");
    $stmt->execute([$toId]);
    $other = $stmt->fetch();

    if (!$other) {
        http_response_code(404);
        echo json_encode([
            "ok" => false,
            "message" => "Destinataire introuvable"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mode = (string)($annonce["mode_vente"] ?? "");
    $canChat = in_array($mode, ["POSSIBILITE_CONTACTE", "LES_DEUX"], true);

    if ($hasReadCols) {
        $stmt = $pdo->prepare("
            UPDATE message
            SET is_lu = 1, date_lu = NOW()
            WHERE id_annonce = ?
              AND id_expediteur = ?
              AND id_destinataire = ?
              AND is_lu = 0
        ");
        $stmt->execute([$annonceId, $toId, $userId]);
    }

    $stmt = $pdo->prepare("
        SELECT m.*
        FROM message m
        WHERE m.id_annonce = ?
          AND (
            (m.id_expediteur = ? AND m.id_destinataire = ?)
            OR
            (m.id_expediteur = ? AND m.id_destinataire = ?)
          )
        ORDER BY m.date_envoi ASC, m.id_message ASC
        LIMIT 300
    ");
    $stmt->execute([$annonceId, $userId, $toId, $toId, $userId]);
    $thread = $stmt->fetchAll();

    $messages = array_map(function ($m) use ($userId) {
        return [
            "id_message" => (int)$m["id_message"],
            "sender" => ((int)$m["id_expediteur"] === $userId) ? "me" : "other",
            "contenu" => (string)($m["contenu"] ?? ""),
            "date_envoi" => (string)($m["date_envoi"] ?? ""),
            "id_expediteur" => (int)($m["id_expediteur"] ?? 0),
            "id_destinataire" => (int)($m["id_destinataire"] ?? 0),
            "id_annonce" => (int)($m["id_annonce"] ?? 0),
        ];
    }, $thread);

    echo json_encode([
        "ok" => true,
        "conversation" => [
            "annonce_id" => (int)$annonceId,
            "other_id" => (int)$toId,
            "other_nom" => (string)($other["nom"] ?? ("User#" . $toId)),
            "annonce_titre" => (string)($annonce["titre"] ?? ("Annonce#" . $annonceId)),
            "mode_vente" => (string)($annonce["mode_vente"] ?? ""),
            "can_chat" => $canChat,
        ],
        "messages" => $messages
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur"
    ], JSON_UNESCAPED_UNICODE);
}