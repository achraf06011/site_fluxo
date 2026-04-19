<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = currentUserId();
$orderId = (int)($_GET["order"] ?? 0);
$sellerId = (int)($_GET["seller"] ?? 0);

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

if ($orderId <= 0 || $sellerId <= 0) {
  http_response_code(400);
  die("Paramètres invalides.");
}

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// 1) Vérifier que la commande appartient à l'utilisateur et est PAYE
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id_order = ? AND id_user = ? LIMIT 1");
$stmt->execute([$orderId, $userId]);
$o = $stmt->fetch();

if (!$o) {
  die("Commande introuvable.");
}

if (($o["statut"] ?? "") !== "PAYE") {
  die("Tu peux laisser un avis seulement après paiement.");
}

// 2) Vérifier que cette commande contient bien une annonce de ce vendeur
$stmt = $pdo->prepare("
  SELECT COUNT(*) AS c
  FROM order_details od
  JOIN annonce a ON a.id_annonce = od.id_annonce
  WHERE od.id_order = ? AND a.id_vendeur = ?
");
$stmt->execute([$orderId, $sellerId]);
$has = (int)($stmt->fetch()["c"] ?? 0);

if ($has <= 0) {
  die("Ce vendeur n'est pas lié à cette commande.");
}

// 3) Vérifier si CET utilisateur a déjà noté ce vendeur pour CETTE commande
$stmt = $pdo->prepare("
  SELECT id_review
  FROM review
  WHERE id_order = ? AND id_seller = ? AND id_user = ?
  LIMIT 1
");
$stmt->execute([$orderId, $sellerId, $userId]);
$already = $stmt->fetch();

if ($already) {
  $_SESSION["flash_error"] = "Tu as déjà noté ce vendeur pour cette commande.";
  header("Location: checkout_success.php?id=" . $orderId);
  exit;
}

// 4) Charger vendeur
$stmt = $pdo->prepare("SELECT id_user, nom, email FROM user WHERE id_user = ? LIMIT 1");
$stmt->execute([$sellerId]);
$seller = $stmt->fetch();

if (!$seller) {
  die("Vendeur introuvable.");
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<div class="container my-4" style="max-width: 860px;">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h2 class="fw-bold mb-0"><i class="bi bi-star"></i> Laisser un avis</h2>
      <div class="text-muted">
        Commande #<?php echo (int)$orderId; ?> · Vendeur: <b><?php echo e($seller["nom"]); ?></b>
      </div>
    </div>

    <a class="btn btn-outline-secondary" href="checkout_success.php?id=<?php echo (int)$orderId; ?>">
      <i class="bi bi-arrow-left"></i> Retour commande
    </a>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo e($success); ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo e($error); ?></div>
  <?php endif; ?>

  <div class="card shadow-sm border-0">
    <div class="card-body p-4">
      <form action="actions/avis_action.php" method="POST">
        <input type="hidden" name="id_order" value="<?php echo (int)$orderId; ?>">
        <input type="hidden" name="id_seller" value="<?php echo (int)$sellerId; ?>">

        <div class="mb-3">
          <label class="form-label fw-semibold">Note (1 à 5)</label>
          <select class="form-select" name="rating" required>
            <option value="">Choisir...</option>
            <option value="5">5 - Excellent</option>
            <option value="4">4 - Très bien</option>
            <option value="3">3 - Bien</option>
            <option value="2">2 - Moyen</option>
            <option value="1">1 - Mauvais</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Commentaire</label>
          <textarea class="form-control" name="comment" rows="4" maxlength="1000" placeholder="Optionnel (qualité, communication, livraison...)"></textarea>
        </div>

        <button class="btn btn-dark btn-lg w-100" type="submit">
          <i class="bi bi-send"></i> Envoyer l’avis
        </button>

        <div class="text-muted small mt-2">
          Avis possible uniquement si la commande est payée.
        </div>
      </form>
    </div>
  </div>
</div>

<?php include "includes/footer.php"; ?>