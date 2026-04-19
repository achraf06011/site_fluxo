<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) die("Commande invalide.");

$userId = currentUserId();

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id_order = ? AND id_user = ? LIMIT 1");
$stmt->execute([$id, $userId]);
$o = $stmt->fetch();
if (!$o) die("Commande introuvable.");

$stmt = $pdo->prepare("
  SELECT od.quantite, od.prix_unitaire, a.id_annonce, a.titre
  FROM order_details od
  JOIN annonce a ON a.id_annonce = od.id_annonce
  WHERE od.id_order = ?
");
$stmt->execute([$id]);
$details = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM paiement WHERE id_order = ? LIMIT 1");
$stmt->execute([$id]);
$pay = $stmt->fetch();

$st = $o["statut"] ?? "EN_ATTENTE";
$orderBadge = "text-bg-secondary";
if ($st === "PAYE") $orderBadge = "text-bg-success";
if ($st === "ANNULEE") $orderBadge = "text-bg-danger";
if ($st === "EXPEDIEE") $orderBadge = "text-bg-primary";

$ps = $pay["statut"] ?? "EN_ATTENTE";
$payBadge = "text-bg-warning";
if ($ps === "ACCEPTE") $payBadge = "text-bg-success";
if ($ps === "REFUS") $payBadge = "text-bg-danger";
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<div class="container my-4" style="max-width: 980px;">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h2 class="fw-bold mb-0"><i class="bi bi-receipt-cutoff"></i> Détail commande</h2>
      <div class="text-muted">Commande #<?php echo (int)$o["id_order"]; ?></div>
    </div>
    <a class="btn btn-outline-secondary" href="mes_commandes.php"><i class="bi bi-arrow-left"></i> Mes commandes</a>
  </div>

  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <div class="text-muted mb-1">Statut commande</div>
        <span class="badge <?php echo $orderBadge; ?>"><?php echo htmlspecialchars($st); ?></span>
        <div class="text-muted mt-2">Date : <?php echo htmlspecialchars($o["date_commande"]); ?></div>
      </div>
      <div class="text-end">
        <div class="text-muted">Total</div>
        <div class="fs-3 fw-bold"><?php echo number_format((float)$o["total"], 2); ?> DH</div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h5 class="fw-bold mb-3">Articles</h5>
          <?php if (count($details) === 0): ?>
            <div class="alert alert-warning mb-0">Aucun article trouvé.</div>
          <?php else: ?>
            <div class="list-group">
              <?php foreach ($details as $d): ?>
                <?php
                  $q = (int)$d["quantite"];
                  $pu = (float)$d["prix_unitaire"];
                  $line = $q * $pu;
                ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($d["titre"]); ?></div>
                    <div class="text-muted small">Quantité: <?php echo $q; ?></div>
                  </div>
                  <div class="text-end">
                    <div class="fw-semibold"><?php echo number_format($line, 2); ?> DH</div>
                    <a class="small text-decoration-none" href="annonce.php?id=<?php echo (int)$d["id_annonce"]; ?>">Voir annonce</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-5">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h5 class="fw-bold mb-3">Paiement</h5>
          <div class="p-3 border rounded">
            <div>Méthode : <b><?php echo htmlspecialchars($pay["methode"] ?? "STRIPE"); ?></b></div>
            <div class="mt-2">
              Statut :
              <span class="badge <?php echo $payBadge; ?>"><?php echo htmlspecialchars($ps); ?></span>
            </div>
          </div>

          <div class="d-grid gap-2 mt-3">
            <a class="btn btn-dark" href="checkout_success.php?id=<?php echo (int)$o["id_order"]; ?>">
              <i class="bi bi-check2-square"></i> Voir confirmation
            </a>
            <a class="btn btn-outline-secondary" href="index.php">
              <i class="bi bi-grid"></i> Retour annonces
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<?php include "includes/footer.php"; ?>