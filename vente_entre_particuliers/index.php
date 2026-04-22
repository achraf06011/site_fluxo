<?php
require_once "config/db.php";
require_once "config/auth.php";

function e($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

function modeLabel(string $mode) {
  return match ($mode) {
    "PAIEMENT_DIRECT" => "PAIEMENT DIRECT",
    "POSSIBILITE_CONTACTE" => "CONTACTER LE VENDEUR",
    "LES_DEUX" => "PAIEMENT DIRECT OU CONTACTER VENDEUR",
    default => $mode,
  };
}

function imgUrl($x) {
  $file = $x["cover_image"] ?? null;
  if (!$file) return "https://picsum.photos/seed/" . $x["id_annonce"] . "/800/600";
  if (str_starts_with($file, "http")) return $file;
  return "uploads/" . $file;
}

function hasPromo(array $a): bool {
  return isset($a["ancien_prix"]) && $a["ancien_prix"] !== null && (float)$a["ancien_prix"] > (float)$a["prix"];
}


function normalizeSearchText(string $text): string {
  $text = mb_strtolower(trim($text), "UTF-8");
  $text = str_replace(["-", "_", ",", ".", "/", "\\", "(", ")", "+", ";", ":"], " ", $text);
  $text = preg_replace('/\s+/u', ' ', $text);
  return trim((string)$text);
}

function expandSearchWords(array $words): array {
  $synonyms = [
    "pc" => ["ordinateur", "informatique", "laptop"],
    "ordinateur" => ["pc", "informatique", "laptop"],
    "laptop" => ["pc", "ordinateur", "informatique"],
    "tel" => ["telephone", "smartphone"],
    "telephone" => ["tel", "smartphone"],
    "smartphone" => ["telephone", "tel"],
    "frigo" => ["refrigerateur"],
    "refrigerateur" => ["frigo"],
    "tv" => ["television"],
    "television" => ["tv"],
    "console" => ["jeux", "gaming"],
    "gaming" => ["console", "jeux"],
  ];

  $final = [];

  foreach ($words as $word) {
    $word = trim((string)$word);
    if ($word === "") continue;

    $final[] = $word;

    if (isset($synonyms[$word])) {
      foreach ($synonyms[$word] as $alt) {
        $final[] = $alt;
      }
    }
  }

  $final = array_values(array_unique(array_filter($final, fn($x) => $x !== "")));
  return $final;
}

$MAROC_CITIES = [
  "",
  "Agadir","Al Hoceima","Asilah","Azrou","Beni Mellal","Berkane","Boujdour",
  "Casablanca","Chefchaouen","Dakhla","El Jadida","Errachidia","Essaouira",
  "Fès","Guelmim","Ifrane","Kenitra","Khemisset","Khouribga","Laâyoune",
  "Larache","Marrakech","Meknès","Mohammedia","Nador","Ouarzazate",
  "Oujda","Rabat","Safi","Salé","Settat","Sidi Ifni","Tanger","Tarfaya",
  "Taza","Tétouan"
];

$CATEGORIES = [
  "" => [],
  "VOITURE" => ["TOYOTA","VOLKSWAGEN","BMW","MERCEDES-BENZ","AUDI","HYUNDAI","KIA","TESLA","FORD","RENAULT","PEUGEOT","HONDA","NISSAN","PORSCHE","VOLVO","MAZDA","SUZUKI","AUTRE"],
  "MOTO" => ["HONDA","YAMAHA","KAWASAKI","SUZUKI","BMW","KTM","DUCATI","TRIUMPH","HARLEY-DAVIDSON","INDIAN","ROYAL ENFIELD","APRILIA","MOTO GUZZI","HUSQVARNA","GASGAS","CFMOTO","BENELLI","AUTRE"],
  "TELEPHONE" => ["APPLE","SAMSUNG","XIAOMI","HUAWEI","GOOGLE","OPPO","VIVO","HONOR","REALME","MOTOROLA","SONY","ASUS","NOKIA","ONEPLUS","NOTHING","TECNO","INFINIX","AUTRE"],
  "INFORMATIQUE" => ["APPLE","LENOVO","HP","DELL","ASUS","ACER","MSI","SAMSUNG","MICROSOFT","RAZER","NVIDIA","INTEL","AMD","GIGABYTE","CORSAIR","LOGITECH","HUAWEI","AUTRE"],
  "TV_AUDIO" => ["SAMSUNG","LG","SONY","PANASONIC","TCL","HISENSE","PHILIPS","BOZE","SONOS","JBL","MARSHALL","BANG & OLUFSEN","DENON","SENNHEISER","BEATS","YAMAHA","APPLE","AUTRE"],
  "ELECTROMENAGER" => ["MIELE","BOSCH","SIEMENS","SAMSUNG","LG","WHIRLPOOL","ELECTROLUX","BEKO","HAIER","DYSON","MOULINEX","ROWENTA","TEFAL","SMEG","DE DIETRICH","LIEBHERR","SHARP","AUTRE"],
  "MODE" => ["ZARA","H&M","MANGO","BERSHKA","PULL&BEAR","STRADIVARIUS","MASSIMO DUTTI","UNIQLO","GAP","LEVI'S","GUESS","CALVIN KLEIN","TOMMY HILFIGER","RALPH LAUREN","LACOSTE","ASOS","SHEIN","AUTRE"],
  "MAISON" => ["IKEA","MAISONS DU MONDE","ZARA HOME","H&M HOME","LEROY MERLIN","CASTORAMA","BUT","CONFORAMA","WESTELM","POTTERY BARN"],
  "SPORT" => ["NIKE","ADIDAS","PUMA","UNDER ARMOUR","NEW BALANCE","ASICS","LULULEMON","JORDAN","SKECHERS","REEBOK","CONVERSE","THE NORTH FACE","COLUMBIA","FILA","MIZUNO","SALOMON","UMBRO","AUTRE"],
  "JEUX" => ["SONY","PLAYSTATION","MICROSOFT","NINTENDO","UBISOFT","ELECTRONIC ARTS","ROCKSTAR GAMES","ACTIVISION","BLIZZARD","EPIC GAMES","KONAMI","AUTRE"],
  "AUTRE" => ["AUTRE"]
];

$search = trim($_GET["search"] ?? "");
$type = trim($_GET["type"] ?? "");
$mode = trim($_GET["mode"] ?? "");
$ville = trim($_GET["ville"] ?? "");
$categorie = trim($_GET["categorie"] ?? "");
$marque = trim($_GET["marque"] ?? "");
$minPrix = trim($_GET["min_prix"] ?? "");
$maxPrix = trim($_GET["max_prix"] ?? "");
$tri = trim($_GET["tri"] ?? "recent");

$page = max((int)($_GET["page"] ?? 1), 1);
$limit = 12;
$offset = ($page - 1) * $limit;

$isFiltering =
  $search !== "" ||
  $type !== "" ||
  $mode !== "" ||
  $ville !== "" ||
  $categorie !== "" ||
  $marque !== "" ||
  $minPrix !== "" ||
  $maxPrix !== "" ||
  $tri !== "recent";

/* =========================
   HISTORIQUE DES RECHERCHES
   ========================= */
$lastSearches = [];

if (isLoggedIn()) {
  $userId = currentUserId();

  if ($search !== "") {
    try {
      $cleanSearch = preg_replace('/\s+/', ' ', $search);
      $cleanSearch = trim((string)$cleanSearch);

      if ($cleanSearch !== "") {
        $stmt = $pdo->prepare("
          SELECT id_historique
          FROM historique_recherche
          WHERE id_user = ? AND recherche = ?
          ORDER BY id_historique DESC
          LIMIT 1
        ");
        $stmt->execute([$userId, $cleanSearch]);
        $existing = $stmt->fetch();

        if ($existing) {
          $stmt = $pdo->prepare("
            UPDATE historique_recherche
            SET date_recherche = NOW()
            WHERE id_historique = ?
          ");
          $stmt->execute([(int)$existing["id_historique"]]);
        } else {
          $stmt = $pdo->prepare("
            INSERT INTO historique_recherche (id_user, recherche, date_recherche)
            VALUES (?, ?, NOW())
          ");
          $stmt->execute([$userId, $cleanSearch]);
        }
      }
    } catch (Exception $e) {
    }
  }

  try {
    $stmt = $pdo->prepare("
      SELECT recherche, MAX(date_recherche) AS last_date
      FROM historique_recherche
      WHERE id_user = ?
      GROUP BY recherche
      ORDER BY last_date DESC
      LIMIT 6
    ");
    $stmt->execute([$userId]);
    $lastSearches = $stmt->fetchAll();
  } catch (Exception $e) {
    $lastSearches = [];
  }
}

/* =========================
   ANNONCES RECEMMENT VUES
   ========================= */
$recentlyViewed = [];
if (!empty($_SESSION["recently_viewed_annonces"]) && is_array($_SESSION["recently_viewed_annonces"])) {
  $recentlyViewed = $_SESSION["recently_viewed_annonces"];
}

$where = ["a.statut = 'ACTIVE'"];
$params = [];
$scoreParts = [];
$scoreParams = [];

if ($search !== "") {
  $normalizedSearch = normalizeSearchText($search);
  $baseWords = preg_split('/\s+/u', trim($normalizedSearch));
  $baseWords = array_values(array_filter($baseWords, fn($w) => $w !== ""));
  $words = expandSearchWords($baseWords);

  if (!empty($words)) {
    $searchOrParts = [];

    foreach ($words as $word) {
      $searchOrParts[] = "(
        LOWER(REPLACE(REPLACE(REPLACE(a.titre, '-', ' '), '_', ' '), '/', ' ')) LIKE ?
        OR LOWER(REPLACE(REPLACE(REPLACE(a.description, '-', ' '), '_', ' '), '/', ' ')) LIKE ?
        OR LOWER(REPLACE(REPLACE(REPLACE(a.marque, '-', ' '), '_', ' '), '/', ' ')) LIKE ?
        OR LOWER(REPLACE(REPLACE(REPLACE(a.ville, '-', ' '), '_', ' '), '/', ' ')) LIKE ?
        OR LOWER(REPLACE(REPLACE(REPLACE(a.categorie, '-', ' '), '_', ' '), '/', ' ')) LIKE ?
      )";

      $like = "%" . $word . "%";
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
    }

    $where[] = "(" . implode(" OR ", $searchOrParts) . ")";

    $exactLike = $normalizedSearch;
    $startLike = $normalizedSearch . "%";
    $containsLike = "%" . $normalizedSearch . "%";

    $scoreParts[] = "CASE
      WHEN LOWER(REPLACE(REPLACE(REPLACE(a.titre, '-', ' '), '_', ' '), '/', ' ')) = ? THEN 100
      WHEN LOWER(REPLACE(REPLACE(REPLACE(a.titre, '-', ' '), '_', ' '), '/', ' ')) LIKE ? THEN 60
      WHEN LOWER(REPLACE(REPLACE(REPLACE(a.titre, '-', ' '), '_', ' '), '/', ' ')) LIKE ? THEN 35
      ELSE 0
    END";
    $scoreParams[] = $exactLike;
    $scoreParams[] = $startLike;
    $scoreParams[] = $containsLike;

    foreach ($words as $word) {
      $wLike = "%" . $word . "%";

      $scoreParts[] = "CASE WHEN LOWER(a.titre) LIKE ? THEN 12 ELSE 0 END";
      $scoreParams[] = $wLike;

      $scoreParts[] = "CASE WHEN LOWER(a.marque) LIKE ? THEN 10 ELSE 0 END";
      $scoreParams[] = $wLike;

      $scoreParts[] = "CASE WHEN LOWER(a.categorie) LIKE ? THEN 8 ELSE 0 END";
      $scoreParams[] = $wLike;

      $scoreParts[] = "CASE WHEN LOWER(a.ville) LIKE ? THEN 7 ELSE 0 END";
      $scoreParams[] = $wLike;

      $scoreParts[] = "CASE WHEN LOWER(a.description) LIKE ? THEN 4 ELSE 0 END";
      $scoreParams[] = $wLike;
    }
  }
}

if ($type !== "") {
  $where[] = "a.type = ?";
  $params[] = $type;
}

if ($mode !== "") {
  $where[] = "a.mode_vente = ?";
  $params[] = $mode;
}

if ($ville !== "") {
  $where[] = "a.ville = ?";
  $params[] = $ville;
}

if ($categorie !== "") {
  $where[] = "a.categorie = ?";
  $params[] = $categorie;
}

if ($marque !== "") {
  $where[] = "a.marque = ?";
  $params[] = $marque;
}

if ($minPrix !== "" && is_numeric($minPrix)) {
  $where[] = "a.prix >= ?";
  $params[] = (float)$minPrix;
}

if ($maxPrix !== "" && is_numeric($maxPrix)) {
  $where[] = "a.prix <= ?";
  $params[] = (float)$maxPrix;
}

$favoriJoin = "";
if (isLoggedIn()) {
  $favoriJoin = ",
    EXISTS(
      SELECT 1
      FROM favoris f
      WHERE f.id_annonce = a.id_annonce
        AND f.id_user = " . (int)currentUserId() . "
    ) AS is_favori
  ";
}

$searchScoreSql = "0";
if (!empty($scoreParts)) {
  $searchScoreSql = "(" . implode(" + ", $scoreParts) . ")";
}

$orderBy = "search_score DESC, a.date_publication DESC, a.id_annonce DESC";
if ($search === "") {
  $orderBy = "a.date_publication DESC, a.id_annonce DESC";
}

if ($tri === "ancien") {
  $orderBy = "a.date_publication ASC, a.id_annonce ASC";
} elseif ($tri === "prix_asc") {
  $orderBy = "a.prix ASC, a.id_annonce DESC";
} elseif ($tri === "prix_desc") {
  $orderBy = "a.prix DESC, a.id_annonce DESC";
} elseif ($tri === "vues_desc") {
  $orderBy = "COALESCE(a.nb_vues, 0) DESC, a.id_annonce DESC";
}

$countSql = "
  SELECT COUNT(*)
  FROM annonce a
  JOIN user u ON u.id_user = a.id_vendeur
  WHERE " . implode(" AND ", $where);

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalAnnonces = (int)$countStmt->fetchColumn();

$totalPages = max((int)ceil($totalAnnonces / $limit), 1);

$sql = "
  SELECT
    a.*,
    u.nom AS vendeur_nom,
    $searchScoreSql AS search_score
    $favoriJoin
  FROM annonce a
  JOIN user u ON u.id_user = a.id_vendeur
  WHERE " . implode(" AND ", $where) . "
  ORDER BY $orderBy
  LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($scoreParams, $params));
$annonces = $stmt->fetchAll();


$recommendedOrderBy = "COALESCE(a.nb_vues, 0) DESC, a.date_publication DESC, a.id_annonce DESC";

if ($tri === "ancien") {
  $recommendedOrderBy = "a.date_publication ASC, a.id_annonce ASC";
} elseif ($tri === "prix_asc") {
  $recommendedOrderBy = "a.prix ASC, a.id_annonce DESC";
} elseif ($tri === "prix_desc") {
  $recommendedOrderBy = "a.prix DESC, a.id_annonce DESC";
} elseif ($tri === "vues_desc") {
  $recommendedOrderBy = "COALESCE(a.nb_vues, 0) DESC, a.id_annonce DESC";
} elseif ($search !== "") {
  $recommendedOrderBy = "a.date_publication DESC, a.id_annonce DESC";
} else {
  $recommendedOrderBy = "a.date_publication DESC, a.id_annonce DESC";
}

/* =========================
   RECOMMANDÉ POUR TOI
   ========================= */
$recommendedItems = [];

if (isLoggedIn() && !$isFiltering) {
  try {
    $uid = (int)currentUserId();

    $recWhere = [
      "a.statut = 'ACTIVE'",
      "a.id_vendeur <> ?",
      "a.id_annonce NOT IN (
        SELECT id_annonce
        FROM favoris
        WHERE id_user = ?
      )"
    ];
    $recParamsBase = [$uid, $uid];

    if ($mode !== "") {
      $recWhere[] = "a.mode_vente = ?";
      $recParamsBase[] = $mode;
    }

    if ($ville !== "") {
      $recWhere[] = "a.ville = ?";
      $recParamsBase[] = $ville;
    }

    if ($categorie !== "") {
      $recWhere[] = "a.categorie = ?";
      $recParamsBase[] = $categorie;
    }

    if ($marque !== "") {
      $recWhere[] = "a.marque = ?";
      $recParamsBase[] = $marque;
    }

    if ($minPrix !== "" && is_numeric($minPrix)) {
      $recWhere[] = "a.prix >= ?";
      $recParamsBase[] = (float)$minPrix;
    }

    if ($maxPrix !== "" && is_numeric($maxPrix)) {
      $recWhere[] = "a.prix <= ?";
      $recParamsBase[] = (float)$maxPrix;
    }

    $stmtRecPrefs = $pdo->prepare("
      SELECT
        a.categorie,
        a.marque,
        COUNT(*) AS score_pref
      FROM favoris f
      JOIN annonce a ON a.id_annonce = f.id_annonce
      WHERE f.id_user = ?
        AND a.categorie IS NOT NULL
        AND a.categorie <> ''
      GROUP BY a.categorie, a.marque
      ORDER BY score_pref DESC
      LIMIT 10
    ");
    $stmtRecPrefs->execute([$uid]);
    $prefs = $stmtRecPrefs->fetchAll();

    if (count($prefs) > 0) {
      $orParts = [];
      $paramsRecPrefs = [];

      foreach ($prefs as $p) {
        $cat = trim((string)($p["categorie"] ?? ""));
        $mar = trim((string)($p["marque"] ?? ""));

        if ($cat !== "" && $mar !== "") {
          $orParts[] = "(a.categorie = ? AND a.marque = ?)";
          $paramsRecPrefs[] = $cat;
          $paramsRecPrefs[] = $mar;
        } elseif ($cat !== "") {
          $orParts[] = "(a.categorie = ?)";
          $paramsRecPrefs[] = $cat;
        }
      }

      if (count($orParts) > 0) {
        $recWhereWithPrefs = $recWhere;
        $recWhereWithPrefs[] = "(" . implode(" OR ", $orParts) . ")";

        $sqlRec = "
          SELECT DISTINCT
            a.*,
            u.nom AS vendeur_nom,
            EXISTS(
              SELECT 1
              FROM favoris f2
              WHERE f2.id_annonce = a.id_annonce
                AND f2.id_user = ?
            ) AS is_favori
          FROM annonce a
          JOIN user u ON u.id_user = a.id_vendeur
          WHERE " . implode(" AND ", $recWhereWithPrefs) . "
          ORDER BY $recommendedOrderBy
          LIMIT 8
        ";

        $stmtRec = $pdo->prepare($sqlRec);
        $stmtRec->execute(array_merge([$uid], $recParamsBase, $paramsRecPrefs));
        $recommendedItems = $stmtRec->fetchAll();
      }
    }

    if (count($recommendedItems) === 0) {
      $sqlRecFallback = "
        SELECT
          a.*,
          u.nom AS vendeur_nom,
          EXISTS(
            SELECT 1
            FROM favoris f2
            WHERE f2.id_annonce = a.id_annonce
              AND f2.id_user = ?
          ) AS is_favori
        FROM annonce a
        JOIN user u ON u.id_user = a.id_vendeur
        WHERE " . implode(" AND ", $recWhere) . "
        ORDER BY $recommendedOrderBy
        LIMIT 8
      ";

      $stmtRecFallback = $pdo->prepare($sqlRecFallback);
      $stmtRecFallback->execute(array_merge([$uid], $recParamsBase));
      $recommendedItems = $stmtRecFallback->fetchAll();
    }
  } catch (Exception $e) {
    $recommendedItems = [];
  }
}

$hasAdvancedFilters =
  $ville !== "" ||
  $categorie !== "" ||
  $marque !== "" ||
  $minPrix !== "" ||
  $maxPrix !== "" ||
  $tri !== "recent";
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<style>
.favori-card-btn.active {
  background: #dc3545 !important;
  color: #fff !important;
  border-color: #dc3545 !important;
}
.filter-card {
  border-radius: 18px;
}
.filter-section-title {
  font-size: 0.95rem;
  font-weight: 700;
  color: #333;
}
.filter-actions .btn {
  min-height: 42px;
}
.filters-toggle-btn {
  border-radius: 999px;
}
.filters-arrow {
  transition: transform .2s ease;
}
.filters-arrow.rotate {
  transform: rotate(180deg);
}
.quick-search-form {
  max-width: 420px;
  width: 100%;
}
.annonce-card-link {
  text-decoration: none;
  color: inherit;
  display: block;
}
.annonce-card-link:hover {
  color: inherit;
}
.annonce-card {
  transition: transform .15s ease, box-shadow .15s ease;
  cursor: pointer;
}
.annonce-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 .5rem 1rem rgba(0,0,0,.12) !important;
}
.card-actions {
  position: relative;
  z-index: 3;
}
.search-history-wrap {
  margin-top: 12px;
}
.search-history-badge {
  text-decoration: none;
  border: 1px solid rgba(255,255,255,.35);
  color: #fff;
  background: rgba(255,255,255,.10);
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 13px;
  transition: .15s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.search-history-badge:hover {
  background: rgba(255,255,255,.18);
  color: #fff;
}
.old-price {
  font-size: .85rem;
  color: #6c757d;
  text-decoration: line-through;
}
.new-price {
  color: #dc3545;
  font-weight: 700;
}
.pagination .page-link {
  border-radius: 10px;
  margin: 0 4px;
  color: #111827;
  border: 1px solid #dee2e6;
}

.pagination .page-item.active .page-link {
  background: #111827;
  border-color: #111827;
  color: #fff;
}

.pagination .page-link:hover {
  background: #f3f4f6;
  color: #111827;
}

.pagination .page-item.disabled .page-link {
  opacity: .6;
}
.annonce-card{
  position: relative;
  overflow: hidden;
}

.badge-new{
  position: absolute;
  top: 10px;
  left: 10px;
  background: #198754;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  padding: 6px 10px;
  border-radius: 999px;
  z-index: 5;
  box-shadow: 0 4px 12px rgba(0,0,0,.15);
}

.badge-promo-card{
  position: absolute;
  top: 10px;
  right: 10px;
  background: #dc3545;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  padding: 6px 10px;
  border-radius: 999px;
  z-index: 5;
  box-shadow: 0 4px 12px rgba(0,0,0,.15);
}
</style>

<div class="container my-4">
  <div class="p-4 hero">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
      <div>
        <h1 class="fw-bold mb-1">Fluxo</h1>
        <p class="mb-0 opacity-75">Marketplace entre particuliers — achat direct ou discussion.</p>
      </div>

      <form class="d-flex gap-2 quick-search-form" method="GET">
        <input
          class="form-control"
          name="search"
          placeholder="Rechercher une annonce..."
          value="<?php echo e($search); ?>"
        >
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-search"></i>
        </button>

        <?php if ($ville !== ""): ?><input type="hidden" name="ville" value="<?php echo e($ville); ?>"><?php endif; ?>
        <?php if ($categorie !== ""): ?><input type="hidden" name="categorie" value="<?php echo e($categorie); ?>"><?php endif; ?>
        <?php if ($marque !== ""): ?><input type="hidden" name="marque" value="<?php echo e($marque); ?>"><?php endif; ?>
        <?php if ($minPrix !== ""): ?><input type="hidden" name="min_prix" value="<?php echo e($minPrix); ?>"><?php endif; ?>
        <?php if ($maxPrix !== ""): ?><input type="hidden" name="max_prix" value="<?php echo e($maxPrix); ?>"><?php endif; ?>
        <?php if ($tri !== ""): ?><input type="hidden" name="tri" value="<?php echo e($tri); ?>"><?php endif; ?>
      </form>
    </div>

    <?php if (isLoggedIn() && count($lastSearches) > 0): ?>
      <div class="search-history-wrap">
        <div class="small opacity-75 mb-2">Dernières recherches :</div>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($lastSearches as $r): ?>
            <a class="search-history-badge" href="index.php?search=<?php echo urlencode($r["recherche"]); ?>">
              <i class="bi bi-clock-history"></i>
              <?php echo e($r["recherche"]); ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
      <button
        class="btn btn-sm btn-outline-light filters-toggle-btn"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#advancedFilters"
        aria-expanded="<?php echo $hasAdvancedFilters ? 'true' : 'false'; ?>"
        aria-controls="advancedFilters"
      >
        <i class="bi bi-funnel"></i> Filtres avancés
        <i class="bi bi-chevron-down ms-1 filters-arrow <?php echo $hasAdvancedFilters ? 'rotate' : ''; ?>" id="filtersArrow"></i>
      </button>
    </div>
  </div>

  <div class="collapse mt-3 <?php echo $hasAdvancedFilters ? 'show' : ''; ?>" id="advancedFilters">
    <div class="card shadow-sm border-0 filter-card">
      <div class="card-body p-4">
        <div class="filter-section-title mb-3">
          <i class="bi bi-funnel"></i> Recherche avancée
        </div>

        <form method="GET" class="row g-3">
          <div class="col-12 col-lg-4">
            <label class="form-label">Recherche</label>
            <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Titre, description, marque, ville, catégorie">
          </div>

          <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label">Ville</label>
            <select class="form-select" name="ville">
              <?php foreach ($MAROC_CITIES as $c): ?>
                <option value="<?php echo e($c); ?>" <?php echo $ville === $c ? "selected" : ""; ?>>
                  <?php echo $c === "" ? "Toutes" : e($c); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label">Catégorie</label>
            <select class="form-select" name="categorie" id="categorie">
              <?php foreach ($CATEGORIES as $cat => $brands): ?>
                <option value="<?php echo e($cat); ?>" <?php echo $categorie === $cat ? "selected" : ""; ?>>
                  <?php echo $cat === "" ? "Toutes" : e($cat); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label">Marque</label>
            <select class="form-select" name="marque" id="marque">
              <option value="">Toutes</option>
            </select>
          </div>

          <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label">Tri</label>
            <select class="form-select" name="tri">
              <option value="recent" <?php echo $tri === "recent" ? "selected" : ""; ?>>Plus récentes</option>
              <option value="ancien" <?php echo $tri === "ancien" ? "selected" : ""; ?>>Plus anciennes</option>
              <option value="prix_asc" <?php echo $tri === "prix_asc" ? "selected" : ""; ?>>Prix croissant</option>
              <option value="prix_desc" <?php echo $tri === "prix_desc" ? "selected" : ""; ?>>Prix décroissant</option>
              <option value="vues_desc" <?php echo $tri === "vues_desc" ? "selected" : ""; ?>>Plus vues</option>
            </select>
          </div>

          <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label">Prix min</label>
            <input type="number" step="0.01" min="0" class="form-control" name="min_prix" value="<?php echo e($minPrix); ?>">
          </div>

          <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label">Prix max</label>
            <input type="number" step="0.01" min="0" class="form-control" name="max_prix" value="<?php echo e($maxPrix); ?>">
          </div>

          <div class="col-12 col-md-6 col-lg-8 d-flex align-items-end">
            <div class="d-flex gap-2 w-100 filter-actions flex-wrap">
              <button class="btn btn-dark" type="submit">
                <i class="bi bi-search"></i> Filtrer
              </button>
              <a class="btn btn-outline-secondary" href="index.php">
                <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
              </a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php if (count($recentlyViewed) > 0 && !$isFiltering): ?>
    <div class="mt-4 mb-3">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <h4 class="fw-bold mb-0">Récemment vues</h4>
          <div class="text-muted small">Les annonces que tu as consultées récemment.</div>
        </div>
      </div>

      <div class="row g-3">
        <?php foreach (array_slice($recentlyViewed, 0, 5) as $rv): ?>
          <?php $modeV = (string)($rv["mode_vente"] ?? ""); ?>
          <div class="col-12 col-sm-6 col-lg-4">
            <a class="annonce-card-link" href="annonce.php?id=<?php echo (int)$rv["id_annonce"]; ?>">
              <div class="card annonce-card h-100 shadow-sm border-0">
                <img src="<?php echo e(imgUrl($rv)); ?>" class="card-img-top"  alt="">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start gap-2">
                    <h6 class="card-title mb-1"><?php echo e($rv["titre"]); ?></h6>
                    <div class="text-end">
                      <?php if (hasPromo($rv)): ?>
                        <div class="old-price"><?php echo number_format((float)$rv["ancien_prix"], 2); ?> DH</div>
                        <div class="new-price"><?php echo number_format((float)$rv["prix"], 2); ?> DH</div>
                      <?php else: ?>
                        <span class="badge text-bg-dark"><?php echo number_format((float)$rv["prix"], 2); ?> DH</span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="text-muted small mb-2">
                    <?php if (!empty($rv["ville"])): ?>
                      <i class="bi bi-geo-alt"></i> <?php echo e($rv["ville"]); ?>
                    <?php endif; ?>
                  </div>

                  <div class="small text-muted">
                    Mode: <b><?php echo e(modeLabel($modeV)); ?></b>
                  </div>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (isLoggedIn() && count($recommendedItems) > 0  && !$isFiltering): ?>
    <div class="mt-4 mb-3">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <h4 class="fw-bold mb-0">Recommandé pour toi</h4>
          <div class="text-muted small">Basé sur tes favoris et tes préférences.</div>
        </div>
      </div>

      <div class="row g-3">
        
          <?php foreach ($recommendedItems as $a): ?>
  <?php
    $modeV = (string)($a["mode_vente"] ?? "");
    $canBuy = in_array($modeV, ["PAIEMENT_DIRECT", "LES_DEUX"], true);
    $canChat = in_array($modeV, ["POSSIBILITE_CONTACTE", "LES_DEUX"], true);
    $isFavori = !empty($a["is_favori"]);
    $isNew = !empty($a["date_publication"]) && (time() - strtotime($a["date_publication"]) < 86400);
    
  ?>
          <div class="col-12 col-sm-6 col-lg-4">
            <a class="annonce-card-link" href="annonce.php?id=<?php echo (int)$a["id_annonce"]; ?>">
              <div class="card annonce-card card-hover h-100 shadow-sm border-0">
  <?php if ($isNew): ?>
    <span class="badge-new">Nouveau</span>
  <?php endif; ?>

  <?php if (hasPromo($a)): ?>
    <span class="badge-promo-card"> PROMO </span>
  <?php endif; ?>
  <img src="<?php echo e(imgUrl($a)); ?>" class="card-img-top" alt="">


                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start gap-2">
                    <h6 class="card-title mb-1"><?php echo e($a["titre"]); ?></h6>
                    <div class="text-end">
                      <?php if (hasPromo($a)): ?>
                        <div class="old-price"><?php echo number_format((float)$a["ancien_prix"], 2); ?> DH</div>
                        <div class="new-price"><?php echo number_format((float)$a["prix"], 2); ?> DH</div>
                      <?php else: ?>
                        <span class="badge text-bg-dark"><?php echo number_format((float)$a["prix"], 2); ?> DH</span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="text-muted small mb-2">
                    <i class="bi bi-person"></i> <?php echo e($a["vendeur_nom"]); ?>
                    <?php if (!empty($a["ville"])): ?>
                      · <i class="bi bi-geo-alt"></i> <?php echo e($a["ville"]); ?>
                    <?php endif; ?>
                  </div>

                  <div class="annonce-actions card-actions">
                    <a class="btn btn-outline-secondary btn-sm" href="annonce.php?id=<?php echo (int)$a["id_annonce"]; ?>">Voir</a>

                    <?php if ($canBuy): ?>
                      <a class="btn btn-primary btn-sm" href="annonce.php?id=<?php echo (int)$a["id_annonce"]; ?>">Acheter</a>
                    <?php endif; ?>

                    <?php if ($canChat): ?>
                      <a class="btn btn-outline-primary btn-sm" href="messages.php?annonce=<?php echo (int)$a["id_annonce"]; ?>&to=<?php echo (int)$a["id_vendeur"]; ?>">Message</a>
                    <?php endif; ?>

                    <button
                      type="button"
                      class="btn btn-outline-danger btn-sm favori-card-btn <?php echo $isFavori ? 'active' : ''; ?>"
                      data-id="<?php echo (int)$a["id_annonce"]; ?>"
                    >
                      <i class="bi <?php echo $isFavori ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                    </button>
                  </div>

                  <div class="mt-2 small text-muted">
                    Mode: <b><?php echo e(modeLabel($modeV)); ?></b>
                  </div>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 mb-2">
    <div class="text-muted">
      <b><?php echo $totalAnnonces; ?></b> annonce(s) trouvée(s)
    </div>
    <div class="small text-muted">
      Tri actuel :
      <b>
        <?php
          echo match ($tri) {
            "ancien" => "Plus anciennes",
            "prix_asc" => "Prix croissant",
            "prix_desc" => "Prix décroissant",
            "vues_desc" => "Plus vues",
            default => ($search !== "" ? "Pertinence" : "Plus récentes"),
          };
        ?>
      </b>
    </div>
  </div>

  <div class="row g-3 mt-1">
   <?php foreach ($annonces as $a): ?>
  <?php
    $modeV = (string)($a["mode_vente"] ?? "");
    $canBuy = in_array($modeV, ["PAIEMENT_DIRECT", "LES_DEUX"], true);
    $canChat = in_array($modeV, ["POSSIBILITE_CONTACTE", "LES_DEUX"], true);
    $isFavori = !empty($a["is_favori"]);
    $isNew = !empty($a["date_publication"]) && (time() - strtotime($a["date_publication"]) < 86400);
    
  ?>
      <div class="col-12 col-sm-6 col-lg-4">
        <a class="annonce-card-link" href="annonce.php?id=<?php echo (int)$a["id_annonce"]; ?>">
          <div class="card annonce-card card-hover h-100 shadow-sm border-0">
  <?php if ($isNew): ?>
    <span class="badge-new">Nouveau</span>
  <?php endif; ?>

  <?php if (hasPromo($a)): ?>
    <span class="badge-promo-card">PROMO </span>
  <?php endif; ?>

  <img src="<?php echo e(imgUrl($a)); ?>" class="card-img-top" alt="">

            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <h5 class="card-title mb-1"><?php echo e($a["titre"]); ?></h5>
                <div class="text-end">
                  <?php if (hasPromo($a)): ?>
                    <div class="old-price"><?php echo number_format((float)$a["ancien_prix"], 2); ?> DH</div>
                    <div class="new-price"><?php echo number_format((float)$a["prix"], 2); ?> DH</div>
                  <?php else: ?>
                    <span class="badge text-bg-dark"><?php echo number_format((float)$a["prix"], 2); ?> DH</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="text-muted small mb-2">
                <i class="bi bi-person"></i> <?php echo e($a["vendeur_nom"]); ?>
                <?php if (!empty($a["ville"])): ?>
                  · <i class="bi bi-geo-alt"></i> <?php echo e($a["ville"]); ?>
                <?php endif; ?>
                · <i class="bi bi-box"></i> Stock: <?php echo (int)$a["stock"]; ?>
              </div>

              <div class="annonce-actions card-actions">
                <a class="btn btn-outline-secondary btn-sm" href="annonce.php?id=<?php echo (int)$a["id_annonce"]; ?>">Voir</a>

                <?php if ($canBuy): ?>
                  <a class="btn btn-primary btn-sm" href="annonce.php?id=<?php echo (int)$a["id_annonce"]; ?>">Acheter</a>
                <?php endif; ?>

                <?php if ($canChat): ?>
                  <a class="btn btn-outline-primary btn-sm" href="messages.php?annonce=<?php echo (int)$a["id_annonce"]; ?>&to=<?php echo (int)$a["id_vendeur"]; ?>">Message</a>
                <?php endif; ?>

                <?php if (isLoggedIn()): ?>
                  <button
                    type="button"
                    class="btn btn-outline-danger btn-sm favori-card-btn <?php echo $isFavori ? 'active' : ''; ?>"
                    data-id="<?php echo (int)$a["id_annonce"]; ?>"
                  >
                    <i class="bi <?php echo $isFavori ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                  </button>
                <?php endif; ?>
              </div>

              <div class="mt-2 small text-muted">
                Mode: <b><?php echo e(modeLabel($modeV)); ?></b>
              </div>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>

    <?php if (count($annonces) === 0): ?>
      <div class="col-12">
      <div class="text-center py-5">
  <i class="bi bi-search" style="font-size:40px;color:#999"></i>
  <h5 class="mt-3">Aucune annonce trouvée</h5>
  <p class="text-muted">Essayez d'élargir votre recherche.</p>
</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-4">
  <ul class="pagination justify-content-center flex-wrap">

    <!-- précédent -->
    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
      <a class="page-link"
         href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">
        Précédent
      </a>
    </li>

    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
        <a class="page-link"
           href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$p])); ?>">
          <?php echo $p; ?>
        </a>
      </li>
    <?php endfor; ?>

    <!-- suivant -->
    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
      <a class="page-link"
         href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page+1])); ?>">
        Suivant
      </a>
    </li>

  </ul>
</nav>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const brandsByCategory = <?php echo json_encode($CATEGORIES, JSON_UNESCAPED_UNICODE); ?>;
  const categorieEl = document.getElementById("categorie");
  const marqueEl = document.getElementById("marque");
  const selectedBrand = <?php echo json_encode($marque, JSON_UNESCAPED_UNICODE); ?>;
  const advancedFilters = document.getElementById("advancedFilters");
  const filtersArrow = document.getElementById("filtersArrow");

  function loadBrands(selected = "") {
    const cat = categorieEl.value;
    const brands = brandsByCategory[cat] || [];

    marqueEl.innerHTML = "";

    const firstOption = document.createElement("option");
    firstOption.value = "";
    firstOption.textContent = "Toutes";
    marqueEl.appendChild(firstOption);

    brands.forEach(function (brand) {
      const opt = document.createElement("option");
      opt.value = brand;
      opt.textContent = brand;
      if (selected && selected === brand) opt.selected = true;
      marqueEl.appendChild(opt);
    });
  }

  loadBrands(selectedBrand);

  categorieEl.addEventListener("change", function () {
    loadBrands("");
  });

  if (advancedFilters && filtersArrow) {
    advancedFilters.addEventListener("show.bs.collapse", function () {
      filtersArrow.classList.add("rotate");
    });

    advancedFilters.addEventListener("hide.bs.collapse", function () {
      filtersArrow.classList.remove("rotate");
    });
  }

  document.querySelectorAll(".card-actions a, .card-actions button").forEach(function (el) {
    el.addEventListener("click", function (e) {
      e.stopPropagation();
    });
  });

  document.querySelectorAll(".favori-card-btn").forEach(function (btn) {
    btn.addEventListener("click", async function (e) {
      e.preventDefault();
      e.stopPropagation();

      const annonceId = this.dataset.id;
      const icon = this.querySelector("i");

      this.disabled = true;

      try {
        const formData = new FormData();
        formData.append("id_annonce", annonceId);

        const res = await fetch("actions/favori_action.php", {
          method: "POST",
          body: formData
        });

        const data = await res.json();

        if (!data.ok) {
          showToast(data.message || "Erreur.");
          return;
        }

        if (data.favori) {
          this.classList.add("active");
          icon.className = "bi bi-heart-fill";
        } else {
          this.classList.remove("active");
          icon.className = "bi bi-heart";
        }

      } catch (e) {
        showToast("Erreur serveur.");
      } finally {
        this.disabled = false;
      }
    });
  });
});

</script>

<?php include "includes/footer.php"; ?>