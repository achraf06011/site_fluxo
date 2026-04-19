<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = currentUserId();

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

$statut = trim($_GET["statut"] ?? "");
$statutLivraison = trim($_GET["statut_livraison"] ?? "");

$where = ["a.id_vendeur = ?"];
$params = [$userId];

if ($statut !== "") {
  $where[] = "o.statut = ?";
  $params[] = $statut;
}

if ($statutLivraison !== "") {
  $where[] = "COALESCE(o.statut_livraison, 'PREPARATION') = ?";
  $params[] = $statutLivraison;
}

// marquer les ventes vues
try {
  $stmtSeen = $pdo->prepare("
    UPDATE orders o
    JOIN order_details od ON od.id_order = o.id_order
    JOIN annonce a ON a.id_annonce = od.id_annonce
    LEFT JOIN paiement p ON p.id_order = o.id_order
    SET o.seller_seen = 1
    WHERE a.id_vendeur = ?
      AND COALESCE(p.statut, 'EN_ATTENTE') = 'ACCEPTE'
  ");
  $stmtSeen->execute([$userId]);
} catch (Exception $e) {}

$sql = "
  SELECT
    o.id_order,
    o.date_commande,
    o.statut,
    o.mode_reception,
    o.ville_livraison,
    o.statut_livraison,
    o.statut_livraison_updated_at,
    u.nom AS acheteur_nom,
    u.email AS acheteur_email,
    COALESCE(p.statut, 'EN_ATTENTE') AS paiement_statut,
    COALESCE(p.methode, 'STRIPE') AS paiement_methode,
    SUM(od.quantite * od.prix_unitaire) AS vendeur_total,
    SUM(od.quantite) AS nb_articles
  FROM orders o
  JOIN user u ON u.id_user = o.id_user
  JOIN order_details od ON od.id_order = o.id_order
  JOIN annonce a ON a.id_annonce = od.id_annonce
  LEFT JOIN paiement p ON p.id_order = o.id_order
  WHERE " . implode(" AND ", $where) . "
  GROUP BY o.id_order, o.date_commande, o.statut, o.mode_reception, o.ville_livraison, o.statut_livraison, o.statut_livraison_updated_at, u.nom, u.email, p.statut, p.methode
  ORDER BY o.id_order DESC
  LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

function badgeOrder($st) {
  $st = $st ?: "EN_ATTENTE";
  return match ($st) {
    "PAYE" => "text-bg-success",
    "ANNULEE" => "text-bg-danger",
    "EXPEDIEE" => "text-bg-primary",
    default => "text-bg-secondary",
  };
}
function badgePay($st) {
  $st = $st ?: "EN_ATTENTE";
  return match ($st) {
    "ACCEPTE" => "text-bg-success",
    "REFUS" => "text-bg-danger",
    default => "text-bg-warning",
  };
}
function badgeLivraison($st) {
  $st = $st ?: "PREPARATION";
  return match ($st) {
    "PREPARATION" => "text-bg-secondary",
    "EN_TRANSIT" => "text-bg-primary",
    "ARRIVEE_VILLE" => "text-bg-info",
    "EN_LIVRAISON" => "text-bg-warning",
    "LIVREE" => "text-bg-success",
    "DISPONIBLE" => "text-bg-primary",
    "TERMINEE" => "text-bg-success",
    default => "text-bg-secondary",
  };
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<div class="container my-4" style="max-width: 1200px;">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h2 class="fw-bold mb-0"><i class="bi bi-bag-check"></i> Mes ventes</h2>
      <div class="text-muted">Commandes où tes annonces ont été achetées.</div>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
      <form class="row g-2 align-items-end" method="GET">
        <div class="col-12 col-md-3">
          <label class="form-label">Statut commande</label>
          <select class="form-select" name="statut">
            <option value="" <?php echo $statut==="" ? "selected" : ""; ?>>Tous</option>
            <option value="EN_ATTENTE" <?php echo $statut==="EN_ATTENTE" ? "selected" : ""; ?>>EN_ATTENTE</option>
            <option value="PAYE" <?php echo $statut==="PAYE" ? "selected" : ""; ?>>PAYE</option>
            <option value="ANNULEE" <?php echo $statut==="ANNULEE" ? "selected" : ""; ?>>ANNULEE</option>
            <option value="EXPEDIEE" <?php echo $statut==="EXPEDIEE" ? "selected" : ""; ?>>EXPEDIEE</option>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label">Statut livraison</label>
          <select class="form-select" name="statut_livraison">
            <option value="" <?php echo $statutLivraison==="" ? "selected" : ""; ?>>Tous</option>
            <option value="PREPARATION" <?php echo $statutLivraison==="PREPARATION" ? "selected" : ""; ?>>PREPARATION</option>
            <option value="EN_TRANSIT" <?php echo $statutLivraison==="EN_TRANSIT" ? "selected" : ""; ?>>EN_TRANSIT</option>
            <option value="ARRIVEE_VILLE" <?php echo $statutLivraison==="ARRIVEE_VILLE" ? "selected" : ""; ?>>ARRIVEE_VILLE</option>
            <option value="EN_LIVRAISON" <?php echo $statutLivraison==="EN_LIVRAISON" ? "selected" : ""; ?>>EN_LIVRAISON</option>
            <option value="LIVREE" <?php echo $statutLivraison==="LIVREE" ? "selected" : ""; ?>>LIVREE</option>
            <option value="DISPONIBLE" <?php echo $statutLivraison==="DISPONIBLE" ? "selected" : ""; ?>>DISPONIBLE</option>
            <option value="TERMINEE" <?php echo $statutLivraison==="TERMINEE" ? "selected" : ""; ?>>TERMINEE</option>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <button class="btn btn-dark w-100" type="submit">Appliquer</button>
        </div>

        <div class="col-12 col-md-3">
          <a class="btn btn-outline-secondary w-100" href="mes_ventes.php">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body p-0">
      <?php if (count($rows) === 0): ?>
        <div class="p-4">
          <div class="alert alert-warning mb-0">Aucune vente pour le moment.</div>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>#Commande</th>
                <th>Date</th>
                <th>Acheteur</th>
                <th>Statut</th>
                <th>Paiement</th>
                <th>Livraison</th>
                <th>Maj livraison</th>
                <th>Articles</th>
                <th>Ton total</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td class="fw-semibold">#<?php echo (int)$r["id_order"]; ?></td>
                  <td><?php echo htmlspecialchars($r["date_commande"]); ?></td>
                  <td>
                    <div class="fw-semibold"><?php echo htmlspecialchars($r["acheteur_nom"]); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars($r["acheteur_email"]); ?></div>
                  </td>
                  <td><span class="badge <?php echo badgeOrder($r["statut"]); ?>"><?php echo htmlspecialchars($r["statut"]); ?></span></td>
                  <td>
                    <span class="badge <?php echo badgePay($r["paiement_statut"]); ?>"><?php echo htmlspecialchars($r["paiement_statut"]); ?></span>
                    <span class="text-muted small ms-2"><?php echo htmlspecialchars($r["paiement_methode"]); ?></span>
                  </td>
                  <td>
                    <span class="badge <?php echo badgeLivraison($r["statut_livraison"]); ?>">
                      <?php echo htmlspecialchars($r["statut_livraison"] ?: "PREPARATION"); ?>
                    </span>
                  </td>
                  <td class="small text-muted">
                    <?php echo htmlspecialchars($r["statut_livraison_updated_at"] ?: "—"); ?>
                  </td>
                  <td><?php echo (int)$r["nb_articles"]; ?></td>
                  <td class="fw-bold"><?php echo number_format((float)$r["vendeur_total"], 2); ?> DH</td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="vente.php?id=<?php echo (int)$r["id_order"]; ?>">Voir</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include "includes/footer.php"; ?>