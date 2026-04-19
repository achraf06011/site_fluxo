<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$userId = (int)($_GET["user_id"] ?? 0);

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "Utilisateur invalide"
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

    $totalUnread = 0;
    if ($hasReadCols) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM message WHERE id_destinataire = ? AND is_lu = 0");
        $stmt->execute([$userId]);
        $totalUnread = (int)$stmt->fetchColumn();
    }

    if ($hasReadCols) {
        $sqlConv = "
            SELECT
              m.id_annonce,
              CASE
                WHEN m.id_expediteur = ? THEN m.id_destinataire
                ELSE m.id_expediteur
              END AS other_id,
              MAX(m.date_envoi) AS last_date,
              SUM(CASE WHEN m.id_destinataire = ? AND m.is_lu = 0 THEN 1 ELSE 0 END) AS unread_count
            FROM message m
            WHERE m.id_expediteur = ? OR m.id_destinataire = ?
            GROUP BY m.id_annonce, other_id
            ORDER BY last_date DESC
            LIMIT 80
        ";
        $stmt = $pdo->prepare($sqlConv);
        $stmt->execute([$userId, $userId, $userId, $userId]);
    } else {
        $sqlConv = "
            SELECT
              m.id_annonce,
              CASE
                WHEN m.id_expediteur = ? THEN m.id_destinataire
                ELSE m.id_expediteur
              END AS other_id,
              MAX(m.date_envoi) AS last_date,
              0 AS unread_count
            FROM message m
            WHERE m.id_expediteur = ? OR m.id_destinataire = ?
            GROUP BY m.id_annonce, other_id
            ORDER BY last_date DESC
            LIMIT 80
        ";
        $stmt = $pdo->prepare($sqlConv);
        $stmt->execute([$userId, $userId, $userId]);
    }

    $convsRaw = $stmt->fetchAll();
    $convs = [];

    if ($convsRaw) {
        foreach ($convsRaw as $c) {
            $otherId = (int)$c["other_id"];
            $aId = (int)$c["id_annonce"];

            $stmt2 = $pdo->prepare("
                SELECT m.*,
                       a.titre AS annonce_titre,
                       a.mode_vente,
                       a.statut AS annonce_statut,
                       u.nom AS other_nom
                FROM message m
                JOIN annonce a ON a.id_annonce = m.id_annonce
                JOIN user u ON u.id_user = ?
                WHERE m.id_annonce = ?
                  AND (
                    (m.id_expediteur = ? AND m.id_destinataire = ?)
                    OR
                    (m.id_expediteur = ? AND m.id_destinataire = ?)
                  )
                ORDER BY m.date_envoi DESC, m.id_message DESC
                LIMIT 1
            ");
            $stmt2->execute([$otherId, $aId, $userId, $otherId, $otherId, $userId]);
            $last = $stmt2->fetch();

            if ($last) {
                $convs[] = [
                    "id_annonce" => $aId,
                    "other_id" => $otherId,
                    "other_nom" => $last["other_nom"] ?? ("User#" . $otherId),
                    "annonce_titre" => $last["annonce_titre"] ?? ("Annonce#" . $aId),
                    "annonce_statut" => $last["annonce_statut"] ?? "",
                    "mode_vente" => $last["mode_vente"] ?? "",
                    "last_date" => $last["date_envoi"] ?? "",
                    "last_contenu" => $last["contenu"] ?? "",
                    "unread_count" => (int)($c["unread_count"] ?? 0),
                ];
            }
        }
    }

    echo json_encode([
        "ok" => true,
        "total_unread" => $totalUnread,
        "conversations" => $convs
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur"
    ], JSON_UNESCAPED_UNICODE);
}