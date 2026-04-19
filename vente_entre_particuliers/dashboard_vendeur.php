<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = currentUserId();

// KPIs
$stmt = $pdo->prepare("
  SELECT
    COUNT(DISTINCT o.id_order) AS nb_commandes,
    COALESCE(SUM(od.quantite),0) AS nb_articles,
    COALESCE(SUM(od.quantite * od.prix_unitaire),0) AS total_ventes
  FROM orders o
  JOIN order_details od ON od.id_order = o.id_order
  JOIN annonce a ON a.id_annonce = od.id_annonce
  LEFT JOIN paiement p ON p.id_order = o.id_order
  WHERE a.id_vendeur = ?
    AND COALESCE(p.statut,'EN_ATTENTE') = 'ACCEPTE'
");
$stmt->execute([$userId]);
$kpi = $stmt->fetch() ?: ["nb_commandes"=>0,"nb_articles"=>0,"total_ventes"=>0];

// nouvelles ventes
$newSales = 0;
try {
  $stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT o.id_order)
    FROM orders o
    JOIN order_details od ON od.id_order = o.id_order
    JOIN annonce a ON a.id_annonce = od.id_annonce
    LEFT JOIN paiement p ON p.id_order = o.id_order
    WHERE a.id_vendeur = ?
      AND COALESCE(p.statut,'EN_ATTENTE') = 'ACCEPTE'
      AND COALESCE(o.seller_seen, 0) = 0
  ");
  $stmt->execute([$userId]);
  $newSales = (int)$stmt->fetchColumn();
} catch (Exception $e) {
  $newSales = 0;
}

// Ventes par jour
$stmt = $pdo->prepare("
  SELECT o.date_commande AS d,
         SUM(od.quantite * od.prix_unitaire) AS total
  FROM orders o
  JOIN order_details od ON od.id_order = o.id_order
  JOIN annonce a ON a.id_annonce = od.id_annonce
  LEFT JOIN paiement p ON p.id_order = o.id_order
  WHERE a.id_vendeur = ?
    AND COALESCE(p.statut,'EN_ATTENTE') = 'ACCEPTE'
    AND o.date_commande >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  GROUP BY o.date_commande
  ORDER BY o.date_commande ASC
");
$stmt->execute([$userId]);
$daily = $stmt->fetchAll();

$labels = [];
$values = [];
foreach ($daily as $row) {
  $labels[] = $row["d"];
  $values[] = (float)$row["total"];
}

// Top annonces
$stmt = $pdo->prepare("
  SELECT a.id_annonce, a.titre,
         SUM(od.quantite) AS qte,
         SUM(od.quantite * od.prix_unitaire) AS total
  FROM order_details od
  JOIN annonce a ON a.id_annonce = od.id_annonce
  JOIN orders o ON o.id_order = od.id_order
  LEFT JOIN paiement p ON p.id_order = o.id_order
  WHERE a.id_vendeur = ?
    AND COALESCE(p.statut,'EN_ATTENTE') = 'ACCEPTE'
  GROUP BY a.id_annonce, a.titre
  ORDER BY total DESC
  LIMIT 5
");
$stmt->execute([$userId]);
$top = $stmt->fetchAll();
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<div class="container my-4" style="max-width: 1150px;">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h2 class="fw-bold mb-0"><i class="bi bi-speedometer2"></i> Dashboard vendeur</h2>
      <div class="text-muted">Statistiques de tes ventes (paiements acceptés).</div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="mes_ventes.php">
        <i class="bi bi-bag-check"></i> Mes ventes
        <?php if ($newSales > 0): ?>
          <span class="badge text-bg-danger ms-1"><?php echo $newSales; ?></span>
        <?php endif; ?>
      </a>
      <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-grid"></i> Annonces</a>
    </div>
  </div>

  <?php if ($newSales > 0): ?>
    <div class="alert alert-info">
      <i class="bi bi-bell"></i> Tu as <b><?php echo $newSales; ?></b> nouvelle(s) commande(s) à consulter.
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted">Total ventes</div>
          <div class="fs-3 fw-bold"><?php echo number_format((float)$kpi["total_ventes"], 2); ?> DH</div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted">Commandes</div>
          <div class="fs-3 fw-bold"><?php echo (int)$kpi["nb_commandes"]; ?></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted">Articles vendus</div>
          <div class="fs-3 fw-bold"><?php echo (int)$kpi["nb_articles"]; ?></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted">Nouvelles ventes</div>
          <div class="fs-3 fw-bold"><?php echo (int)$newSales; ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h5 class="fw-bold mb-3">Ventes (30 derniers jours)</h5>
          <canvas id="salesChart" height="120"></canvas>
          <div class="small text-muted mt-2">Graphique basé sur les paiements acceptés.</div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-5">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h5 class="fw-bold mb-3">Top annonces</h5>

          <?php if (count($top) === 0): ?>
            <div class="alert alert-warning mb-0">Aucune vente pour le moment.</div>
          <?php else: ?>
            <div class="list-group">
              <?php foreach ($top as $t): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($t["titre"]); ?></div>
                    <div class="text-muted small">Qté vendue: <?php echo (int)$t["qte"]; ?></div>
                  </div>
                  <div class="text-end">
                    <div class="fw-bold"><?php echo number_format((float)$t["total"], 2); ?> DH</div>
                    <a class="small text-decoration-none" href="annonce.php?id=<?php echo (int)$t["id_annonce"]; ?>">Voir</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?php echo json_encode($labels); ?>;
const values = <?php echo json_encode($values); ?>;

const ctx = document.getElementById('salesChart');
new Chart(ctx, {
  type: 'line',
  data: {
    labels: labels,
    datasets: [{
      label: 'Ventes (DH)',
      data: values,
      tension: 0.35
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: true } },
    scales: {
      y: { beginAtZero: true }
    }
  }
});
</script>

<?php include "includes/footer.php"; ?>