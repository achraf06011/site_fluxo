<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$currentUserId = (int)($_GET["current_user_id"] ?? 0);

$search = trim((string)($_GET["search"] ?? ""));
$ville = trim((string)($_GET["ville"] ?? ""));
$categorie = trim((string)($_GET["categorie"] ?? ""));
$marque = trim((string)($_GET["marque"] ?? ""));
$prixMin = trim((string)($_GET["prix_min"] ?? ""));
$prixMax = trim((string)($_GET["prix_max"] ?? ""));
$tri = trim((string)($_GET["tri"] ?? "recent"));

try {
    $favoriSelect = "0 AS is_favori";
    $params = [];

    if ($currentUserId > 0) {
        $favoriSelect = "
            EXISTS(
                SELECT 1
                FROM favoris f
                WHERE f.id_annonce = a.id_annonce
                  AND f.id_user = ?
            ) AS is_favori
        ";
        $params[] = $currentUserId;
    }

    $where = ["a.statut = 'ACTIVE'"];

    if ($search !== "") {
        $where[] = "(
            a.titre LIKE ?
            OR a.description LIKE ?
            OR COALESCE(a.marque, '') LIKE ?
            OR COALESCE(a.ville, '') LIKE ?
            OR COALESCE(a.categorie, '') LIKE ?
            OR COALESCE(u.nom, '') LIKE ?
        )";
        $searchLike = "%" . $search . "%";
        $params[] = $searchLike;
        $params[] = $searchLike;
        $params[] = $searchLike;
        $params[] = $searchLike;
        $params[] = $searchLike;
        $params[] = $searchLike;
    }

    if ($ville !== "" && strtoupper($ville) !== "TOUTES") {
        $where[] = "a.ville = ?";
        $params[] = $ville;
    }

    if ($categorie !== "" && strtoupper($categorie) !== "TOUTES") {
        $where[] = "a.categorie = ?";
        $params[] = $categorie;
    }

    if ($marque !== "" && strtoupper($marque) !== "TOUTES") {
        $where[] = "a.marque = ?";
        $params[] = $marque;
    }

    if ($prixMin !== "" && is_numeric($prixMin)) {
        $where[] = "a.prix >= ?";
        $params[] = (float)$prixMin;
    }

    if ($prixMax !== "" && is_numeric($prixMax)) {
        $where[] = "a.prix <= ?";
        $params[] = (float)$prixMax;
    }

    switch ($tri) {
        case "prix_asc":
            $orderBy = "a.prix ASC, a.id_annonce DESC";
            break;

        case "prix_desc":
            $orderBy = "a.prix DESC, a.id_annonce DESC";
            break;

        case "promo":
            $orderBy = "
                CASE
                    WHEN a.ancien_prix IS NOT NULL AND a.ancien_prix > a.prix THEN 0
                    ELSE 1
                END ASC,
                (a.ancien_prix - a.prix) DESC,
                a.id_annonce DESC
            ";
            break;

        case "stock_desc":
            $orderBy = "a.stock DESC, a.id_annonce DESC";
            break;

        default:
            $orderBy = "a.date_publication DESC, a.id_annonce DESC";
            break;
    }

    $sql = "
        SELECT
            a.id_annonce,
            a.id_vendeur,
            a.titre,
            a.description,
            a.prix,
            a.ancien_prix,
            a.ville,
            a.stock,
            a.mode_vente,
            a.cover_image,
            a.date_publication,
            a.categorie,
            a.marque,
            u.nom AS vendeur_nom,
            $favoriSelect
        FROM annonce a
        JOIN user u ON u.id_user = a.id_vendeur
        WHERE " . implode(" AND ", $where) . "
        ORDER BY $orderBy
        LIMIT 100
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "ok" => true,
        "annonces" => $annonces
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur : " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}