<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = currentUserId();
$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {
  http_response_code(400);
  die("Commande invalide.");
}

function e($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

$isAdminViewer = currentUserRole() === "ADMIN";
$from = trim((string)($_GET["from"] ?? ""));
$referer = (string)($_SERVER["HTTP_REFERER"] ?? "");

$isFromAdmin = $isAdminViewer && (
  $from === "admin_orders" ||
  str_contains($referer, "/admin/orders.php") ||
  str_contains($referer, "\\admin\\orders.php") ||
  str_contains($referer, "/admin/order_view.php") ||
  str_contains($referer, "\\admin\\order_view.php")
);

// Charger commande
if ($isFromAdmin) {
  $stmt = $pdo->prepare("SELECT * FROM orders WHERE id_order = ? LIMIT 1");
  $stmt->execute([$id]);
} else {
  $stmt = $pdo->prepare("SELECT * FROM orders WHERE id_order = ? AND id_user = ? LIMIT 1");
  $stmt->execute([$id, $userId]);
}
$o = $stmt->fetch();

if (!$o) {
  http_response_code(404);
  die("Commande introuvable.");
}

// Marquer vue acheteur seulement si c'est le propriétaire
if (!$isFromAdmin) {
  try {
    $stmtSeen = $pdo->prepare("UPDATE orders SET buyer_seen = 1 WHERE id_order = ? AND id_user = ?");
    $stmtSeen->execute([$id, $userId]);
  } catch (Exception $e) {}
}

// Charger paiement
$stmt = $pdo->prepare("SELECT * FROM paiement WHERE id_order = ? LIMIT 1");
$stmt->execute([$id]);
$pay = $stmt->fetch();

// Détails commande
$stmt = $pdo->prepare("
  SELECT
    od.quantite,
    od.prix_unitaire,
    a.titre,
    a.ville AS ville_vendeur,
    u.nom AS vendeur_nom
  FROM order_details od
  JOIN annonce a ON a.id_annonce = od.id_annonce
  JOIN user u ON u.id_user = a.id_vendeur
  WHERE od.id_order = ?
");
$stmt->execute([$id]);
$details = $stmt->fetchAll();

// Acheteur si admin
$buyer = null;
if ($isFromAdmin) {
  $stmt = $pdo->prepare("SELECT nom, email FROM user WHERE id_user = ? LIMIT 1");
  $stmt->execute([(int)$o["id_user"]]);
  $buyer = $stmt->fetch();
}

// Valeurs principales
$statutCommande   = (string)($o["statut"] ?? "EN_ATTENTE");
$statutPaiement   = (string)($pay["statut"] ?? "EN_ATTENTE");
$statutLivraison  = strtoupper(trim((string)($o["statut_livraison"] ?? "")));
$modeReception    = strtoupper(trim((string)($o["mode_reception"] ?? "PICKUP")));
$villeLivraison   = (string)($o["ville_livraison"] ?? "");
$fraisLivraison   = (float)($o["frais_livraison"] ?? 0);
$dateCommande     = (string)($o["date_commande"] ?? "");
$updatedAt        = (string)($o["statut_livraison_updated_at"] ?? "");
$telephoneClient  = (string)($o["telephone_client"] ?? "");
$adresseLivraison = (string)($o["adresse_livraison"] ?? "");

// Badges
$orderBadge = "text-bg-secondary";
if ($statutCommande === "PAYE") $orderBadge = "text-bg-success";
if ($statutCommande === "ANNULEE") $orderBadge = "text-bg-danger";
if ($statutCommande === "EXPEDIEE") $orderBadge = "text-bg-primary";

$payBadge = "text-bg-warning";
if ($statutPaiement === "ACCEPTE") $payBadge = "text-bg-success";
if ($statutPaiement === "REFUS") $payBadge = "text-bg-danger";

$isPaid = ($statutCommande === "PAYE" || $statutPaiement === "ACCEPTE");
$isLivraison = ($modeReception === "LIVRAISON");

// Lien retour
$backUrl = "mes_commandes.php";
if ($isFromAdmin) {
  $backUrl = "admin/orders.php";
} elseif (!empty($_SERVER["HTTP_REFERER"]) && str_contains($_SERVER["HTTP_REFERER"], "checkout_success.php")) {
  $backUrl = "checkout_success.php?id=" . (int)$id;
}

// Etapes
$steps = [];
$currentStep = 0;
$currentMessage = "Commande enregistrée.";

if ($isLivraison) {
  $steps = [
    1 => ["key" => "PREPARATION",   "label" => "Préparation vendeur"],
    2 => ["key" => "EN_TRANSIT",    "label" => "En transit"],
    3 => ["key" => "ARRIVEE_VILLE", "label" => "Arrivée à ta ville"],
    4 => ["key" => "EN_LIVRAISON",  "label" => "En cours de livraison"],
    5 => ["key" => "LIVREE",        "label" => "Livrée"],
  ];

  switch ($statutLivraison) {
    case "PREPARATION":
      $currentStep = 1;
      $currentMessage = "Le vendeur prépare actuellement la commande.";
      break;
    case "EN_TRANSIT":
      $currentStep = 2;
      $currentMessage = "La commande est en transit entre les villes.";
      break;
    case "ARRIVEE_VILLE":
      $currentStep = 3;
      $currentMessage = "La commande est arrivée dans la ville de livraison.";
      break;
    case "EN_LIVRAISON":
      $currentStep = 4;
      $currentMessage = "La commande est en cours de livraison.";
      break;
    case "LIVREE":
      $currentStep = 5;
      $currentMessage = "La commande a été livrée.";
      break;
    default:
      $currentStep = 1;
      $currentMessage = "Le vendeur prépare actuellement la commande.";
      break;
  }
} else {
  $steps = [
    1 => ["key" => "PREPARATION", "label" => "Préparation vendeur"],
    2 => ["key" => "DISPONIBLE",  "label" => "Disponible pour remise"],
    3 => ["key" => "TERMINEE",    "label" => "Remise effectuée"],
  ];

  switch ($statutLivraison) {
    case "PREPARATION":
      $currentStep = 1;
      $currentMessage = "Le vendeur prépare actuellement la commande.";
      break;
    case "DISPONIBLE":
      $currentStep = 2;
      $currentMessage = "La commande est prête pour la remise en main propre.";
      break;
    case "TERMINEE":
    case "MAIN_PROPRE":
    case "LIVREE":
      $currentStep = 3;
      $currentMessage = "La remise en main propre a été effectuée.";
      break;
    default:
      $currentStep = 1;
      $currentMessage = "Le vendeur prépare actuellement la commande.";
      break;
  }
}
?>

<?php include "includes/header.php"; ?>
<?php
if ($isFromAdmin) {
  include "includes/admin_navbar.php";
} else {
  include "includes/navbar.php";
}
?>

<div class="container my-5" style="max-width: 980px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="fw-bold mb-0">
      <i class="bi bi-truck"></i> Suivi commande #<?php echo (int)$o["id_order"]; ?>
    </h2>
    <a class="btn btn-outline-secondary" href="<?php echo e($backUrl); ?>">
      Retour
    </a>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body p-4">

      <?php if ($isFromAdmin && $buyer): ?>
        <div class="alert alert-info">
          <b>Client :</b> <?php echo e($buyer["nom"] ?? ""); ?>
          <?php if (!empty($buyer["email"])): ?>
            · <?php echo e($buyer["email"]); ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-12 col-md-3">
          <div class="p-3 border rounded h-100">
            <div class="text-muted small">Statut commande</div>
            <div class="mt-1">
              <span class="badge <?php echo $orderBadge; ?>"><?php echo e($statutCommande); ?></span>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-3">
          <div class="p-3 border rounded h-100">
            <div class="text-muted small">Paiement</div>
            <div class="mt-1">
              <span class="badge <?php echo $payBadge; ?>"><?php echo e($statutPaiement); ?></span>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-3">
          <div class="p-3 border rounded h-100">
            <div class="text-muted small">Date commande</div>
            <div class="fw-semibold mt-1"><?php echo e($dateCommande); ?></div>
          </div>
        </div>

        <div class="col-12 col-md-3">
          <div class="p-3 border rounded h-100">
            <div class="text-muted small">Dernière mise à jour</div>
            <div class="fw-semibold mt-1"><?php echo $updatedAt !== "" ? e($updatedAt) : "—"; ?></div>
          </div>
        </div>
      </div>

      <?php if (!$isPaid): ?>
        <div class="alert alert-warning mb-0">
          Le suivi détaillé est disponible seulement après paiement accepté.
        </div>
      <?php else: ?>

        <div class="p-3 border rounded mb-4">
          <div class="fw-bold mb-1">Statut actuel</div>
          <div class="mb-2">
            <span class="badge text-bg-primary"><?php echo e($statutLivraison ?: "PREPARATION"); ?></span>
          </div>
          <div class="text-muted"><?php echo e($currentMessage); ?></div>

          <hr>

          <div class="row g-3">
            <div class="col-12 col-md-4">
              <div class="text-muted small">Mode réception</div>
              <div class="fw-semibold">
                <?php echo $isLivraison ? "Livraison" : "Main propre"; ?>
              </div>
            </div>

            <div class="col-12 col-md-4">
              <div class="text-muted small">Téléphone</div>
              <div class="fw-semibold"><?php echo $telephoneClient !== "" ? e($telephoneClient) : "—"; ?></div>
            </div>

            <?php if ($isLivraison): ?>
              <div class="col-12 col-md-4">
                <div class="text-muted small">Ville livraison</div>
                <div class="fw-semibold"><?php echo e($villeLivraison ?: "—"); ?></div>
              </div>

              <div class="col-12">
                <div class="text-muted small">Adresse livraison</div>
                <div class="fw-semibold"><?php echo $adresseLivraison !== "" ? nl2br(e($adresseLivraison)) : "—"; ?></div>
              </div>

              <div class="col-12 col-md-4">
                <div class="text-muted small">Frais livraison</div>
                <div class="fw-semibold"><?php echo number_format($fraisLivraison, 2); ?> DH</div>
              </div>
            <?php else: ?>
              <div class="col-12">
                <div class="text-muted small">Lieu de rencontre</div>
                <div class="fw-semibold"><?php echo $adresseLivraison !== "" ? nl2br(e($adresseLivraison)) : "Non précisé"; ?></div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <h4 class="fw-bold mb-3">Étapes</h4>

        <div class="list-group mb-4">
          <?php foreach ($steps as $index => $step): ?>
            <?php
              $done = ($index < $currentStep);
              $active = ($index === $currentStep);

              $itemClass = "";
              $badgeClass = "text-bg-secondary";
              $badgeText = "En attente";

              if ($done) {
                $itemClass = "list-group-item-success";
                $badgeClass = "text-bg-success";
                $badgeText = "Terminée";
              }

              if ($active) {
                $itemClass = "list-group-item-primary";
                $badgeClass = "text-bg-primary";
                $badgeText = "En cours";
              }
            ?>
            <div class="list-group-item <?php echo $itemClass; ?> d-flex justify-content-between align-items-center">
              <div class="fw-semibold"><?php echo e($step["label"]); ?></div>
              <span class="badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (count($details) > 0): ?>
          <h5 class="fw-bold mb-3">Produits de la commande</h5>
          <div class="list-group">
            <?php foreach ($details as $d): ?>
              <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-semibold"><?php echo e($d["titre"]); ?></div>
                  <div class="text-muted small">
                    Quantité: <?php echo (int)$d["quantite"]; ?>
                    <?php if (!empty($d["vendeur_nom"])): ?>
                      · Vendeur: <?php echo e($d["vendeur_nom"]); ?>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="fw-semibold">
                  <?php echo number_format((float)$d["prix_unitaire"] * (int)$d["quantite"], 2); ?> DH
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>

    </div>
  </div>
</div>

<?php include "includes/footer.php"; ?>