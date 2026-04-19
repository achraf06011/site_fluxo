<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) die("Commande invalide.");

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

function imgUrl(?string $file): ?string {
  if (!$file) return null;
  if (str_starts_with($file, "http")) return $file;
  return "../uploads/" . $file;
}

// 1) Order + client + paiement
$stmt = $pdo->prepare("
  SELECT o.*,
         u.nom AS client_nom, u.email AS client_email,
         p.statut AS pay_statut, p.methode AS pay_methode
  FROM orders o
  JOIN user u ON u.id_user = o.id_user
  LEFT JOIN paiement p ON p.id_order = o.id_order
  WHERE o.id_order = ?
  LIMIT 1
");
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) die("Commande introuvable.");

// 2) Items
$stmt = $pdo->prepare("
  SELECT od.*,
         a.titre, a.cover_image, a.id_vendeur
  FROM order_details od
  JOIN annonce a ON a.id_annonce = od.id_annonce
  WHERE od.id_order = ?
  ORDER BY od.id_detail ASC
");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

include __DIR__ . "/../includes/header.php";
include __DIR__ . "/../includes/admin_navbar.php";
?>

<div class="container my-4" style="max-width: 1050px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h2 class="fw-bold mb-0">Commande #<?php echo (int)$order["id_order"]; ?></h2>
      <div class="text-muted small">
        Client: <b><?php echo e($order["client_nom"]); ?></b> (<?php echo e($order["client_email"]); ?>)
        · Date: <b><?php echo e($order["date_commande"] ?? ""); ?></b>
      </div>
    </div>
    <a class="btn btn-outline-secondary" href="orders.php">Retour</a>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h6 class="fw-bold">Statuts</h6>
          <div class="mb-2">
            Commande: <span class="badge text-bg-secondary"><?php echo e($order["statut"] ?? ""); ?></span>
          </div>
          <div class="mb-2">
            Paiement:
            <?php
              $ps = $order["pay_statut"] ?? "—";
              $pbadge = "text-bg-warning";
              if ($ps === "ACCEPTE") $pbadge = "text-bg-success";
              if ($ps === "REFUS") $pbadge = "text-bg-danger";
              if ($ps === "—") $pbadge = "text-bg-secondary";
            ?>
            <span class="badge <?php echo $pbadge; ?>"><?php echo e($ps); ?></span>
            <div class="text-muted small"><?php echo e($order["pay_methode"] ?? ""); ?></div>
          </div>

          <hr>

          <h6 class="fw-bold">Livraison</h6>
          <?php $mode = $order["mode_reception"] ?? "PICKUP"; ?>
          <div class="mb-1"><b><?php echo ($mode === "LIVRAISON") ? "Livraison" : "Main propre"; ?></b></div>
          <?php if ($mode === "LIVRAISON"): ?>
            <div class="text-muted small">
              Ville: <?php echo e($order["ville_livraison"] ?? "—"); ?><br>
              Frais: <?php echo number_format((float)($order["frais_livraison"] ?? 0), 2); ?> DH
            </div>
          <?php endif; ?>

          <hr>

          <h6 class="fw-bold">Total</h6>
          <div class="fs-5 fw-bold"><?php echo number_format((float)$order["total"], 2); ?> DH</div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h6 class="fw-bold mb-3">Articles</h6>

          <?php if (!$items): ?>
            <div class="alert alert-warning mb-0">Aucun article.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead class="table-light">
                  <tr>
                    <th style="width:90px;">Photo</th>
                    <th>Annonce</th>
                    <th style="width:110px;">Qté</th>
                    <th style="width:150px;">Prix unit.</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $it): ?>
                    <?php $img = imgUrl($it["cover_image"] ?? null); ?>
                    <tr>
                      <td>
                        <?php if ($img): ?>
                          <img src="<?php echo e($img); ?>" alt=""
                               style="width:72px;height:54px;object-fit:cover;border-radius:10px;border:1px solid #eee;">
                        <?php else: ?>
                          <div class="text-muted small">—</div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="fw-semibold"><?php echo e($it["titre"] ?? ""); ?></div>
                        <div class="text-muted small">
                          Annonce #<?php echo (int)$it["id_annonce"]; ?>
                        </div>
                      </td>
                      <td><?php echo (int)($it["quantite"] ?? 0); ?></td>
                      <td><?php echo number_format((float)($it["prix_unitaire"] ?? 0), 2); ?> DH</td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>