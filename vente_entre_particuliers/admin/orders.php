<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();
$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

$filter = $_GET["statut"] ?? "";
$allowed = ["", "EN_ATTENTE", "PAYE", "ANNULEE", "EXPEDIEE"];
if (!in_array($filter, $allowed, true)) $filter = "";

$where = [];
$params = [];
if ($filter !== "") {
  $where[] = "o.statut = ?";
  $params[] = $filter;
}

$sql = "
  SELECT o.*,
         u.nom AS client_nom, u.email AS client_email,
         p.statut AS pay_statut, p.methode AS pay_methode
  FROM orders o
  JOIN user u ON u.id_user = o.id_user
  LEFT JOIN paiement p ON p.id_order = o.id_order
";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY o.id_order DESC LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

include __DIR__ . "/../includes/header.php";
include __DIR__ . "/../includes/admin_navbar.php";

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
?>

<div class="container my-4" style="max-width: 1250px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h2 class="fw-bold mb-0">Commandes</h2>

    <form class="d-flex gap-2" method="GET">
      <select class="form-select" name="statut">
        <option value="" <?php echo $filter===""?'selected':''; ?>>Tous</option>
        <?php foreach (["EN_ATTENTE","PAYE","ANNULEE","EXPEDIEE"] as $st): ?>
          <option value="<?php echo $st; ?>" <?php echo $st===$filter?'selected':''; ?>>
            <?php echo $st; ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-dark">Filtrer</button>
    </form>
  </div>

  <?php if ($success): ?><div class="alert alert-success mt-3"><?php echo e($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger mt-3"><?php echo e($error); ?></div><?php endif; ?>

  <div class="card shadow-sm border-0 mt-3">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Date</th>
              <th>Client</th>
              <th>Total</th>
              <th>Statut</th>
              <th>Paiement</th>
              <th>Livraison</th>
              <th>Contact</th>
              <th style="width:210px;">Détails</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $o): ?>
            <?php
              $oid  = (int)$o["id_order"];
              $mode = $o["mode_reception"] ?? "PICKUP";
              $ville = $o["ville_livraison"] ?? null;
              $fee = (float)($o["frais_livraison"] ?? 0);
              $tel = $o["telephone_client"] ?? "";
              $adresse = $o["adresse_livraison"] ?? "";

              $paySt = $o["pay_statut"] ?? "—";
              $payBadge = "text-bg-warning";
              if ($paySt === "ACCEPTE") $payBadge = "text-bg-success";
              if ($paySt === "REFUS") $payBadge = "text-bg-danger";
              if ($paySt === "—") $payBadge = "text-bg-secondary";
            ?>
            <tr>
              <td>#<?php echo $oid; ?></td>
              <td><?php echo e($o["date_commande"] ?? ""); ?></td>
              <td>
                <div class="fw-semibold"><?php echo e($o["client_nom"] ?? ""); ?></div>
                <div class="text-muted small"><?php echo e($o["client_email"] ?? ""); ?></div>
              </td>
              <td><?php echo number_format((float)$o["total"], 2); ?> DH</td>
              <td><span class="badge text-bg-secondary"><?php echo e($o["statut"] ?? ""); ?></span></td>
              <td>
                <div class="text-muted small"><?php echo e($o["pay_methode"] ?? ""); ?></div>
                <span class="badge <?php echo $payBadge; ?>"><?php echo e($paySt); ?></span>
              </td>
              <td>
                <div class="fw-semibold"><?php echo ($mode === "LIVRAISON") ? "Livraison" : "Main propre"; ?></div>
                <?php if ($mode === "LIVRAISON"): ?>
                  <div class="text-muted small">
                    <?php echo e($ville ?: "—"); ?> · <?php echo number_format($fee, 2); ?> DH
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <div class="small"><b>Tél:</b> <?php echo e($tel ?: "—"); ?></div>
                <?php if ($mode === "LIVRAISON"): ?>
                  <div class="small text-muted"><?php echo e($adresse ?: "—"); ?></div>
                <?php endif; ?>
              </td>
              <td class="text-nowrap">
                <div class="d-flex gap-2 flex-wrap">
                  <a class="btn btn-outline-dark btn-sm"
                     href="order_view.php?id=<?php echo $oid; ?>">
                    Voir
                  </a>

                  <a class="btn btn-outline-secondary btn-sm"
                     href="../suivi_commande.php?id=<?php echo $oid; ?>&from=admin_orders"
                     target="_blank">
                    Suivi
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (count($rows) === 0): ?>
            <tr><td colspan="9" class="text-center text-muted p-4">Aucune commande.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <a class="btn btn-outline-dark mt-3" href="index.php">Retour Admin</a>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>