<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$userId    = (int)($data["user_id"] ?? 0);
$idAnnonce = (int)($data["id_annonce"] ?? 0);
$toId      = (int)($data["to"] ?? 0);
$contenu   = trim((string)($data["contenu"] ?? ""));

if ($userId <= 0 || $idAnnonce <= 0 || $toId <= 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "Paramètres invalides"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($contenu === "" || mb_strlen($contenu) < 1) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "Message vide."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($contenu) > 2000) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "Message trop long (max 2000 caractères)."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($toId === $userId) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "Tu ne peux pas t’envoyer un message à toi-même."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id_annonce, titre, mode_vente, statut, id_vendeur
        FROM annonce
        WHERE id_annonce = ?
        LIMIT 1
    ");
    $stmt->execute([$idAnnonce]);
    $annonce = $stmt->fetch();

    if (!$annonce) {
        http_response_code(404);
        echo json_encode([
            "ok" => false,
            "message" => "Annonce introuvable."
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
    $dest = $stmt->fetch();

    if (!$dest) {
        http_response_code(404);
        echo json_encode([
            "ok" => false,
            "message" => "Destinataire introuvable."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mode = (string)($annonce["mode_vente"] ?? "");
    $canChat = in_array($mode, ["POSSIBILITE_CONTACTE", "LES_DEUX"], true);

    if (!$canChat) {
        http_response_code(400);
        echo json_encode([
            "ok" => false,
            "message" => "Cette annonce n’autorise pas la discussion."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO message (
            contenu,
            date_envoi,
            id_expediteur,
            id_destinataire,
            id_annonce,
            is_lu
        )
        VALUES (?, NOW(), ?, ?, ?, 0)
    ");
    $stmt->execute([
        $contenu,
        $userId,
        $toId,
        $idAnnonce
    ]);

    $newMessageId = (int)$pdo->lastInsertId();

    try {
        $titreNotif = "Nouveau message";
        $contenuNotif = "Tu as reçu un nouveau message à propos de : " . ($annonce["titre"] ?? ("Annonce #" . $idAnnonce));

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
            $toId,
            "NEW_MESSAGE",
            $titreNotif,
            $contenuNotif,
            "messages.php?annonce=" . $idAnnonce . "&to=" . $userId
        ]);
    } catch (Exception $e) {
        // ne bloque pas l'envoi si la notif échoue
    }

    echo json_encode([
        "ok" => true,
        "message" => "Message envoyé.",
        "message_item" => [
            "id_message" => $newMessageId,
            "sender" => "me",
            "contenu" => $contenu,
            "date_envoi" => date("Y-m-d H:i:s"),
            "id_expediteur" => $userId,
            "id_destinataire" => $toId,
            "id_annonce" => $idAnnonce
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur."
    ], JSON_UNESCAPED_UNICODE);
}