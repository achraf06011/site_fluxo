<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$userId = (int)($_GET["user_id"] ?? 0);

if ($userId <= 0) {
    echo json_encode([
        "ok" => false,
        "message" => "Utilisateur invalide"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sql = "
        SELECT
            a.id_annonce,
            a.id_vendeur,
            a.titre,
            a.prix,
            a.ancien_prix,
            a.ville,
            a.stock,
            a.mode_vente,
            a.cover_image,
            a.date_publication,
            u.nom AS vendeur_nom,
            1 AS is_favori
        FROM favoris f
        JOIN annonce a ON a.id_annonce = f.id_annonce
        JOIN user u ON u.id_user = a.id_vendeur
        WHERE f.id_user = ?
          AND a.statut = 'ACTIVE'
        ORDER BY f.id_favori DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "ok" => true,
        "annonces" => $annonces
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur"
    ], JSON_UNESCAPED_UNICODE);
}