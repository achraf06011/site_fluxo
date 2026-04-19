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

function resolveMobileRoute(string $link): string {
    $link = trim($link);

    if ($link === "") return "/notifications";

    if (stripos($link, "messages.php") !== false) return "/messages";
    if (stripos($link, "mes_commandes.php") !== false) return "/mes-commandes";
    if (stripos($link, "mes_ventes.php") !== false) return "/mes-ventes";
    if (stripos($link, "mes_annonces.php") !== false) return "/mes-annonces";
    if (stripos($link, "profil.php") !== false) return "/mon-compte";
    if (stripos($link, "notifications.php") !== false) return "/notifications";

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
        UPDATE notification
        SET is_read = 1
        WHERE id_user = ?
          AND COALESCE(is_read, 0) = 0
    ");
    $stmt->execute([$userId]);

    $stmt = $pdo->prepare("
        SELECT *
        FROM notification
        WHERE id_user = ?
        ORDER BY id_notification DESC
        LIMIT 100
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $notifications = [];

    foreach ($rows as $n) {
        $notifications[] = [
            "id_notification" => (int)$n["id_notification"],
            "titre" => (string)($n["titre"] ?? "Notification"),
            "contenu" => (string)($n["contenu"] ?? ""),
            "created_at" => (string)($n["created_at"] ?? ""),
            "is_read" => (int)($n["is_read"] ?? 0),
            "href" => (string)($n["lien"] ?? ""),
            "mobile_route" => resolveMobileRoute((string)($n["lien"] ?? "")),
            "type_notification" => (string)($n["type_notification"] ?? ""),
        ];
    }

    echo json_encode([
        "ok" => true,
        "notifications" => $notifications
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur : " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}