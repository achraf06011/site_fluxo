<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = currentUserId();

function e($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

function imgUrl($x) {
  $file = $x["cover_image"] ?? null;
  if (!$file) return "https://picsum.photos/seed/" . $x["id_annonce"] . "/800/600";
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

function hasPromo(array $a): bool {
  return isset($a["ancien_prix"]) && $a["ancien_prix"] !== null && (float)$a["ancien_prix"] > (float)$a["prix"];
}

$stmt = $pdo->prepare("
  SELECT a.*, u.nom AS vendeur_nom
  FROM favoris f
  JOIN annonce a ON a.id_annonce = f.id_annonce
  JOIN user u ON u.id_user = a.id_vendeur
  WHERE f.id_user = ?
  ORDER BY f.id_favori DESC
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll();
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<style>
.favori-card-btn.active {
  background: #dc3545 !important;
  color: #fff !important;
  border-color: #dc3545 !important;
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
.old-price {
  font-size: .85rem;
  color: #6c757d;
  text-decoration: line-through;
}
.new-price {
  color: #dc3545;
  font-weight: 700;
}
</style>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h2 class="fw-bold mb-0"><i class="bi bi-heart-fill text-danger"></i> Mes favoris</h2>
      <div class="text-muted">Toutes les annonces que tu as sauvegardées.</div>
    </div>
    <a class="btn btn-outline-secondary" href="index.php">Retour</a>
  </div>

  <div class="row g-3">
    <?php foreach ($rows as $a): ?>
      <?php
        $modeV = (string)($a["mode_vente"] ?? "");
        $canBuy = in_array($modeV, ["PAIEMENT_DIRECT", "LES_DEUX"], true);
        $canChat = in_array($modeV, ["POSSIBILITE_CONTACTE", "LES_DEUX"], true);
      ?>
      <div class="col-12 col-sm-6 col-lg-4 favori-col" id="favori-col-<?php echo (int)$a["id_annonce"]; ?>">
        <a class="annonce-card-link" href="annonce.php?id=<?php echo (int)$a["id_annonce"]; ?>">
          <div class="card annonce-card card-hover h-100 shadow-sm border-0">
            <img src="<?php echo e(imgUrl($a)); ?>" class="card-img-top" style="height:210px;object-fit:cover" alt="">
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

                <button
                  type="button"
                  class="btn btn-outline-danger btn-sm favori-card-btn active"
                  data-id="<?php echo (int)$a["id_annonce"]; ?>"
                >
                  <i class="bi bi-heart-fill"></i>
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

    <?php if (count($rows) === 0): ?>
      <div class="col-12">
        <div class="alert alert-warning mb-0">Tu n’as encore aucun favori.</div>
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
      const col = document.getElementById("favori-col-" + annonceId);

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

        if (!data.favori && col) {
          col.remove();
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