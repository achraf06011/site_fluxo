<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();
$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

// Stats rapides
$stats = [
  "users" => 0,
  "annonces_wait" => 0,
  "annonces_active" => 0,
  "orders" => 0,
];

try {
  $stats["users"] = (int)$pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
  $stats["annonces_wait"] = (int)$pdo->query("SELECT COUNT(*) FROM annonce WHERE statut='EN_ATTENTE_VALIDATION'")->fetchColumn();
  $stats["annonces_active"] = (int)$pdo->query("SELECT COUNT(*) FROM annonce WHERE statut='ACTIVE'")->fetchColumn();
  $stats["orders"] = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
} catch (Exception $e) {}

include __DIR__ . "/../includes/header.php";
include __DIR__ . "/../includes/admin_navbar.php";
?>

<div class="container my-4" style="max-width: 1100px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h2 class="fw-bold mb-0">Admin Dashboard</h2>
    <span class="badge text-bg-danger">ADMIN</span>
  </div>

  <?php if ($success): ?><div class="alert alert-success mt-3"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="row g-3 mt-1">
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Utilisateurs</div>
          <div class="fs-3 fw-bold"><?php echo (int)$stats["users"]; ?></div>
          <a class="btn btn-outline-dark btn-sm mt-2" href="users.php">Gérer</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Annonces en attente</div>
          <div class="fs-3 fw-bold"><?php echo (int)$stats["annonces_wait"]; ?></div>
          <a class="btn btn-outline-dark btn-sm mt-2" href="annonces.php?statut=EN_ATTENTE_VALIDATION">Valider</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Annonces actives</div>
          <div class="fs-3 fw-bold"><?php echo (int)$stats["annonces_active"]; ?></div>
          <a class="btn btn-outline-dark btn-sm mt-2" href="annonces.php?statut=ACTIVE">Voir</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Commandes</div>
          <div class="fs-3 fw-bold"><?php echo (int)$stats["orders"]; ?></div>
          <a class="btn btn-outline-dark btn-sm mt-2" href="orders.php">Voir</a>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm border-0 mt-4">
    <div class="card-body">
      <h5 class="fw-bold mb-2">Actions rapides</h5>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-dark" href="annonces.php?statut=EN_ATTENTE_VALIDATION"><i class="bi bi-check2-circle"></i> Valider annonces</a>
        <a class="btn btn-outline-dark" href="users.php"><i class="bi bi-people"></i> Gérer users</a>
        <a class="btn btn-outline-dark" href="orders.php"><i class="bi bi-receipt"></i> Voir commandes</a>
        <a class="btn btn-outline-secondary" href="../index.php"><i class="bi bi-arrow-left"></i> Retour site</a>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>