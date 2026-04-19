<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$userId = (int)($_GET["user_id"] ?? 0);

if ($userId <= 0) {
    echo json_encode([
        "ok" => false,
        "message" => "Connexion requise."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function notifIconMobile($type) {
    $type = strtoupper(trim($type));

    switch ($type) {
        case "NEW_ORDER":
            return ["bag-handle-outline", "#2563eb", "#dbeafe"];
        case "ORDER_UPDATE":
            return ["car-outline", "#d97706", "#fef3c7"];
        case "ORDER_DELIVERED":
            return ["checkmark-circle-outline", "#16a34a", "#dcfce7"];
        case "ANNONCE_VALIDATED":
            return ["checkmark-done-circle-outline", "#16a34a", "#dcfce7"];
        case "ANNONCE_REFUSED":
            return ["close-circle-outline", "#dc2626", "#fee2e2"];
        case "ANNONCE_DELETED":
            return ["trash-outline", "#dc2626", "#fee2e2"];
        case "NEW_MESSAGE":
            return ["chatbubble-ellipses-outline", "#0891b2", "#cffafe"];
        case "NEW_REVIEW":
            return ["star-outline", "#d97706", "#fef3c7"];
        case "ADMIN_ANNONCE_WAIT":
            return ["hourglass-outline", "#d97706", "#fef3c7"];
        case "ADMIN_NEW_ORDER":
            return ["receipt-outline", "#2563eb", "#dbeafe"];
        case "ADMIN_NEW_USER":
            return ["person-add-outline", "#0891b2", "#cffafe"];
        case "ORDER_STATUS":
            return ["car-outline", "#d97706", "#fef3c7"];
        case "ADMIN_SIGNALEMENT":
            return ["flag-outline", "#dc2626", "#fee2e2"];
        case "SIGNALEMENT_ENVOYE":
            return ["flag-outline", "#d97706", "#fef3c7"];
        default:
            return ["notifications-outline", "#6b7280", "#f3f4f6"];
    }
}

function resolveMobileRoute($link) {
    $link = trim($link);

    if ($link === "") {
        return "/notifications";
    }

    if (stripos($link, "messages.php") !== false) {
        return "/messages";
    }

    if (stripos($link, "mes_commandes.php") !== false) {
        return "/mes-commandes";
    }

    if (stripos($link, "mes_ventes.php") !== false) {
        return "/mes-ventes";
    }

    if (stripos($link, "mes_annonces.php") !== false) {
        return "/mes-annonces";
    }

    if (stripos($link, "profil.php") !== false) {
        return "/mon-compte";
    }

    if (stripos($link, "notifications.php") !== false) {
        return "/notifications";
    }

    if (preg_match('/annonce\.php\?id=(\d+)/i', $link, $m)) {
        return "/annonce/" . (int)$m[1];
    }

    if (preg_match('/commande\.php\?id=(\d+)/i', $link, $m)) {
        return "/commande/" . (int)$m[1];
    }

    if (preg_match('/vente\.php\?id=(\d+)/i', $link, $m)) {
        return "/vente/" . (int)$m[1];
    }

    return "/notifications";
}

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notification
        WHERE id_user = ?
          AND COALESCE(is_read, 0) = 0
    ");
    $stmt->execute([$userId]);
    $badge = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT *
        FROM notification
        WHERE id_user = ?
          AND COALESCE(is_popup_seen, 0) = 0
        ORDER BY id_notification DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $popupNotif = $stmt->fetch(PDO::FETCH_ASSOC);

    $popup = null;

    if ($popupNotif) {
        $iconData = notifIconMobile((string)($popupNotif["type_notification"] ?? ""));
        $icon = $iconData[0];
        $iconColor = $iconData[1];
        $iconBg = $iconData[2];

        $popup = [
            "id_notification" => (int)$popupNotif["id_notification"],
            "type_notification" => (string)($popupNotif["type_notification"] ?? ""),
            "titre" => (string)($popupNotif["titre"] ?? "Notification"),
            "contenu" => (string)($popupNotif["contenu"] ?? ""),
            "lien" => (string)($popupNotif["lien"] ?? ""),
            "mobile_route" => resolveMobileRoute((string)($popupNotif["lien"] ?? "")),
            "is_read" => (int)($popupNotif["is_read"] ?? 0),
            "is_popup_seen" => (int)($popupNotif["is_popup_seen"] ?? 0),
            "created_at" => (string)($popupNotif["created_at"] ?? ""),
            "icon" => $icon,
            "icon_color" => $iconColor,
            "icon_bg" => $iconBg
        ];
    }

    echo json_encode([
        "ok" => true,
        "badge" => $badge,
        "popup" => $popup
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur : " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}