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

// Vérifier que cette commande contient au moins une annonce de ce vendeur
$stmt = $pdo->prepare("
  SELECT COUNT(*)
  FROM order_details od
  JOIN annonce a ON a.id_annonce = od.id_annonce
  WHERE od.id_order = ? AND a.id_vendeur = ?
");
$stmt->execute([$id, $userId]);

if ((int)$stmt->fetchColumn() <= 0) {
  http_response_code(403);
  die("Accès refusé.");
}

// marquer commande comme vue par le vendeur
try {
  $stmt = $pdo->prepare("UPDATE orders SET seller_seen = 1 WHERE id_order = ?");
  $stmt->execute([$id]);
} catch (Exception $e) {}

// commande + acheteur + paiement
$stmt = $pdo->prepare("
  SELECT
    o.*,
    o.id_user AS acheteur_id,
    u.nom AS acheteur_nom,
    u.email AS acheteur_email,
    p.statut AS paiement_statut,
    p.methode AS paiement_methode
  FROM orders o
  JOIN user u ON u.id_user = o.id_user
  LEFT JOIN paiement p ON p.id_order = o.id_order
  WHERE o.id_order = ?
  LIMIT 1
");
$stmt->execute([$id]);
$o = $stmt->fetch();

if (!$o) {
  http_response_code(404);
  die("Commande introuvable.");
}

// détails uniquement des articles de ce vendeur
$stmt = $pdo->prepare("
  SELECT
    od.quantite,
    od.prix_unitaire,
    a.id_annonce,
    a.titre,
    a.cover_image
  FROM order_details od
  JOIN annonce a ON a.id_annonce = od.id_annonce
  WHERE od.id_order = ?
    AND a.id_vendeur = ?
");
$stmt->execute([$id, $userId]);
$details = $stmt->fetchAll();

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

function imgUrl($x) {
  $file = $x["cover_image"] ?? null;
  if (!$file) return "https://picsum.photos/seed/" . (int)$x["id_annonce"] . "/300/200";
  if (str_starts_with($file, "http")) return $file;
  return "uploads/" . $file;
}

$modeReception = strtoupper(trim((string)($o["mode_reception"] ?? "PICKUP")));
$statutLivraison = strtoupper(trim((string)($o["statut_livraison"] ?? "PREPARATION")));
$paiementStatut = strtoupper(trim((string)($o["paiement_statut"] ?? "EN_ATTENTE")));
$updatedAt = (string)($o["statut_livraison_updated_at"] ?? "");

$optionsLivraison = [];
if ($modeReception === "LIVRAISON") {
  $optionsLivraison = ["PREPARATION","EN_TRANSIT","ARRIVEE_VILLE","EN_LIVRAISON","LIVREE"];
} else {
  $optionsLivraison = ["PREPARATION","DISPONIBLE","TERMINEE"];
}

$badgeLivraison = "text-bg-secondary";
if ($statutLivraison === "EN_TRANSIT") $badgeLivraison = "text-bg-primary";
if ($statutLivraison === "ARRIVEE_VILLE") $badgeLivraison = "text-bg-info";
if ($statutLivraison === "EN_LIVRAISON") $badgeLivraison = "text-bg-warning";
if ($statutLivraison === "LIVREE" || $statutLivraison === "TERMINEE") $badgeLivraison = "text-bg-success";
if ($statutLivraison === "DISPONIBLE") $badgeLivraison = "text-bg-primary";

$payBadge = "text-bg-warning";
if ($paiementStatut === "ACCEPTE") $payBadge = "text-bg-success";
if ($paiementStatut === "REFUS") $payBadge = "text-bg-danger";
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<div class="container my-4" style="max-width: 1050px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h2 class="fw-bold mb-0"><i class="bi bi-bag-check"></i> Vente #<?php echo (int)$o["id_order"]; ?></h2>
      <div class="text-muted">Gestion vendeur de la commande</div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-dark" href="messages.php?to=<?php echo (int)$o["acheteur_id"]; ?>">
        <i class="bi bi-chat-dots"></i> Contacter acheteur
      </a>
      <a class="btn btn-outline-secondary" href="mes_ventes.php">Retour</a>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo e($success); ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo e($error); ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h5 class="fw-bold mb-3">Produits de cette vente</h5>

          <?php if (count($details) === 0): ?>
            <div class="alert alert-warning mb-0">Aucun produit trouvé pour cette vente.</div>
          <?php else: ?>
            <?php foreach ($details as $d): ?>
              <div class="d-flex gap-3 align-items-center py-2 border-bottom">
                <img src="<?php echo e(imgUrl($d)); ?>" alt=""
                     style="width:90px;height:70px;object-fit:cover;border-radius:12px;">
                <div class="flex-grow-1">
                  <div class="fw-semibold"><?php echo e($d["titre"]); ?></div>
                  <div class="text-muted small">
                    Quantité: <?php echo (int)$d["quantite"]; ?>
                  </div>
                </div>
                <div class="fw-bold">
                  <?php echo number_format((float)$d["prix_unitaire"] * (int)$d["quantite"], 2); ?> DH
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-5">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h5 class="fw-bold mb-3">Infos acheteur</h5>

          <div class="mb-2"><b>Nom :</b> <?php echo e($o["acheteur_nom"]); ?></div>
          <div class="mb-2"><b>Email :</b> <?php echo e($o["acheteur_email"]); ?></div>
          <div class="mb-2"><b>Téléphone :</b> <?php echo e($o["telephone_client"] ?? "—"); ?></div>
          <div class="mb-2"><b>Mode :</b> <?php echo $modeReception === "LIVRAISON" ? "Livraison" : "Main propre"; ?></div>

          <?php if ($modeReception === "LIVRAISON"): ?>
            <div class="mb-2"><b>Ville :</b> <?php echo e($o["ville_livraison"] ?? "—"); ?></div>
            <div class="mb-2"><b>Adresse :</b><br><?php echo nl2br(e($o["adresse_livraison"] ?? "—")); ?></div>
          <?php else: ?>
            <div class="mb-2"><b>Lieu de rencontre :</b><br><?php echo nl2br(e($o["adresse_livraison"] ?? "Non précisé")); ?></div>
          <?php endif; ?>

          <hr>

          <div class="mb-2">
            <b>Paiement :</b>
            <span class="badge <?php echo $payBadge; ?>"><?php echo e($paiementStatut); ?></span>
          </div>

          <div class="mb-2">
            <b>Statut actuel :</b>
            <span class="badge <?php echo $badgeLivraison; ?>"><?php echo e($statutLivraison); ?></span>
          </div>

          <div class="text-muted small mb-3">
            Dernière mise à jour :
            <?php echo $updatedAt !== "" ? e($updatedAt) : "—"; ?>
          </div>

          <?php if ($paiementStatut !== "ACCEPTE"): ?>
            <div class="alert alert-warning mb-0">
              Tu pourras gérer le suivi quand le paiement sera accepté.
            </div>
          <?php else: ?>
            <form action="actions/vendor_order_status.php" method="POST">
              <input type="hidden" name="id_order" value="<?php echo (int)$o["id_order"]; ?>">

              <div class="mb-3">
                <label class="form-label fw-semibold">Statut livraison</label>
                <select class="form-select" name="statut_livraison" required>
                  <?php foreach ($optionsLivraison as $opt): ?>
                    <option value="<?php echo e($opt); ?>" <?php echo $statutLivraison === $opt ? "selected" : ""; ?>>
                      <?php echo e($opt); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <button class="btn btn-dark w-100" type="submit">
                <i class="bi bi-save"></i> Mettre à jour le suivi
              </button>
            </form>

            
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include "includes/footer.php"; ?>