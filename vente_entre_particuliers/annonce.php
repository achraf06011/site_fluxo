<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";

if (session_status() === PHP_SESSION_NONE) session_start();

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) die("Annonce invalide.");

$isAdminViewer = isLoggedIn() && currentUserRole() === "ADMIN";
$from = trim((string)($_GET["from"] ?? ""));
$referer = (string)($_SERVER["HTTP_REFERER"] ?? "");

$isFromAdmin = $isAdminViewer && (
  $from === "admin_annonces" ||
  str_contains($referer, "/admin/annonces.php") ||
  str_contains($referer, "\\admin\\annonces.php") ||
  str_contains($referer, "/admin/index.php") ||
  str_contains($referer, "\\admin\\index.php")
);

$backUrl = $isFromAdmin ? "admin/annonces.php" : "index.php";

// Récup annonce + vendeur
$stmt = $pdo->prepare("
  SELECT a.*, u.nom AS vendeur_nom, u.email AS vendeur_email
  FROM annonce a
  JOIN user u ON u.id_user = a.id_vendeur
  WHERE a.id_annonce = ?
  LIMIT 1
");
$stmt->execute([$id]);
$a = $stmt->fetch();
if (!$a) die("Annonce introuvable.");

$currentUserId = isLoggedIn() ? (int)currentUserId() : 0;
$isOwner = isLoggedIn() && $currentUserId === (int)$a["id_vendeur"];

// Compteur de vues : 1 vue max / 30 min / session / annonce
$canCountView = true;

if ($isFromAdmin) $canCountView = false;
if ($isOwner) $canCountView = false;

if (!isset($_SESSION["annonce_views"]) || !is_array($_SESSION["annonce_views"])) {
  $_SESSION["annonce_views"] = [];
}

$lastViewAt = (int)($_SESSION["annonce_views"][$id] ?? 0);
$now = time();
$delaySeconds = 30 * 60;

if ($canCountView && ($now - $lastViewAt >= $delaySeconds)) {
  try {
    $stmt = $pdo->prepare("
      UPDATE annonce
      SET nb_vues = COALESCE(nb_vues, 0) + 1
      WHERE id_annonce = ?
      LIMIT 1
    ");
    $stmt->execute([$id]);

    $_SESSION["annonce_views"][$id] = $now;
    $a["nb_vues"] = (int)($a["nb_vues"] ?? 0) + 1;
  } catch (Exception $e) {
  }
}

function e($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

function coverUrl($a) {
  $file = $a["cover_image"] ?? null;
  if (!$file) return "https://picsum.photos/seed/" . (int)$a["id_annonce"] . "/1200/800";
  if (str_starts_with($file, "http")) return $file;
  return "uploads/" . $file;
}

function similarImgUrl($x) {
  $file = $x["cover_image"] ?? null;
  if (!$file) return "https://picsum.photos/seed/" . (int)$x["id_annonce"] . "/800/600";
  if (str_starts_with($file, "http")) return $file;
  return "uploads/" . $file;
}

function modeLabelText(string $mode): string {
  return match ($mode) {
    "PAIEMENT_DIRECT" => "PAIEMENT DIRECT",
    "POSSIBILITE_CONTACTE" => "CONTACTER LE VENDEUR",
    "LES_DEUX" => "PAIEMENT DIRECT OU CONTACTER VENDEUR",
    default => $mode,
  };
}

function hasPromo(array $item): bool {
  return isset($item["ancien_prix"]) && $item["ancien_prix"] !== null && (float)$item["ancien_prix"] > (float)$item["prix"];
}

/* =========================
   RECEMMENT VUES (SESSION)
   ========================= */
if (!$isFromAdmin) {
  if (!isset($_SESSION["recently_viewed_annonces"]) || !is_array($_SESSION["recently_viewed_annonces"])) {
    $_SESSION["recently_viewed_annonces"] = [];
  }

  $recent = $_SESSION["recently_viewed_annonces"];

  $recent = array_values(array_filter($recent, function ($item) use ($id) {
    return (int)($item["id_annonce"] ?? 0) !== $id;
  }));

  array_unshift($recent, [
    "id_annonce" => (int)$a["id_annonce"],
    "titre" => (string)($a["titre"] ?? ""),
    "prix" => (float)($a["prix"] ?? 0),
    "ancien_prix" => isset($a["ancien_prix"]) ? $a["ancien_prix"] : null,
    "cover_image" => (string)($a["cover_image"] ?? ""),
    "ville" => (string)($a["ville"] ?? ""),
    "mode_vente" => (string)($a["mode_vente"] ?? ""),
    "viewed_at" => date("Y-m-d H:i:s"),
  ]);

  $_SESSION["recently_viewed_annonces"] = array_slice($recent, 0, 12);
}

// Images secondaires
$stmt = $pdo->prepare("SELECT url FROM annonce_image WHERE id_annonce = ? ORDER BY id_image DESC");
$stmt->execute([$id]);
$imgs = $stmt->fetchAll();

// Galerie complète
$galleryImages = [coverUrl($a)];
foreach ($imgs as $im) {
  $galleryImages[] = ltrim((string)$im["url"], "/");
}

// Etat / logique
$modeV = $a["mode_vente"] ?? "";
$canBuy = in_array($modeV, ["PAIEMENT_DIRECT", "LES_DEUX"], true);
$canChat = in_array($modeV, ["POSSIBILITE_CONTACTE", "LES_DEUX"], true);

$stock = (int)($a["stock"] ?? 0);
$stockOk = $stock > 0;
$nbVues = (int)($a["nb_vues"] ?? 0);

$statut = $a["statut"] ?? "";
$isActive = ($statut === "ACTIVE");
$isSold = ($statut === "VENDUE");
$isOut = ($stock <= 0 && !$isSold);

// Label mode vente
$modeLabel = $modeV;
if ($modeV === "PAIEMENT_DIRECT") $modeLabel = "PAIEMENT DIRECT";
if ($modeV === "POSSIBILITE_CONTACTE") $modeLabel = "CONTACTER LE VENDEUR";
if ($modeV === "LES_DEUX") $modeLabel = "PAIEMENT DIRECT OU CONTACTER VENDEUR";

// Livraison
$livraisonActive = (int)($a["livraison_active"] ?? 0) === 1;
$prixSame = (float)($a["livraison_prix_same_city"] ?? 15);
$prixOther = (float)($a["livraison_prix_other_city"] ?? 40);

// Coordonnées
$latitude = isset($a["latitude"]) && $a["latitude"] !== null ? (float)$a["latitude"] : null;
$longitude = isset($a["longitude"]) && $a["longitude"] !== null ? (float)$a["longitude"] : null;
$hasMap = ($latitude !== null && $longitude !== null);

// Rating vendeur
$vendeurId = (int)($a["id_vendeur"] ?? 0);
$avg = 0.0;
$cnt = 0;

if ($vendeurId > 0) {
  $stmt = $pdo->prepare("
    SELECT AVG(r.note) AS avg_note, COUNT(r.id_review) AS total_reviews
    FROM review r
    JOIN annonce a2 ON a2.id_annonce = r.id_annonce
    WHERE a2.id_vendeur = ?
  ");
  $stmt->execute([$vendeurId]);
  $rowRating = $stmt->fetch();
  if ($rowRating) {
    $avg = round((float)($rowRating["avg_note"] ?? 0), 1);
    $cnt = (int)($rowRating["total_reviews"] ?? 0);
  }
}

function starsHtml(float $rating): string {
  $html = "";
  for ($i = 1; $i <= 5; $i++) {
    if ($rating >= $i) {
      $html .= '<i class="bi bi-star-fill text-warning"></i>';
    } elseif ($rating >= ($i - 0.5)) {
      $html .= '<i class="bi bi-star-half text-warning"></i>';
    } else {
      $html .= '<i class="bi bi-star text-warning"></i>';
    }
  }
  return $html;
}

// Favori
$isFavori = false;
if (isLoggedIn()) {
  try {
    $stmt = $pdo->prepare("
      SELECT id_favori
      FROM favoris
      WHERE id_user = ? AND id_annonce = ?
      LIMIT 1
    ");
    $stmt->execute([$currentUserId, $id]);
    $isFavori = (bool)$stmt->fetch();
  } catch (Exception $e) {
    $isFavori = false;
  }
}

// Affichage vues seulement vendeur/admin
$canSeeViews = $isOwner || $isFromAdmin || $isAdminViewer;

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

/* Annonces similaires */
$similarItems = [];
try {
  $stmt = $pdo->prepare("
    SELECT a.*, u.nom AS vendeur_nom
    FROM annonce a
    JOIN user u ON u.id_user = a.id_vendeur
    WHERE a.id_annonce <> ?
      AND a.statut = 'ACTIVE'
      AND a.categorie = ?
    ORDER BY
      CASE WHEN a.marque = ? THEN 0 ELSE 1 END,
      CASE WHEN a.ville = ? THEN 0 ELSE 1 END,
      COALESCE(a.nb_vues, 0) DESC,
      a.date_publication DESC,
      a.id_annonce DESC
    LIMIT 4
  ");
  $stmt->execute([
    (int)$a["id_annonce"],
    (string)($a["categorie"] ?? ""),
    (string)($a["marque"] ?? ""),
    (string)($a["ville"] ?? "")
  ]);
  $similarItems = $stmt->fetchAll();
} catch (Exception $e) {
  $similarItems = [];
}

$shareUrl = ((isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http")
  . "://"
  . ($_SERVER["HTTP_HOST"] ?? "localhost")
  . rtrim(dirname($_SERVER["SCRIPT_NAME"] ?? "/"), "/\\")
  . "/annonce.php?id=" . (int)$a["id_annonce"];
?>

<?php include "includes/header.php"; ?>
<?php
if ($isFromAdmin) {
  include "includes/admin_navbar.php";
} else {
  include "includes/navbar.php";
}
?>

<style>
.ribbon-wrap{ position: relative; }
.sold-ribbon{
  position:absolute; top:18px; left:-48px;
  transform: rotate(-20deg);
  background:#dc3545; color:#fff;
  padding:10px 70px;
  font-weight:800; letter-spacing:1px;
  text-transform:uppercase;
  box-shadow:0 12px 28px rgba(0,0,0,.25);
  z-index:5;
}
.sold-ribbon.bg-secondary{ background:#6c757d; }

.favori-btn.active,
.favori-toggle-btn.active {
  background: #dc3545 !important;
  color: #fff !important;
  border-color: #dc3545 !important;
}

#annonce-mini-map{
  width: 100%;
  height: 240px;
  border: 0;
  display: block;
  border-radius: 14px;
}

.map-clickable-wrap{
  cursor: pointer;
  position: relative;
  overflow: hidden;
  border-radius: 14px;
  border: 1px solid #dee2e6;
}

.map-click-overlay{
  position: absolute;
  inset: 0;
  z-index: 10;
  background: transparent;
}

.map-nav-hint{
  position: absolute;
  right: 12px;
  bottom: 12px;
  z-index: 11;
  background: rgba(0,0,0,.68);
  color: #fff;
  font-size: 12px;
  padding: 7px 11px;
  border-radius: 999px;
  pointer-events: none;
}

.annonce-zoom-trigger{
  position: relative;
  overflow: hidden;
  cursor: zoom-in;
  background: #fff;
}

.annonce-zoom-trigger .zoom-image{
  transition: transform .18s ease;
  will-change: transform;
}

.annonce-zoom-trigger:hover .zoom-image{
  transform: scale(1.8);
}

.zoom-hint{
  position: absolute;
  bottom: 12px;
  right: 12px;
  background: rgba(0,0,0,.65);
  color: #fff;
  font-size: 12px;
  padding: 6px 10px;
  border-radius: 999px;
  z-index: 3;
  pointer-events: none;
}

.gallery-modal .modal-content{
  background: #000;
  border: 0;
}

.gallery-stage{
  position: relative;
  width: 100%;
  height: calc(100vh - 70px);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: #000;
}

.gallery-stage img{
  max-width: 96vw;
  max-height: 88vh;
  width: auto;
  height: auto;
  object-fit: contain;
  user-select: none;
  cursor: pointer;
}

.gallery-nav-btn{
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 20;
  width: 52px;
  height: 52px;
  border: 0;
  border-radius: 50%;
  background: rgba(255,255,255,.14);
  color: #fff;
  font-size: 26px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: .2s ease;
}

.gallery-nav-btn:hover{
  background: rgba(255,255,255,.25);
}

.gallery-prev{ left: 18px; }
.gallery-next{ right: 18px; }

.gallery-counter{
  position: absolute;
  bottom: 18px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 20;
  background: rgba(0,0,0,.55);
  color: #fff;
  padding: 8px 14px;
  border-radius: 999px;
  font-size: 14px;
}

.gallery-close{ z-index: 30; }

.similar-card-link{
  text-decoration: none;
  color: inherit;
  display: block;
}
.similar-card-link:hover{
  color: inherit;
}
.similar-card{
  transition: transform .15s ease, box-shadow .15s ease;
}
.similar-card:hover{
  transform: translateY(-2px);
  box-shadow: 0 .5rem 1rem rgba(0,0,0,.12)!important;
}

.old-price{
  font-size: .9rem;
  color: #6c757d;
  text-decoration: line-through;
}

.new-price{
  color: #dc3545;
  font-weight: 700;
}

.vendeur-link{
  text-decoration: none;
  color: inherit;
  font-weight: 600;
}
.vendeur-link:hover{
  color: #0d6efd;
}

@media (max-width: 768px){
  .gallery-nav-btn{
    width: 44px;
    height: 44px;
    font-size: 22px;
  }

  .gallery-stage img{
    max-width: 94vw;
    max-height: 80vh;
  }
}
</style>

<div class="container my-4">
  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo e($success); ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo e($error); ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-12 col-lg-7">
      <div class="ribbon-wrap">
        <?php if ($isSold): ?>
          <div class="sold-ribbon">VENDUE</div>
        <?php elseif ($isOut): ?>
          <div class="sold-ribbon bg-secondary">RUPTURE</div>
        <?php endif; ?>

            <div
              id="carouselAnnonce"
              class="carousel slide rounded overflow-hidden shadow-sm"
              data-bs-ride="carousel"
              data-bs-interval="4300"
              data-bs-pause="hover"
              data-bs-touch="true">
            <div class="carousel-inner">
            <?php foreach ($galleryImages as $index => $imgSrc): ?>
              <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                <div
                  class="annonce-zoom-trigger"
                  data-gallery-index="<?php echo (int)$index; ?>"
                  data-image="<?php echo e($imgSrc); ?>"
                  style="height:420px;"
                >
                  <img
                    src="<?php echo e($imgSrc); ?>"
                    class="d-block w-100 h-100 zoom-image"
                    style="object-fit:cover"
                    alt=""
                  >
                  <span class="zoom-hint"><i class="bi bi-zoom-in"></i> Zoom</span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (count($galleryImages) > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#carouselAnnonce" data-bs-slide="prev">
              <span class="carousel-control-prev-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Précédent</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carouselAnnonce" data-bs-slide="next">
              <span class="carousel-control-next-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Suivant</span>
            </button>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($hasMap): ?>
        <div class="card shadow-sm border-0 mt-4">
          <div class="card-body p-3">
            <div class="fw-semibold mb-2">
              <i class="bi bi-geo-alt-fill"></i> Localisation de l’annonce
            </div>

            <div id="mapContainer" class="map-clickable-wrap">
              <iframe
                id="annonce-mini-map"
                src="https://www.google.com/maps?q=<?php echo e($latitude); ?>,<?php echo e($longitude); ?>&z=14&output=embed"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen
              ></iframe>

              <div class="map-click-overlay"></div>

              <div class="map-nav-hint">
                <i class="bi bi-cursor-fill"></i> Cliquer pour naviguer
              </div>
            </div>

            <div class="small text-muted mt-2">
              Position approximative sur la carte.
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-12 col-lg-5">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4">

          <div class="d-flex justify-content-between align-items-start gap-2">
            <h2 class="fw-bold mb-1"><?php echo e($a["titre"]); ?></h2>

            <div class="text-end">
              <?php if (hasPromo($a)): ?>
                <div class="old-price">
                  <?php echo number_format((float)$a["ancien_prix"], 2); ?> DH
                </div>
                <div class="badge text-bg-danger fs-6">
                  <?php echo number_format((float)$a["prix"], 2); ?> DH
                </div>
              <?php else: ?>
                <span class="badge text-bg-dark fs-6"><?php echo number_format((float)$a["prix"], 2); ?> DH</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="text-muted mb-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
              <div>
                <a href="vendeur.php?id=<?php echo (int)$a["id_vendeur"]; ?>" class="vendeur-link">
                  <i class="bi bi-person"></i> <?php echo e($a["vendeur_nom"]); ?>
                </a>
              </div>

              <div class="small">
                <?php echo starsHtml($avg); ?>
                <span class="text-muted"><?php echo e($avg); ?>/5 (<?php echo (int)$cnt; ?> avis)</span>
              </div>
            </div>

            <div class="mt-1">
              <i class="bi bi-geo-alt"></i> <?php echo e($a["ville"] ?? "Ville inconnue"); ?>
              · <i class="bi bi-box"></i> Stock: <?php echo (int)$stock; ?>
              <?php if ($canSeeViews): ?>
                · <i class="bi bi-eye"></i> <?php echo (int)$nbVues; ?> vues
              <?php endif; ?>
            </div>
          </div>

          <div class="mb-3 d-flex flex-wrap gap-2">
            <span class="badge badge-soft"><?php echo e($modeLabel); ?></span>
            <span class="badge badge-soft"><?php echo e($statut); ?></span>

            <?php if ($livraisonActive): ?>
              <span class="badge badge-soft">
                <i class="bi bi-truck"></i> Livraison dispo
              </span>
            <?php endif; ?>

            <?php if (hasPromo($a)): ?>
              <span class="badge text-bg-danger">Promotion</span>
            <?php endif; ?>
          </div>

          <?php if ($livraisonActive): ?>
            <div class="alert alert-info py-2">
              <div class="fw-semibold mb-1"><i class="bi bi-truck"></i> Livraison</div>
              <div class="small text-muted">
                Même ville: <b><?php echo number_format($prixSame, 2); ?> DH</b> ·
                Autre ville: <b><?php echo number_format($prixOther, 2); ?> DH</b>
              </div>
              <div class="small text-muted mt-1">
                (Le prix final sera calculé au checkout selon ta ville.)
              </div>
            </div>
          <?php endif; ?>

          <?php if (!$isActive): ?>
            <div class="alert alert-warning">
              Cette annonce n’est pas disponible (statut: <b><?php echo e($statut); ?></b>).
            </div>
          <?php endif; ?>

          <?php if ($isSold): ?>
            <div class="alert alert-danger">
              Cette annonce est <b>VENDUE</b>.
            </div>
          <?php elseif ($isOut): ?>
            <div class="alert alert-secondary">
              Produit en <b>RUPTURE DE STOCK</b>.
            </div>
          <?php endif; ?>

          <p class="mb-4"><?php echo nl2br(e($a["description"])); ?></p>

          <div class="d-flex flex-wrap gap-2">
            <?php if ($isActive && !$isSold && $canBuy): ?>
              <?php if ($stockOk): ?>
                <a class="btn btn-primary"
                   href="actions/panier_action.php?action=add&id=<?php echo (int)$a["id_annonce"]; ?>">
                  <i class="bi bi-cart-plus"></i> Ajouter au panier
                </a>

                <a class="btn btn-dark"
                   href="actions/buy_now.php?id=<?php echo (int)$a["id_annonce"]; ?>">
                  <i class="bi bi-credit-card"></i> Acheter maintenant
                </a>
              <?php else: ?>
                <button class="btn btn-primary" disabled>
                  <i class="bi bi-cart-plus"></i> Ajouter au panier
                </button>
                <button class="btn btn-dark" disabled>
                  <i class="bi bi-credit-card"></i> Rupture de stock
                </button>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($isActive && !$isSold && $canChat): ?>
              <a class="btn btn-outline-primary"
                 href="messages.php?annonce=<?php echo (int)$a["id_annonce"]; ?>&to=<?php echo (int)$a["id_vendeur"]; ?>">
                <i class="bi bi-chat-dots"></i> Contacter le vendeur
              </a>
            <?php endif; ?>

            <a class="btn btn-outline-dark"
               href="vendeur.php?id=<?php echo (int)$a["id_vendeur"]; ?>">
              <i class="bi bi-person-badge"></i> Voir profil vendeur
            </a>

            <button type="button" class="btn btn-outline-secondary" id="copyAnnonceLinkBtn">
              <i class="bi bi-link-45deg"></i> Copier lien
            </button>

            <button type="button" class="btn btn-outline-success" id="shareAnnonceBtn">
              <i class="bi bi-share"></i> Partager
            </button>

            <?php if (isLoggedIn() && !$isFromAdmin): ?>
              <button
                type="button"
                class="btn btn-outline-danger favori-toggle-btn <?php echo $isFavori ? 'active' : ''; ?>"
                data-id="<?php echo (int)$a["id_annonce"]; ?>"
              >
                <i class="bi <?php echo $isFavori ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                <span><?php echo $isFavori ? 'Retirer favori' : 'Ajouter favori'; ?></span>
              </button>
            <?php endif; ?>

            <?php if (isLoggedIn() && !$isOwner && !$isFromAdmin): ?>
              <button
                type="button"
                class="btn btn-outline-warning"
                data-bs-toggle="modal"
                data-bs-target="#signalementModal"
              >
                <i class="bi bi-flag"></i> Signaler
              </button>
            <?php endif; ?>

            <a class="btn btn-outline-secondary" href="<?php echo e($backUrl); ?>">Retour</a>
          </div>

          <div id="annonceShareMsg" class="small mt-2 text-success" style="display:none;"></div>

          <?php if ($isActive && !$canBuy && !$canChat): ?>
            <div class="text-muted small mt-3">
              Aucun mode disponible pour cette annonce.
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>

  <?php if (count($similarItems) > 0): ?>
    <div class="mt-5">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h4 class="fw-bold mb-0">Annonces similaires</h4>
          <div class="text-muted small">Produits proches de cette annonce.</div>
        </div>
      </div>

      <div class="row g-3">
        <?php foreach ($similarItems as $s): ?>
          <div class="col-12 col-sm-6 col-lg-3">
            <a class="similar-card-link" href="annonce.php?id=<?php echo (int)$s["id_annonce"]; ?>">
              <div class="card similar-card h-100 shadow-sm border-0">
                <img
                  src="<?php echo e(similarImgUrl($s)); ?>"
                  class="card-img-top"
                  style="height:190px;object-fit:cover"
                  alt=""
                >

                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start gap-2">
                    <h6 class="card-title mb-1"><?php echo e($s["titre"]); ?></h6>

                    <div class="text-end">
                      <?php if (hasPromo($s)): ?>
                        <div class="old-price">
                          <?php echo number_format((float)$s["ancien_prix"], 2); ?> DH
                        </div>
                        <div class="new-price">
                          <?php echo number_format((float)$s["prix"], 2); ?> DH
                        </div>
                      <?php else: ?>
                        <span class="badge text-bg-dark">
                          <?php echo number_format((float)$s["prix"], 2); ?> DH
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="text-muted small mb-2">
                    <i class="bi bi-person"></i> <?php echo e($s["vendeur_nom"]); ?>
                    <?php if (!empty($s["ville"])): ?>
                      · <i class="bi bi-geo-alt"></i> <?php echo e($s["ville"]); ?>
                    <?php endif; ?>
                  </div>

                  <div class="small text-muted">
                    Catégorie: <b><?php echo e($s["categorie"] ?? ""); ?></b>
                  </div>

                  <?php if (!empty($s["marque"])): ?>
                    <div class="small text-muted">
                      Marque: <b><?php echo e($s["marque"]); ?></b>
                    </div>
                  <?php endif; ?>

                  <div class="small text-muted mt-1">
                    Mode: <b><?php echo e(modeLabelText((string)($s["mode_vente"] ?? ""))); ?></b>
                  </div>

                  <?php if (hasPromo($s)): ?>
                    <div class="mt-2">
                      <span class="badge text-bg-danger">Promotion</span>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if (isLoggedIn() && !$isOwner && !$isFromAdmin): ?>
  <div class="modal fade" id="signalementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content border-0 shadow">
        <form action="actions/signalement_action.php" method="POST">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-flag"></i> Signaler cette annonce</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <input type="hidden" name="id_annonce" value="<?php echo (int)$a["id_annonce"]; ?>">

            <div class="mb-3">
              <label class="form-label">Motif</label>
              <select name="motif" class="form-select" required>
                <option value="">Choisir...</option>
                <option value="ARNAQUE">Arnaque</option>
                <option value="FAUSSE_ANNONCE">Fausse annonce</option>
                <option value="CONTENU_INTERDIT">Contenu interdit</option>
                <option value="PRIX_SUSPECT">Prix suspect</option>
                <option value="SPAM">Spam</option>
                <option value="AUTRE">Autre</option>
              </select>
            </div>

            <div class="mb-0">
              <label class="form-label">Description (optionnelle)</label>
              <textarea
                name="description"
                class="form-control"
                rows="4"
                placeholder="Explique brièvement le problème..."
              ></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-warning">
              <i class="bi bi-send"></i> Envoyer le signalement
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($hasMap): ?>
<div class="modal fade" id="navigationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-geo-alt-fill"></i> Choisir navigation
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body text-center">
        <a id="googleMapsBtn" class="btn btn-primary w-100 mb-2" target="_blank" rel="noopener noreferrer">
          <i class="bi bi-map"></i> Ouvrir avec Google Maps
        </a>

        <a id="wazeBtn" class="btn btn-dark w-100 mb-2" target="_blank" rel="noopener noreferrer">
          <i class="bi bi-sign-turn-right"></i> Ouvrir avec Waze
        </a>

        <button id="copyPositionBtn" type="button" class="btn btn-outline-secondary w-100 mb-2">
          <i class="bi bi-copy"></i> Copier position
        </button>

        <button class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">
          Annuler
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="modal fade gallery-modal" id="imageZoomModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen m-0">
    <div class="modal-content">
      <div class="modal-header border-0 bg-black py-2">
        <button
          type="button"
          class="btn-close btn-close-white ms-auto gallery-close"
          data-bs-dismiss="modal"
        ></button>
      </div>

      <div class="modal-body p-0">
        <div class="gallery-stage">
          <button type="button" class="gallery-nav-btn gallery-prev" id="galleryPrevBtn">
            <i class="bi bi-chevron-left"></i>
          </button>

          <img id="zoomedAnnonceImage" src="" alt="">

          <button type="button" class="gallery-nav-btn gallery-next" id="galleryNextBtn">
            <i class="bi bi-chevron-right"></i>
          </button>

          <div class="gallery-counter" id="galleryCounter">1 / 1</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const annonceLat = <?php echo json_encode($latitude); ?>;
  const annonceLng = <?php echo json_encode($longitude); ?>;

  const annonceShareUrl = <?php echo json_encode($shareUrl, JSON_UNESCAPED_UNICODE); ?>;
  const annonceTitle = <?php echo json_encode((string)$a["titre"], JSON_UNESCAPED_UNICODE); ?>;
  const copyBtn = document.getElementById("copyAnnonceLinkBtn");
  const shareBtn = document.getElementById("shareAnnonceBtn");
  const shareMsg = document.getElementById("annonceShareMsg");

  function showShareMsg(text, isError = false) {
    if (!shareMsg) return;
    shareMsg.style.display = "block";
    shareMsg.className = "small mt-2 " + (isError ? "text-danger" : "text-success");
    shareMsg.textContent = text;

    setTimeout(function () {
      shareMsg.style.display = "none";
    }, 2500);
  }

  const mapContainer = document.getElementById("mapContainer");
  const navigationModalEl = document.getElementById("navigationModal");
  const googleMapsBtn = document.getElementById("googleMapsBtn");
  const wazeBtn = document.getElementById("wazeBtn");
  const copyPositionBtn = document.getElementById("copyPositionBtn");

  if (mapContainer && navigationModalEl && googleMapsBtn && wazeBtn && copyPositionBtn && annonceLat !== null && annonceLng !== null) {
    mapContainer.addEventListener("click", function () {
      googleMapsBtn.href = "https://www.google.com/maps/dir/?api=1&destination=" + annonceLat + "," + annonceLng;
      wazeBtn.href = "https://waze.com/ul?ll=" + annonceLat + "," + annonceLng + "&navigate=yes";

      const navigationModal = new bootstrap.Modal(navigationModalEl);
      navigationModal.show();
    });

    copyPositionBtn.addEventListener("click", async function () {
      try {
        await navigator.clipboard.writeText(annonceLat + "," + annonceLng);
        showShareMsg("Position copiée.");
      } catch (e) {
        showShareMsg("Impossible de copier la position.", true);
      }
    });
  }

  if (copyBtn) {
    copyBtn.addEventListener("click", async function () {
      try {
        await navigator.clipboard.writeText(annonceShareUrl);
        showShareMsg("Lien copié.");
      } catch (e) {
        showShareMsg("Impossible de copier le lien.", true);
      }
    });
  }

  if (shareBtn) {
    shareBtn.addEventListener("click", async function () {
      try {
        if (navigator.share) {
          await navigator.share({
            title: annonceTitle,
            text: "Regarde cette annonce sur Fluxo",
            url: annonceShareUrl
          });
        } else {
          await navigator.clipboard.writeText(annonceShareUrl);
          showShareMsg("Partage non supporté. Lien copié.");
        }
      } catch (e) {
      }
    });
  }

  const btn = document.querySelector(".favori-toggle-btn");
  if (btn) {
    btn.addEventListener("click", async function () {
      const annonceId = this.dataset.id;
      const icon = this.querySelector("i");
      const text = this.querySelector("span");

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
          text.textContent = "Retirer favori";
        } else {
          this.classList.remove("active");
          icon.className = "bi bi-heart";
          text.textContent = "Ajouter favori";
        }

      } catch (e) {
        showToast("Erreur serveur.");
      } finally {
        this.disabled = false;
      }
    });
  }

  const zoomTriggers = document.querySelectorAll(".annonce-zoom-trigger");
  const zoomModalEl = document.getElementById("imageZoomModal");
  const zoomedImage = document.getElementById("zoomedAnnonceImage");
  const prevBtn = document.getElementById("galleryPrevBtn");
  const nextBtn = document.getElementById("galleryNextBtn");
  const counter = document.getElementById("galleryCounter");

  if (!zoomTriggers.length || !zoomModalEl || !zoomedImage) return;

  const galleryImages = Array.from(zoomTriggers).map(function (item) {
    return item.getAttribute("data-image");
  });

  let currentIndex = 0;
  const zoomModal = new bootstrap.Modal(zoomModalEl);

  function renderImage() {
    if (!galleryImages.length) return;
    zoomedImage.src = galleryImages[currentIndex];
    counter.textContent = (currentIndex + 1) + " / " + galleryImages.length;
  }

  function showImage(index) {
    if (!galleryImages.length) return;

    if (index < 0) index = galleryImages.length - 1;
    if (index >= galleryImages.length) index = 0;

    currentIndex = index;
    renderImage();
  }

  zoomTriggers.forEach(function (item) {
    item.addEventListener("mousemove", function (e) {
      const img = this.querySelector(".zoom-image");
      if (!img) return;

      const rect = this.getBoundingClientRect();
      const x = ((e.clientX - rect.left) / rect.width) * 100;
      const y = ((e.clientY - rect.top) / rect.height) * 100;

      img.style.transformOrigin = x + "% " + y + "%";
    });

    item.addEventListener("mouseleave", function () {
      const img = this.querySelector(".zoom-image");
      if (!img) return;
      img.style.transformOrigin = "center center";
    });

    item.addEventListener("click", function () {
      const index = parseInt(this.getAttribute("data-gallery-index") || "0", 10);
      showImage(index);
      zoomModal.show();
    });
  });

  if (prevBtn) {
    prevBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      showImage(currentIndex - 1);
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      showImage(currentIndex + 1);
    });
  }

  document.addEventListener("keydown", function (e) {
    if (!zoomModalEl.classList.contains("show")) return;

    if (e.key === "ArrowLeft") {
      showImage(currentIndex - 1);
    } else if (e.key === "ArrowRight") {
      showImage(currentIndex + 1);
    }
  });

  zoomedImage.addEventListener("click", function () {
    showImage(currentIndex + 1);
  });
});
</script>

<?php include "includes/footer.php"; ?>