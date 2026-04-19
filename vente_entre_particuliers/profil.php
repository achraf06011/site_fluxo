<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = currentUserId();

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// Charger user
$stmt = $pdo->prepare("SELECT id_user, nom, email, date_inscription, role FROM user WHERE id_user = ? LIMIT 1");
$stmt->execute([$userId]);
$u = $stmt->fetch();
if (!$u) {
  http_response_code(404);
  die("Utilisateur introuvable.");
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<div class="container my-4" style="max-width: 900px;">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h2 class="fw-bold mb-0"><i class="bi bi-person"></i> Mon profil</h2>
    <a class="btn btn-outline-secondary" href="index.php">Retour annonces</a>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo e($success); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo e($error); ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-lg-5">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h5 class="fw-bold mb-3">Informations</h5>

          <div class="mb-2"><span class="text-muted">Nom:</span> <b><?php echo e($u["nom"]); ?></b></div>
          <div class="mb-2"><span class="text-muted">Email:</span> <b><?php echo e($u["email"]); ?></b></div>
          <div class="mb-2"><span class="text-muted">Rôle:</span> <span class="badge text-bg-dark"><?php echo e($u["role"]); ?></span></div>
          <div class="mb-0"><span class="text-muted">Inscription:</span> <b><?php echo e($u["date_inscription"]); ?></b></div>

          <hr>

          <div class="d-grid gap-2">
            <a class="btn btn-outline-dark" href="mes_commandes.php">
              <i class="bi bi-receipt"></i> Mes commandes
            </a>

            <!-- ✅ Nouveau -->
            <a class="btn btn-dark" href="mes_annonces.php">
              <i class="bi bi-pencil-square"></i> Mes annonces (modifier)
            </a>

            <a class="btn btn-outline-dark" href="mes_ventes.php">
              <i class="bi bi-bag-check"></i> Mes ventes
            </a>
          </div>

        </div>
      </div>
    </div>

    <div class="col-12 col-lg-7">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h5 class="fw-bold mb-3">Modifier mon profil</h5>

          <form action="actions/profil_action.php" method="POST" class="mb-4">
            <div class="mb-3">
              <label class="form-label">Nom</label>
              <input class="form-control" name="nom" value="<?php echo e($u["nom"]); ?>" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Email</label>
              <input class="form-control" type="email" name="email" value="<?php echo e($u["email"]); ?>" required>
            </div>

            <button class="btn btn-dark" type="submit"><i class="bi bi-save"></i> Enregistrer</button>
          </form>

          <h6 class="fw-bold">Changer mot de passe</h6>
          <form action="actions/password_action.php" method="POST">
            <div class="mb-2">
              <label class="form-label">Ancien mot de passe</label>
              <input class="form-control" type="password" name="old_password" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Nouveau mot de passe</label>
              <input class="form-control" type="password" name="new_password" minlength="6" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirmer</label>
              <input class="form-control" type="password" name="new_password2" minlength="6" required>
            </div>

            <button class="btn btn-outline-dark" type="submit"><i class="bi bi-key"></i> Modifier</button>
          </form>

        </div>
      </div>
    </div>
  </div>
</div>

<?php include "includes/footer.php"; ?>