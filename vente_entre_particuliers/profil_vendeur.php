<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";

if (session_status() === PHP_SESSION_NONE) session_start();

$vendeurId = (int)($_GET["id"] ?? 0);
if ($vendeurId <= 0) die("Vendeur invalide.");

function e($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

function imgUrl($x) {
  $file = $x["cover_image"] ?? null;
  if (!$file) return "https://picsum.photos/seed/" . (int)($x["id_annonce"] ?? 0) . "/800/600";
  if (str_starts_with($file, "http")) return $file;
  return "uploads/" . $file;
}

function modeLabel(string $mode): string {
  return match ($mode) {
    "PAIEMENT_DIRECT" => "PAIEMENT DIRECT",
    "POSSIBILITE_CONTACTE" => "CONTACTER LE VENDEUR",
    "LES_DEUX" => "PAIEMENT DIRECT OU CONTACTER VENDEUR",
    default => $mode,
  };
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

// vendeur
$stmt = $pdo->prepare("
  SELECT id_user, nom, email, role
  FROM user
  WHERE id_user = ?
  LIMIT 1
");
$stmt->execute([$vendeurId]);
$vendeur = $stmt->fetch();

if (!$vendeur) {
  die("Vendeur introuvable.");
}

// stats vendeur
$stats = [
  "annonces_actives" => 0,
  "annonces_vendues" => 0,
  "total_vues" => 0,
  "avg_note" => 0,
  "total_reviews" => 0,
];

try {
  $stmt = $pdo->prepare("
    SELECT
      SUM(CASE WHEN statut = 'ACTIVE' THEN 1 ELSE 0 END) AS annonces_actives,
      SUM(CASE WHEN statut = 'VENDUE' THEN 1 ELSE 0 END) AS annonces_vendues,
      SUM(COALESCE(nb_vues, 0)) AS total_vues
    FROM annonce
    WHERE id_vendeur = ?
  ");
  $stmt->execute([$vendeurId]);
  $row = $stmt->fetch();

  if ($row) {
    $stats["annonces_actives"] = (int)($row["annonces_actives"] ?? 0);
    $stats["annonces_vendues"] = (int)($row["annonces_vendues"] ?? 0);
    $stats["total_vues"] = (int)($row["total_vues"] ?? 0);
  }
} catch (Exception $e) {
}

try {
  $stmt = $pdo->prepare("
    SELECT
      AVG(r.note) AS avg_note,
      COUNT(r.id_review) AS total_reviews
    FROM review r
    JOIN annonce a ON a.id_annonce = r.id_annonce
    WHERE a.id_vendeur = ?
  ");
  $stmt->execute([$vendeurId]);
  $row = $stmt->fetch();

  if ($row) {
    $stats["avg_note"] = round((float)($row["avg_note"] ?? 0), 1);
    $stats["total_reviews"] = (int)($row["total_reviews"] ?? 0);
  }
} catch (Exception $e) {
}

// annonces actives du vendeur
$annonces = [];
try {
  $stmt = $pdo->prepare("
    SELECT id_annonce, titre, prix, stock, ville, mode_vente, cover_image
    FROM annonce
    WHERE id_vendeur = ?
      AND statut = 'ACTIVE'
    ORDER BY id_annonce DESC
    LIMIT 50
  ");
  $stmt->execute([$vendeurId]);
  $annonces = $stmt->fetchAll();
} catch (Exception $e) {
  $annonces = [];
}

// avis vendeur
$reviews = [];
try {
  $stmt = $pdo->prepare("
    SELECT
      r.note,
      r.commentaire,
      r.created_at,
      u.nom AS auteur_nom,
      a.id_annonce,
      a.titre AS annonce_titre
    FROM review r
    JOIN user u ON u.id_user = r.id_user
    JOIN annonce a ON a.id_annonce = r.id_annonce
    WHERE a.id_vendeur = ?
    ORDER BY r.id_review DESC
    LIMIT 20
  ");
  $stmt->execute([$vendeurId]);
  $reviews = $stmt->fetchAll();
} catch (Exception $e) {
  $reviews = [];
}

$isOwnProfile = isLoggedIn() && ((int)currentUserId() === $vendeurId);
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<style>
.public-seller-hero{
  border-radius: 20px;
}
.public-annonce-card{
  transition: transform .15s ease, box-shadow .15s ease;
  cursor: pointer;
}
.public-annonce-card:hover{
  transform: translateY(-2px);
  box-shadow: 0 10px 24px rgba(0,0,0,.08);
}
.public-annonce-card .stretched-link{
  z-index: 1;
}
.public-annonce-card .btn,
.public-annonce-card button{
  position: relative;
  z-index: 2;
}
.review-card{
  border-radius: 16px;
}
</style>

<div class="container my-4">
  <div class="p-4 hero public-seller-hero">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
      <div>
        <h1 class="fw-bold mb-1">
          <i class="bi bi-person-circle"></i> <?php echo e($vendeur["nom"]); ?>
        </h1>
        <p class="mb-0 opacity-75">
          Profil vendeur public
          <?php if ($isOwnProfile): ?>
            · C’est ton profil public
          <?php endif; ?>
        </p>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-light" href="index.php">
          <i class="bi bi-arrow-left"></i> Retour
        </a>

        <?php if (isLoggedIn() && !$isOwnProfile): ?>
          <a class="btn btn-light" href="messages.php?to=<?php echo (int)$vendeurId; ?>">
            <i class="bi bi-chat-dots"></i> Contacter
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="text-muted small">Annonces actives</div>
          <div class="fs-3 fw-bold"><?php echo (int)$stats["annonces_actives"]; ?></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="text-muted small">Annonces vendues</div>
          <div class="fs-3 fw-bold"><?php echo (int)$stats["annonces_vendues"]; ?></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="text-muted small">Total vues</div>
          <div class="fs-3 fw-bold"><?php echo (int)$stats["total_vues"]; ?></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="text-muted small">Note moyenne</div>
          <div class="fw-semibold mb-1">
            <?php echo starsHtml((float)$stats["avg_note"]); ?>
          </div>
          <div class="fw-bold">
            <?php echo e($stats["avg_note"]); ?>/5
            <span class="text-muted small">(<?php echo (int)$stats["total_reviews"]; ?> avis)</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h3 class="fw-bold mb-0"><i class="bi bi-grid"></i> Annonces du vendeur</h3>
      <div class="text-muted small"><?php echo count($annonces); ?> annonce(s) active(s)</div>
    </div>

    <div class="row g-3">
      <?php foreach ($annonces as $a): ?>
        <?php
          $modeV = (string)($a["mode_vente"] ?? "");
          $canBuy = in_array($modeV, ["PAIEMENT_DIRECT", "LES_DEUX"], true);
          $canChat = in_array($modeV, ["POSSIBILITE_CONTACTE", "LES_DEUX"], true);
        ?>
        <div class="col-12 col-sm-6 col-lg-4">
          <div class="card public-annonce-card h-100 shadow-sm border-0 position-relative">
            <a class="stretched-link" href="annonce.php?id=<?php echo (int)$a["id_annonce"]; ?>" aria-label="Voir annonce"></a>

            <img src="<?php echo e(imgUrl($a)); ?>" class="card-img-top" style="height:210px;object-fit:cover" alt="">

            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <h5 class="card-title mb-1"><?php echo e($a["titre"]); ?></h5>
                <span class="badge text-bg-dark"><?php echo number_format((float)$a["prix"], 2); ?> DH</span>
              </div>

              <div class="text-muted small mb-2">
                <?php if (!empty($a["ville"])): ?>
                  <i class="bi bi-geo-alt"></i> <?php echo e($a["ville"]); ?>
                <?php endif; ?>
                · <i class="bi bi-box"></i> Stock: <?php echo (int)$a["stock"]; ?>
              </div>

              <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="annonce.php?id=<?php echo (int)$a["id_annonce"]; ?>">
                  Voir
                </a>

                <?php if ($canBuy): ?>
                  <a class="btn btn-primary btn-sm" href="annonce.php?id=<?php echo (int)$a["id_annonce"]; ?>">
                    Acheter
                  </a>
                <?php endif; ?>

                <?php if ($canChat && isLoggedIn() && !$isOwnProfile): ?>
                  <a class="btn btn-outline-primary btn-sm" href="messages.php?annonce=<?php echo (int)$a["id_annonce"]; ?>&to=<?php echo (int)$vendeurId; ?>">
                    Message
                  </a>
                <?php endif; ?>
              </div>

              <div class="mt-2 small text-muted">
                Mode: <b><?php echo e(modeLabel($modeV)); ?></b>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (count($annonces) === 0): ?>
        <div class="col-12">
          <div class="alert alert-warning mb-0">
            Ce vendeur n’a aucune annonce active pour le moment.
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="mt-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h3 class="fw-bold mb-0"><i class="bi bi-star"></i> Avis reçus</h3>
      <div class="text-muted small"><?php echo count($reviews); ?> avis affiché(s)</div>
    </div>

    <div class="row g-3">
      <?php foreach ($reviews as $r): ?>
        <div class="col-12">
          <div class="card shadow-sm border-0 review-card">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                  <div class="fw-semibold">
                    <i class="bi bi-person"></i> <?php echo e($r["auteur_nom"] ?? "Utilisateur"); ?>
                  </div>
                  <div class="small text-muted">
                    Sur l’annonce :
                    <a href="annonce.php?id=<?php echo (int)($r["id_annonce"] ?? 0); ?>">
                      <?php echo e($r["annonce_titre"] ?? "Annonce"); ?>
                    </a>
                  </div>
                </div>

                <div class="text-end">
                  <div><?php echo starsHtml((float)($r["note"] ?? 0)); ?></div>
                  <div class="small text-muted">
                    <?php echo e(substr((string)($r["created_at"] ?? ""), 0, 16)); ?>
                  </div>
                </div>
              </div>

              <div class="mt-3">
                <?php
                  $commentaire = trim((string)($r["commentaire"] ?? ""));
                  echo $commentaire !== "" ? nl2br(e($commentaire)) : '<span class="text-muted">Aucun commentaire.</span>';
                ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (count($reviews) === 0): ?>
        <div class="col-12">
          <div class="alert alert-secondary mb-0">
            Aucun avis pour le moment.
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include "includes/footer.php"; ?>