<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  die("Commande invalide.");
}

$userId = currentUserId();

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id_order = ? AND id_user = ? LIMIT 1");
$stmt->execute([$id, $userId]);
$o = $stmt->fetch();
if (!$o) {
  http_response_code(404);
  die("Commande introuvable.");
}

$stmt = $pdo->prepare("
  SELECT 
    od.quantite, od.prix_unitaire,
    a.id_annonce, a.titre,
    a.id_vendeur, u.nom AS vendeur_nom
  FROM order_details od
  JOIN annonce a ON a.id_annonce = od.id_annonce
  JOIN user u ON u.id_user = a.id_vendeur
  WHERE od.id_order = ?
");
$stmt->execute([$id]);
$details = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM paiement WHERE id_order = ? LIMIT 1");
$stmt->execute([$id]);
$pay = $stmt->fetch();

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

function e($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

$st = $o["statut"] ?? "EN_ATTENTE";
$orderBadge = "text-bg-secondary";
if ($st === "PAYE") $orderBadge = "text-bg-success";
if ($st === "ANNULEE") $orderBadge = "text-bg-danger";
if ($st === "EXPEDIEE") $orderBadge = "text-bg-primary";

$ps = $pay["statut"] ?? "EN_ATTENTE";
$payBadge = "text-bg-warning";
if ($ps === "ACCEPTE") $payBadge = "text-bg-success";
if ($ps === "REFUS") $payBadge = "text-bg-danger";

$modeReception    = strtoupper(trim((string)($o["mode_reception"] ?? "PICKUP")));
$villeLivraison   = $o["ville_livraison"] ?? null;
$fraisLivraison   = (float)($o["frais_livraison"] ?? 0);
$telephoneClient  = $o["telephone_client"] ?? "";
$adresseLivraison = $o["adresse_livraison"] ?? "";

$vendors = [];
foreach ($details as $d) {
  $vid = (int)$d["id_vendeur"];
  if (!isset($vendors[$vid])) {
    $vendors[$vid] = [
      "id_seller" => $vid,
      "vendeur_nom" => $d["vendeur_nom"],
      "annonces" => [],
    ];
  }
  $vendors[$vid]["annonces"][] = [
    "id_annonce" => (int)$d["id_annonce"],
    "titre" => $d["titre"],
  ];
}

$reviewedSellers = [];
try {
  $stmt = $pdo->prepare("
    SELECT id_seller
    FROM review
    WHERE id_order = ? AND id_user = ?
  ");
  $stmt->execute([$id, $userId]);
  $rows = $stmt->fetchAll();

  foreach ($rows as $r) {
    $reviewedSellers[(int)$r["id_seller"]] = true;
  }
} catch (Exception $e) {}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<div class="container my-5" style="max-width: 920px;">

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo e($success); ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo e($error); ?></div>
  <?php endif; ?>

  <div class="card shadow-sm border-0">
    <div class="card-body p-4">

      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h2 class="fw-bold mb-1"><i class="bi bi-check-circle"></i> Commande confirmée</h2>
          <div class="text-muted">
            Commande #<?php echo (int)$o["id_order"]; ?> ·
            <span class="badge <?php echo $orderBadge; ?>"><?php echo e($st); ?></span>
          </div>
        </div>

        <div class="text-end">
          <div class="text-muted">Total</div>
          <div class="fs-3 fw-bold"><?php echo number_format((float)$o["total"], 2); ?> DH</div>
        </div>
      </div>

      <hr class="my-4">

      <div class="mb-4">
        <div class="p-3 border rounded">
          <div class="row g-3">
            <div class="col-12 col-md-4">
              <div class="text-muted small">Mode réception</div>
              <div class="fw-semibold">
                <?php echo ($modeReception === "LIVRAISON") ? "Livraison" : "Main propre"; ?>
              </div>
            </div>

            <div class="col-12 col-md-4">
              <div class="text-muted small">Téléphone</div>
              <div class="fw-semibold"><?php echo $telephoneClient !== "" ? e($telephoneClient) : "—"; ?></div>
            </div>

            <?php if ($modeReception === "LIVRAISON"): ?>
              <div class="col-12 col-md-4">
                <div class="text-muted small">Ville livraison</div>
                <div class="fw-semibold"><?php echo e($villeLivraison ?: "—"); ?></div>
              </div>

              <div class="col-12">
                <div class="text-muted small">Adresse de livraison</div>
                <div class="fw-semibold"><?php echo $adresseLivraison !== "" ? nl2br(e($adresseLivraison)) : "—"; ?></div>
              </div>

              <div class="col-12 col-md-4">
                <div class="text-muted small">Frais livraison</div>
                <div class="fw-semibold"><?php echo number_format($fraisLivraison, 2); ?> DH</div>
              </div>

              <div class="col-12">
                <a class="btn btn-outline-dark w-100 mt-2" href="suivi_commande.php?id=<?php echo (int)$o["id_order"]; ?>">
                  <i class="bi bi-truck"></i> Suivi commande
                </a>
              </div>
            <?php else: ?>
              <div class="col-12">
                <div class="text-muted small">Lieu de rencontre / adresse</div>
                <div class="fw-semibold">
                  <?php echo $adresseLivraison !== "" ? nl2br(e($adresseLivraison)) : "Non précisé"; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-lg-7">
          <h5 class="fw-bold mb-3">Détails</h5>

          <?php if (count($details) === 0): ?>
            <div class="alert alert-warning mb-0">Aucun détail trouvé pour cette commande.</div>
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
                    <div class="fw-semibold"><?php echo e($d["titre"]); ?></div>
                    <div class="text-muted small">
                      Quantité: <?php echo $q; ?> ·
                      Vendeur: <?php echo e($d["vendeur_nom"]); ?>
                    </div>
                  </div>
                  <div class="fw-semibold"><?php echo number_format($line, 2); ?> DH</div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="col-12 col-lg-5">
          <h5 class="fw-bold mb-3">Paiement</h5>

          <div class="p-3 border rounded">
            <div>Méthode : <b><?php echo e($pay["methode"] ?? "STRIPE"); ?></b></div>
            <div class="mt-1">
              Statut :
              <span class="badge <?php echo $payBadge; ?>">
                <?php echo e($ps); ?>
              </span>
            </div>
          </div>

          <?php if (($o["statut"] ?? "EN_ATTENTE") === "PAYE"): ?>
            <div class="mt-4">
              <h6 class="fw-bold mb-2"><i class="bi bi-star"></i> Laisser un avis</h6>

              <?php if (count($vendors) === 0): ?>
                <div class="text-muted small">Aucun vendeur trouvé.</div>
              <?php else: ?>
                <div class="list-group">
                  <?php foreach ($vendors as $v): ?>
                    <?php $already = !empty($reviewedSellers[(int)$v["id_seller"]]); ?>
                    <div class="list-group-item">
                      <div class="d-flex align-items-center justify-content-between gap-2">
                        <div>
                          <div class="fw-semibold"><?php echo e($v["vendeur_nom"]); ?></div>
                          <div class="text-muted small">
                            <?php
                              $titles = array_map(fn($x) => $x["titre"], $v["annonces"]);
                              echo e(implode(" · ", $titles));
                            ?>
                          </div>
                        </div>

                        <?php if ($already): ?>
                          <span class="badge text-bg-success">Déjà noté</span>
                        <?php else: ?>
                          <a class="btn btn-sm btn-outline-dark"
                             href="laisser_avis.php?order=<?php echo (int)$o["id_order"]; ?>&seller=<?php echo (int)$v["id_seller"]; ?>">
                            Noter
                          </a>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="text-muted small mt-2">
                  (Avis possible seulement après paiement, 1 avis par vendeur et par commande.)
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="d-grid gap-2 mt-4">
            <a class="btn btn-dark" href="index.php"><i class="bi bi-grid"></i> Retour annonces</a>
            <a class="btn btn-outline-secondary" href="profil.php"><i class="bi bi-person"></i> Mon compte</a>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include "includes/footer.php"; ?>