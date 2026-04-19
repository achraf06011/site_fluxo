<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/db.php";

$idVendeur = (int)($_GET["id"] ?? 0);
$currentUserId = (int)($_GET["current_user_id"] ?? 0);

if ($idVendeur <= 0) {
    echo json_encode([
        "ok" => false,
        "message" => "Vendeur invalide."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function modeLabel($mode) {
    switch ($mode) {
        case "PAIEMENT_DIRECT":
            return "PAIEMENT DIRECT";
        case "POSSIBILITE_CONTACTE":
            return "CONTACTER LE VENDEUR";
        case "LES_DEUX":
            return "PAIEMENT DIRECT OU CONTACTER VENDEUR";
        default:
            return $mode;
    }
}

function imgUrlFromRow($x) {
    $file = isset($x["cover_image"]) ? $x["cover_image"] : null;

    if (!$file) {
        return null;
    }

    if (strpos($file, "http") === 0) {
        return $file;
    }

    return "http://" . ($_SERVER["HTTP_HOST"] ?? "192.168.1.13") . "/pfe_fluxo/vente_entre_particuliers/uploads/" . ltrim($file, "/");
}

function starsArray($rating) {
    $stars = [];

    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $stars[] = "full";
        } elseif ($rating >= ($i - 0.5)) {
            $stars[] = "half";
        } else {
            $stars[] = "empty";
        }
    }

    return $stars;
}

try {
    $stmt = $pdo->prepare("
        SELECT id_user, nom, email, date_inscription, role, statut
        FROM user
        WHERE id_user = ?
        LIMIT 1
    ");
    $stmt->execute([$idVendeur]);
    $vendeur = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendeur) {
        echo json_encode([
            "ok" => false,
            "message" => "Vendeur introuvable."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stats = [
        "total_annonces" => 0,
        "annonces_actives" => 0,
        "avg_note" => 0,
        "total_reviews" => 0
    ];

    try {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_annonces,
                SUM(CASE WHEN statut = 'ACTIVE' THEN 1 ELSE 0 END) AS annonces_actives
            FROM annonce
            WHERE id_vendeur = ?
        ");
        $stmt->execute([$idVendeur]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $stats["total_annonces"] = (int)($row["total_annonces"] ?? 0);
            $stats["annonces_actives"] = (int)($row["annonces_actives"] ?? 0);
        }
    } catch (Exception $e) {
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                AVG(note) AS avg_note,
                COUNT(id_review) AS total_reviews
            FROM review
            WHERE id_seller = ?
        ");
        $stmt->execute([$idVendeur]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $stats["avg_note"] = round((float)($row["avg_note"] ?? 0), 1);
            $stats["total_reviews"] = (int)($row["total_reviews"] ?? 0);
        }
    } catch (Exception $e) {
    }

    $reviewColumns = [];
    $commentColumn = null;
    $dateColumn = null;

    try {
        $reviewColumns = $pdo->query("SHOW COLUMNS FROM review")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $reviewColumns = [];
    }

    $possibleCommentCols = ["commentaire", "comment", "contenu", "avis", "description"];
    foreach ($possibleCommentCols as $col) {
        if (in_array($col, $reviewColumns, true)) {
            $commentColumn = $col;
            break;
        }
    }

    $possibleDateCols = ["date_review", "date_avis", "created_at", "date_creation", "date_add"];
    foreach ($possibleDateCols as $col) {
        if (in_array($col, $reviewColumns, true)) {
            $dateColumn = $col;
            break;
        }
    }

    $reviews = [];

    try {
        $commentSelect = $commentColumn ? "r.`$commentColumn` AS review_comment" : "NULL AS review_comment";
        $dateSelect = $dateColumn ? "r.`$dateColumn` AS review_date" : "NULL AS review_date";
        $dateOrder = $dateColumn ? "r.`$dateColumn` DESC" : "r.id_review DESC";

        $sqlReviews = "
            SELECT
                r.id_review,
                r.id_user,
                r.id_seller,
                r.note,
                $commentSelect,
                $dateSelect,
                u.nom AS acheteur_nom
            FROM review r
            JOIN user u ON u.id_user = r.id_user
            WHERE r.id_seller = ?
            ORDER BY $dateOrder, r.id_review DESC
            LIMIT 12
        ";

        $stmt = $pdo->prepare($sqlReviews);
        $stmt->execute([$idVendeur]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $reviews[] = [
                "id_review" => (int)($r["id_review"] ?? 0),
                "acheteur_nom" => (string)($r["acheteur_nom"] ?? "Utilisateur"),
                "note" => (float)($r["note"] ?? 0),
                "review_comment" => (string)($r["review_comment"] ?? ""),
                "review_date" => (string)($r["review_date"] ?? ""),
                "stars" => starsArray((float)($r["note"] ?? 0))
            ];
        }
    } catch (Exception $e) {
        $reviews = [];
    }

    $annonces = [];

    $favoriSelect = "0 AS is_favori";
    $favoriParams = [$idVendeur];

    if ($currentUserId > 0) {
        $favoriSelect = "EXISTS(
            SELECT 1
            FROM favoris f
            WHERE f.id_annonce = a.id_annonce
              AND f.id_user = ?
        ) AS is_favori";
        $favoriParams = [$currentUserId, $idVendeur];
    }

    $stmt = $pdo->prepare("
        SELECT
            a.*,
            u.nom AS vendeur_nom,
            $favoriSelect
        FROM annonce a
        JOIN user u ON u.id_user = a.id_vendeur
        WHERE a.id_vendeur = ?
          AND a.statut = 'ACTIVE'
        ORDER BY a.id_annonce DESC
        LIMIT 60
    ");
    $stmt->execute($favoriParams);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $a) {
        $modeV = (string)($a["mode_vente"] ?? "");

        $annonces[] = [
            "id_annonce" => (int)($a["id_annonce"] ?? 0),
            "titre" => (string)($a["titre"] ?? ""),
            "prix" => (float)($a["prix"] ?? 0),
            "ville" => (string)($a["ville"] ?? ""),
            "stock" => (int)($a["stock"] ?? 0),
            "mode_vente" => $modeV,
            "mode_label" => modeLabel($modeV),
            "cover_image_url" => imgUrlFromRow($a),
            "is_favori" => !empty($a["is_favori"]),
            "can_buy" => in_array($modeV, ["PAIEMENT_DIRECT", "LES_DEUX"], true),
            "can_chat" => in_array($modeV, ["POSSIBILITE_CONTACTE", "LES_DEUX"], true)
        ];
    }

    $isOwnProfile = $currentUserId > 0 && $currentUserId === (int)$vendeur["id_user"];

    echo json_encode([
        "ok" => true,
        "vendeur" => [
            "id_user" => (int)$vendeur["id_user"],
            "nom" => (string)$vendeur["nom"],
            "email" => (string)($vendeur["email"] ?? ""),
            "date_inscription" => (string)($vendeur["date_inscription"] ?? ""),
            "role" => (string)($vendeur["role"] ?? ""),
            "statut" => (string)($vendeur["statut"] ?? ""),
            "initiale" => strtoupper(substr((string)$vendeur["nom"], 0, 1))
        ],
        "stats" => [
            "total_annonces" => (int)$stats["total_annonces"],
            "annonces_actives" => (int)$stats["annonces_actives"],
            "avg_note" => (float)$stats["avg_note"],
            "total_reviews" => (int)$stats["total_reviews"],
            "stars" => starsArray((float)$stats["avg_note"])
        ],
        "reviews" => $reviews,
        "annonces" => $annonces,
        "isOwnProfile" => $isOwnProfile
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur : " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}