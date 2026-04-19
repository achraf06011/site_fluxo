<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = currentUserId();

function coverUrl($x) {
  $file = $x["cover_image"] ?? null;
  if (!$file) return "https://picsum.photos/seed/" . ((int)($x["id_annonce"] ?? 0) ?: rand(1,9999)) . "/300/200";
  if (str_starts_with($file, "http")) return $file;
  return "uploads/" . $file;
}
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

function shippingLabel(?string $st, string $modeReception = "PICKUP"): string {
  $st = strtoupper(trim((string)$st));
  $modeReception = strtoupper(trim((string)$modeReception));

  if ($modeReception === "LIVRAISON") {
    return match ($st) {
      "PREPARATION"   => "Préparation",
      "EN_TRANSIT"    => "En transit",
      "ARRIVEE_VILLE" => "Arrivée à ta ville",
      "EN_LIVRAISON"  => "En cours de livraison",
      "LIVREE"        => "Livrée",
      default         => "Préparation",
    };
  }

  return match ($st) {
    "PREPARATION" => "Préparation",
    "DISPONIBLE"  => "Disponible pour remise",
    "TERMINEE"    => "Remise effectuée",
    "LIVREE"      => "Remise effectuée",
    default       => "Préparation",
  };
}

function shippingBadge(?string $st): string {
  $st = strtoupper(trim((string)$st));
  return match ($st) {
    "LIVREE", "TERMINEE" => "text-bg-success",
    "EN_LIVRAISON", "EN_TRANSIT", "ARRIVEE_VILLE", "DISPONIBLE" => "text-bg-primary",
    default => "text-bg-warning",
  };
}

$stmt = $pdo->prepare("
  SELECT
    o.id_order,
    o.date_commande,
    o.statut,
    o.total,
    o.buyer_seen,
    o.statut_livraison,
    o.mode_reception,
    o.statut_livraison_updated_at,
    p.statut AS paiement_statut,
    p.methode
  FROM orders o
  LEFT JOIN paiement p ON p.id_order = o.id_order
  WHERE o.id_user = ?
  ORDER BY o.id_order DESC
  LIMIT 200
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll();

$orderIds = array_map(fn($r) => (int)$r["id_order"], $rows);
$summaryByOrder = [];

if (count($orderIds) > 0) {
  $placeholders = implode(",", array_fill(0, count($orderIds), "?"));

  $sqlFirst = "
    SELECT
      od.id_order,
      a.id_annonce,
      a.titre,
      a.cover_image,
      a.id_vendeur,
      u.nom AS vendeur_nom
    FROM order_details od
    JOIN annonce a ON a.id_annonce = od.id_annonce
    JOIN user u ON u.id_user = a.id_vendeur
    JOIN (
      SELECT id_order, MIN(id_detail) AS min_detail
      FROM order_details
      WHERE id_order IN ($placeholders)
      GROUP BY id_order
    ) x ON x.id_order = od.id_order AND x.min_detail = od.id_detail
  ";
  $stmt = $pdo->prepare($sqlFirst);
  $stmt->execute($orderIds);
  $firstItems = $stmt->fetchAll();

  foreach ($firstItems as $fi) {
    $oid = (int)$fi["id_order"];
    $summaryByOrder[$oid] = [
      "id_annonce" => (int)$fi["id_annonce"],
      "titre" => $fi["titre"] ?? "",
      "cover_image" => $fi["cover_image"] ?? null,
      "count_items" => 0,
      "id_vendeur" => (int)($fi["id_vendeur"] ?? 0),
      "vendeur_nom" => $fi["vendeur_nom"] ?? "",
    ];
  }

  $sqlCnt = "
    SELECT id_order, COUNT(*) AS cnt
    FROM order_details
    WHERE id_order IN ($placeholders)
    GROUP BY id_order
  ";
  $stmt = $pdo->prepare($sqlCnt);
  $stmt->execute($orderIds);
  $cntRows = $stmt->fetchAll();

  foreach ($cntRows as $c) {
    $oid = (int)$c["id_order"];
    if (!isset($summaryByOrder[$oid])) {
      $summaryByOrder[$oid] = [
        "id_annonce" => 0,
        "titre" => "",
        "cover_image" => null,
        "count_items" => 0,
        "id_vendeur" => 0,
        "vendeur_nom" => "",
      ];
    }
    $summaryByOrder[$oid]["count_items"] = (int)$c["cnt"];
  }
}

include "includes/header.php";
include "includes/navbar.php";
?>

<div class="container my-4" style="max-width: 1150px;">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h2 class="fw-bold mb-0"><i class="bi bi-receipt"></i> Mes commandes</h2>
    <a class="btn btn-outline-secondary" href="index.php">Retour annonces</a>
  </div>

  <?php if (count($rows) === 0): ?>
    <div class="alert alert-warning">Aucune commande pour le moment.</div>
    <a class="btn btn-dark" href="index.php">Voir les annonces</a>
  <?php else: ?>

    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:390px;">Produits</th>
                <th>ID</th>
                <th>Date</th>
                <th>Total</th>
                <th>Statut</th>
                <th>Paiement</th>
                <th>Livraison</th>
                <th style="width:230px;">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $o): ?>
              <?php
                $orderId = (int)$o["id_order"];

                $st = $o["statut"] ?? "EN_ATTENTE";
                $ps = $o["paiement_statut"] ?? "—";

                $badge = "text-bg-secondary";
                if ($st === "PAYE") $badge = "text-bg-success";
                if ($st === "ANNULEE") $badge = "text-bg-danger";
                if ($st === "EXPEDIEE") $badge = "text-bg-primary";

                $pbadge = "text-bg-warning";
                if ($ps === "ACCEPTE") $pbadge = "text-bg-success";
                if ($ps === "REFUS") $pbadge = "text-bg-danger";
                if ($ps === "—") $pbadge = "text-bg-secondary";

                $sum = $summaryByOrder[$orderId] ?? null;
                $thumbTitle = $sum["titre"] ?? "";
                $thumbCover = $sum ? coverUrl($sum) : "https://picsum.photos/seed/" . $orderId . "/300/200";
                $countItems = (int)($sum["count_items"] ?? 0);
                $vendeurId = (int)($sum["id_vendeur"] ?? 0);
                $vendeurNom = (string)($sum["vendeur_nom"] ?? "");

                $buyerSeen = (int)($o["buyer_seen"] ?? 1);
                $modeReception = strtoupper(trim((string)($o["mode_reception"] ?? "PICKUP")));
                $statutLivraison = (string)($o["statut_livraison"] ?? "");
                $shippingText = shippingLabel($statutLivraison, $modeReception);
                $shippingBadgeClass = shippingBadge($statutLivraison);
                $shippingUpdatedAt = $o["statut_livraison_updated_at"] ?? null;

                $hasUpdate = ($buyerSeen === 0);
              ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-3">
                    <div style="position:relative;">
                      <img
                        src="<?php echo e($thumbCover); ?>"
                        alt=""
                        style="width:74px;height:54px;object-fit:cover;border-radius:12px;border:1px solid #eee;"
                      >
                      <?php if ($hasUpdate): ?>
                        <span style="
                          position:absolute;
                          top:-4px;
                          right:-4px;
                          width:14px;
                          height:14px;
                          background:#dc3545;
                          border:2px solid #fff;
                          border-radius:50%;
                          display:inline-block;
                        "></span>
                      <?php endif; ?>
                    </div>

                    <div style="min-width:0;">
                      <div class="fw-semibold d-flex align-items-center gap-2 flex-wrap">
                        <span class="text-truncate" style="max-width:220px;">
                          <?php echo $thumbTitle ? e($thumbTitle) : "Produits de la commande"; ?>
                        </span>

                        <?php if ($hasUpdate): ?>
                          <span class="badge text-bg-danger">Mise à jour livraison</span>
                        <?php endif; ?>
                      </div>

                      <div class="text-muted small">
                        <?php if ($countItems > 1): ?>
                          + <?php echo ($countItems - 1); ?> autre(s) article(s)
                        <?php else: ?>
                          <?php echo ($countItems === 1) ? "1 article" : "—"; ?>
                        <?php endif; ?>
                      </div>

                      <?php if ($vendeurId > 0): ?>
                        <div class="small mt-1">
                          Vendeur :
                          <a class="text-decoration-none" href="vendeur.php?id=<?php echo $vendeurId; ?>">
                            <?php echo e($vendeurNom !== "" ? $vendeurNom : ("Vendeur #" . $vendeurId)); ?>
                          </a>
                        </div>
                      <?php endif; ?>

                      <div class="small mt-1">
                        <span class="badge <?php echo e($shippingBadgeClass); ?>">
                          <?php echo e($shippingText); ?>
                        </span>

                        <?php if (!empty($shippingUpdatedAt)): ?>
                          <span class="text-muted ms-2">
                            <?php echo e(substr((string)$shippingUpdatedAt, 0, 16)); ?>
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </td>

                <td>#<?php echo $orderId; ?></td>
                <td><?php echo e($o["date_commande"]); ?></td>
                <td><?php echo number_format((float)$o["total"], 2); ?> DH</td>

                <td>
                  <span class="badge <?php echo $badge; ?>">
                    <?php echo e($st); ?>
                  </span>
                </td>

                <td>
                  <span class="badge <?php echo $pbadge; ?>">
                    <?php echo e($ps); ?>
                  </span>
                  <div class="text-muted small"><?php echo e($o["methode"] ?? ""); ?></div>
                </td>

                <td>
                  <span class="badge <?php echo e($shippingBadgeClass); ?>">
                    <?php echo e($shippingText); ?>
                  </span>
                </td>

                <td>
                  <div class="d-grid gap-2">
                    <a class="btn btn-dark btn-sm"
                       href="checkout_success.php?id=<?php echo $orderId; ?>">
                      <i class="bi bi-eye"></i> Voir
                    </a>

                    <a class="btn btn-outline-dark btn-sm"
                       href="suivi_commande.php?id=<?php echo $orderId; ?>">
                      <i class="bi bi-truck"></i> Suivre
                    </a>
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

<?php include "includes/footer.php"; ?>