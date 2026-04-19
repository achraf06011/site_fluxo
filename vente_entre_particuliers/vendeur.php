<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";

if (session_status() === PHP_SESSION_NONE) session_start();

$idVendeur = (int)($_GET["id"] ?? 0);
if ($idVendeur <= 0) die("Vendeur invalide.");

function e($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

function imgUrl($x) {
  $file = $x["cover_image"] ?? null;
  if (!$file) return "https://picsum.photos/seed/" . ((int)($x["id_annonce"] ?? 0) ?: rand(1, 9999)) . "/800/600";
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

/* =========================
   VENDEUR
   ========================= */
$stmt = $pdo->prepare("
  SELECT id_user, nom, email, date_inscription, role, statut
  FROM user
  WHERE id_user = ?
  LIMIT 1
");
$stmt->execute([$idVendeur]);
$vendeur = $stmt->fetch();

if (!$vendeur) die("Vendeur introuvable.");

/* =========================
   STATS VENDEUR
   ========================= */
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
  $row = $stmt->fetch();

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
  $row = $stmt->fetch();

  if ($row) {
    $stats["avg_note"] = round((float)($row["avg_note"] ?? 0), 1);
    $stats["total_reviews"] = (int)($row["total_reviews"] ?? 0);
  }
} catch (Exception $e) {
}

/* =========================
   DETECTER COLONNES REVIEW
   ========================= */
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

/* =========================
   LISTE DES AVIS
   ========================= */
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
  $reviews = $stmt->fetchAll();
} catch (Exception $e) {
  $reviews = [];
}

/* =========================
   ANNONCES DU VENDEUR
   ========================= */
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

$stmt = $pdo->prepare("
  SELECT a.*, u.nom AS vendeur_nom
  $favoriJoin
  FROM annonce a
  JOIN user u ON u.id_user = a.id_vendeur
  WHERE a.id_vendeur = ?
    AND a.statut = 'ACTIVE'
  ORDER BY a.id_annonce DESC
  LIMIT 60
");
$stmt->execute([$idVendeur]);
$annonces = $stmt->fetchAll();

$isOwnProfile = isLoggedIn() && currentUserId() === $idVendeur;

include "includes/header.php";
include "includes/navbar.php";
?>

<style>
.vendeur-hero {
  border-radius: 22px;
  background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
  color: #fff;
}
.vendeur-avatar {
  width: 82px;
  height: 82px;
  border-radius: 50%;
  background: rgba(255,255,255,.12);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 34px;
  font-weight: 700;
  border: 1px solid rgba(255,255,255,.15);
}
.stats-card {
  border-radius: 18px;
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
.favori-card-btn.active {
  background: #dc3545 !important;
  color: #fff !important;
  border-color: #dc3545 !important;
}
.review-card {
  border-radius: 18px;
}
.review-avatar {
  width: 46px;
  height: 46px;
  border-radius: 50%;
  background: #f1f3f5;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 18px;
  color: #333;
  flex: 0 0 46px;
}
.review-comment {
  white-space: pre-wrap;
}
</style>

<div class="container my-4">
  <div class="vendeur-hero p-4 p-md-5 shadow-sm">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
      <div class="d-flex align-items-center gap-3">
        <div class="vendeur-avatar">
          <?php echo e(mb_strtoupper(mb_substr((string)$vendeur["nom"], 0, 1))); ?>
        </div>

        <div>
          <h2 class="fw-bold mb-1"><?php echo e($vendeur["nom"]); ?></h2>

          <div class="d-flex flex-wrap gap-3 small opacity-75">
            <span><i class="bi bi-person-badge"></i> Vendeur</span>
            <span>
              <i class="bi bi-calendar-event"></i>
              Inscrit le
              <?php
                $dateIns = trim((string)($vendeur["date_inscription"] ?? ""));
                echo e($dateIns !== "" ? substr($dateIns, 0, 10) : "—");
              ?>
            </span>
          </div>

          <div class="mt-2">
            <?php echo starsHtml((float)$stats["avg_note"]); ?>
            <span class="ms-1">
              <?php echo e(number_format((float)$stats["avg_note"], 1)); ?>/5
              (<?php echo (int)$stats["total_reviews"]; ?> avis)
            </span>
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <?php if (!$isOwnProfile && isLoggedIn()): ?>
          <a
            class="btn btn-light"
            href="messages.php?to=<?php echo (int)$idVendeur; ?>"
            onclick="event.preventDefault(); showToast('Pour contacter ce vendeur, ouvre une annonce de ce vendeur puis clique sur Message.');"
          >
            <i class="bi bi-chat-dots"></i> Contacter
          </a>
        <?php endif; ?>

        <?php if ($isOwnProfile): ?>
          <a class="btn btn-light" href="profil.php">
            <i class="bi bi-person"></i> Mon profil
          </a>
          <a class="btn btn-outline-light" href="mes_annonces.php">
            <i class="bi bi-megaphone"></i> Mes annonces
          </a>
        <?php else: ?>
          <a class="btn btn-outline-light" href="index.php">
            <i class="bi bi-arrow-left"></i> Retour
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-12 col-md-4">
      <div class="card shadow-sm border-0 stats-card h-100">
        <div class="card-body">
          <div class="text-muted small mb-1">Annonces publiées</div>
          <div class="fw-bold fs-3"><?php echo (int)$stats["total_annonces"]; ?></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-4">
      <div class="card shadow-sm border-0 stats-card h-100">
        <div class="card-body">
          <div class="text-muted small mb-1">Annonces actives</div>
          <div class="fw-bold fs-3"><?php echo (int)$stats["annonces_actives"]; ?></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-4">
      <div class="card shadow-sm border-0 stats-card h-100">
        <div class="card-body">
          <div class="text-muted small mb-1">Avis reçus</div>
          <div class="fw-bold fs-3"><?php echo (int)$stats["total_reviews"]; ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <h4 class="fw-bold mb-0">Avis des acheteurs</h4>
        <div class="text-muted small"><?php echo (int)$stats["total_reviews"]; ?> avis reçu(s)</div>
      </div>
    </div>

    <?php if (count($reviews) > 0): ?>
      <div class="row g-3">
        <?php foreach ($reviews as $r): ?>
          <?php
            $buyerName = trim((string)($r["acheteur_nom"] ?? "Utilisateur"));
            $buyerInitial = mb_strtoupper(mb_substr($buyerName !== "" ? $buyerName : "U", 0, 1));
            $reviewDate = trim((string)($r["review_date"] ?? ""));
            $reviewComment = trim((string)($r["review_comment"] ?? ""));
            $reviewNote = (float)($r["note"] ?? 0);
          ?>
          <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 review-card h-100">
              <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                  <div class="review-avatar"><?php echo e($buyerInitial); ?></div>

                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                      <div>
                        <div class="fw-semibold"><?php echo e($buyerName); ?></div>
                        <div class="small">
                          <?php echo starsHtml($reviewNote); ?>
                          <span class="ms-1 text-muted"><?php echo e(number_format($reviewNote, 1)); ?>/5</span>
                        </div>
                      </div>

                      <?php if ($reviewDate !== ""): ?>
                        <div class="small text-muted">
                          <?php echo e(substr($reviewDate, 0, 16)); ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <?php if ($reviewComment !== ""): ?>
                      <div class="text-muted mt-3 review-comment"><?php echo e($reviewComment); ?></div>
                    <?php else: ?>
                      <div class="text-muted mt-3 fst-italic">Aucun commentaire ajouté.</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-light border">
        Ce vendeur n’a pas encore reçu d’avis.
      </div>
    <?php endif; ?>
  </div>

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-5 mb-3">
    <div>
      <h4 class="fw-bold mb-0">Annonces du vendeur</h4>
      <div class="text-muted small"><?php echo count($annonces); ?> annonce(s) active(s)</div>
    </div>
  </div>

  <div class="row g-3">
    <?php foreach ($annonces as $a): ?>
      <?php
        $modeV = (string)($a["mode_vente"] ?? "");
        $canBuy = in_array($modeV, ["PAIEMENT_DIRECT", "LES_DEUX"], true);
        $canChat = in_array($modeV, ["POSSIBILITE_CONTACTE", "LES_DEUX"], true);
        $isFavori = !empty($a["is_favori"]);
      ?>
      <div class="col-12 col-sm-6 col-lg-4">
        <a class="annonce-card-link" href="annonce.php?id=<?php echo (int)$a["id_annonce"]; ?>">
          <div class="card annonce-card card-hover h-100 shadow-sm border-0">
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

              <div class="d-flex gap-2 flex-wrap card-actions">
                <a class="btn btn-outline-secondary btn-sm" href="annonce.php?id=<?php echo (int)$a["id_annonce"]; ?>">
                  Voir
                </a>

                <?php if ($canBuy): ?>
                  <a class="btn btn-primary btn-sm" href="annonce.php?id=<?php echo (int)$a["id_annonce"]; ?>">
                    Acheter
                  </a>
                <?php endif; ?>

                <?php if ($canChat): ?>
                  <a class="btn btn-outline-primary btn-sm" href="messages.php?annonce=<?php echo (int)$a["id_annonce"]; ?>&to=<?php echo (int)$a["id_vendeur"]; ?>">
                    Message
                  </a>
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
        <div class="alert alert-warning mb-0">Ce vendeur n’a aucune annonce active.</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
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