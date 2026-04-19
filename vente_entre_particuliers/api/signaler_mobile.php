<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$currentUserId = (int)($data["user_id"] ?? 0);
$idAnnonce = (int)($data["id_annonce"] ?? 0);
$motif = trim((string)($data["motif"] ?? ""));
$description = trim((string)($data["description"] ?? ""));

if ($currentUserId <= 0) {
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

$allowedMotifs = [
    "ARNAQUE",
    "FAUSSE_ANNONCE",
    "CONTENU_INTERDIT",
    "PRIX_SUSPECT",
    "SPAM",
    "AUTRE"
];

if (!in_array($motif, $allowedMotifs, true)) {
    echo json_encode([
        "ok" => false,
        "message" => "Motif invalide."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT a.id_annonce, a.id_vendeur, a.titre, a.statut
        FROM annonce a
        WHERE a.id_annonce = ?
        LIMIT 1
    ");
    $stmt->execute([$idAnnonce]);
    $annonce = $stmt->fetch();

    if (!$annonce) {
        echo json_encode([
            "ok" => false,
            "message" => "Annonce introuvable."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int)$annonce["id_vendeur"] === $currentUserId) {
        echo json_encode([
            "ok" => false,
            "message" => "Tu ne peux pas signaler ta propre annonce."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id_signalement
        FROM signalement
        WHERE id_annonce = ?
          AND id_user = ?
          AND statut = 'EN_ATTENTE'
        LIMIT 1
    ");
    $stmt->execute([$idAnnonce, $currentUserId]);
    $already = $stmt->fetch();

    if ($already) {
        echo json_encode([
            "ok" => false,
            "message" => "Tu as déjà signalé cette annonce."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO signalement (
          id_annonce,
          id_user,
          motif,
          description,
          statut,
          created_at
        )
        VALUES (?, ?, ?, ?, 'EN_ATTENTE', NOW())
    ");
    $stmt->execute([
        $idAnnonce,
        $currentUserId,
        $motif,
        $description !== "" ? $description : null
    ]);

    try {
        $stmtAdmins = $pdo->query("SELECT id_user FROM user WHERE role = 'ADMIN'");
        $admins = $stmtAdmins->fetchAll();

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
            VALUES (?, 'ADMIN_SIGNALEMENT', ?, ?, ?, 0, NOW(), 0)
        ");

        $titreNotif = "Nouveau signalement";
        $contenuNotif = "Un utilisateur a signalé l'annonce \"" . $annonce["titre"] . "\".";
        $lienNotif = "../admin/signalements.php";

        foreach ($admins as $admin) {
            $stmtNotif->execute([
                (int)$admin["id_user"],
                $titreNotif,
                $contenuNotif,
                $lienNotif
            ]);
        }
    } catch (Exception $e) {
    }

    try {
        $stmtUserNotif = $pdo->prepare("
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
            VALUES (?, 'SIGNALEMENT_ENVOYE', ?, ?, ?, 0, NOW(), 0)
        ");
        $stmtUserNotif->execute([
            $currentUserId,
            "Signalement envoyé",
            "Ton signalement pour l'annonce \"" . $annonce["titre"] . "\" a bien été envoyé.",
            "annonce.php?id=" . $idAnnonce
        ]);
    } catch (Exception $e) {
    }

    $pdo->commit();

    echo json_encode([
        "ok" => true,
        "message" => "Signalement envoyé avec succès."
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur lors du signalement."
    ], JSON_UNESCAPED_UNICODE);
}