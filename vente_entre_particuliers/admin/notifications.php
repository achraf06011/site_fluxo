<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();

function e($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

function coverUrl($a) {
  $file = $a["cover_image"] ?? null;
  if (!$file) return "https://picsum.photos/seed/" . (int)$a["id_annonce"] . "/300/200";
  if (str_starts_with($file, "http")) return $file;
  return "../uploads/" . $file;
}

$stmt = $pdo->query("
  SELECT a.*, u.nom AS vendeur_nom, u.email AS vendeur_email
  FROM annonce a
  JOIN user u ON u.id_user = a.id_vendeur
  WHERE a.statut = 'EN_ATTENTE_VALIDATION'
  ORDER BY COALESCE(a.date_modification, a.date_publication) DESC, a.id_annonce DESC
  LIMIT 100
");
$rows = $stmt->fetchAll();

include __DIR__ . "/../includes/header.php";
include __DIR__ . "/../includes/admin_navbar.php";
?>

<div class="container my-4" style="max-width:1200px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="fw-bold mb-0">
      <i class="bi bi-bell"></i> Notifications admin
    </h2>
    <a class="btn btn-outline-dark" href="annonces.php?statut=EN_ATTENTE_VALIDATION">
      Voir toutes les annonces à valider
    </a>
  </div>

  <?php if (count($rows) === 0): ?>
    <div class="alert alert-success">
      Aucune nouvelle annonce à valider.
    </div>
  <?php else: ?>
    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:90px;">Photo</th>
                <th>ID</th>
                <th>Titre</th>
                <th>Vendeur</th>
                <th>Statut</th>
                <th>Date</th>
                <th style="width:280px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $a): ?>
                <tr>
                  <td>
                    <img src="<?php echo e(coverUrl($a)); ?>" alt=""
                         style="width:72px;height:54px;object-fit:cover;border-radius:10px;border:1px solid #eee;">
                  </td>
                  <td>#<?php echo (int)$a["id_annonce"]; ?></td>
                  <td>
                    <div class="fw-semibold"><?php echo e($a["titre"]); ?></div>
                    <div class="text-muted small">
                      <?php echo e($a["type"] ?? ""); ?> · <?php echo e($a["mode_vente"] ?? ""); ?>
                    </div>
                  </td>
                  <td>
                    <div><?php echo e($a["vendeur_nom"]); ?></div>
                    <div class="text-muted small"><?php echo e($a["vendeur_email"]); ?></div>
                  </td>
                  <td>
                    <span class="badge text-bg-warning"><?php echo e($a["statut"]); ?></span>
                  </td>
                  <td class="small text-muted">
                    <?php echo e(substr((string)($a["date_modification"] ?? $a["date_publication"] ?? ""), 0, 16)); ?>
                  </td>
                  <td>
                    <div class="d-flex gap-2 flex-wrap">
                      <a class="btn btn-outline-secondary btn-sm"
                         target="_blank"
                         href="../annonce.php?id=<?php echo (int)$a["id_annonce"]; ?>">
                        Voir
                      </a>

                      <form action="../actions/admin_annonce_validate.php" method="POST" class="d-inline">
                        <input type="hidden" name="id_annonce" value="<?php echo (int)$a["id_annonce"]; ?>">
                        <button class="btn btn-success btn-sm" type="submit">Valider</button>
                      </form>

                      <form action="../actions/admin_annonce_refuse.php" method="POST" class="d-inline d-flex gap-1">
                        <input type="hidden" name="id_annonce" value="<?php echo (int)$a["id_annonce"]; ?>">
                        <input type="text" name="reason" class="form-control form-control-sm" style="width:130px;" placeholder="Raison">
                        <button class="btn btn-danger btn-sm" type="submit">Refuser</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>