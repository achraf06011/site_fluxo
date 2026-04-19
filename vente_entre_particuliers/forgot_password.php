<?php
require_once "config/auth.php";
if (session_status() === PHP_SESSION_NONE) session_start();

if (isLoggedIn()) {
  header("Location: index.php");
  exit;
}

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
$old     = $_SESSION["flash_old"] ?? [];

unset($_SESSION["flash_success"], $_SESSION["flash_error"], $_SESSION["flash_old"]);
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<div class="container my-5" style="max-width: 760px;">
  <div class="card shadow-sm border-0">
    <div class="card-body p-4 p-md-5">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0 fw-bold">Mot de passe oublié</h3>
        <span class="text-muted small"><i class="bi bi-envelope"></i> email sécurisé</span>
      </div>

      <p class="text-muted">
        Entre ton adresse email. Si elle existe, on t’enverra un lien pour réinitialiser ton mot de passe.
      </p>

      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form action="actions/forgot_password_action.php" method="POST" novalidate>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input
            type="email"
            name="email"
            class="form-control"
            placeholder="exemple@gmail.com"
            value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>"
            required
          >
        </div>

        <div class="d-grid gap-2 mt-4">
          <button class="btn btn-dark btn-lg" type="submit">
            <i class="bi bi-send"></i> Envoyer le lien
          </button>
          <a class="btn btn-outline-secondary" href="login.php">
            Retour connexion
          </a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include "includes/footer.php"; ?>