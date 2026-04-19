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

<div class="container my-5" style="max-width: 980px;">
  <div class="row g-4 align-items-stretch">
    <div class="col-12 col-lg-6">
      <div class="p-4 hero h-100">
        <h2 class="fw-bold mb-2">Re-bienvenue 👋</h2>
        <p class="mb-0 opacity-75">
          Connecte-toi pour publier, discuter, acheter et gérer tes annonces.
        </p>
        <div class="mt-3 d-flex flex-wrap gap-2">
          <span class="badge badge-soft">Messagerie</span>
          <span class="badge badge-soft">Paiement direct</span>
          <span class="badge badge-soft">Panier</span>
          <span class="badge badge-soft">Avis</span>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body p-4 p-md-5">

          <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0 fw-bold">Connexion</h3>
            <span class="text-muted small"><i class="bi bi-lock"></i> sécurisé</span>
          </div>

          <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
          <?php endif; ?>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>

          <form action="actions/login_action.php" method="POST" novalidate>
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

            <div class="mb-2">
              <label class="form-label">Mot de passe</label>
              <div class="input-group">
                <input
                  type="password"
                  name="password"
                  class="form-control"
                  placeholder="Ton mot de passe"
                  required
                  id="pwd"
                >
                <button class="btn btn-outline-secondary" type="button" id="togglePwd">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>

            <div class="mb-3 text-end">
              <a href="forgot_password.php" class="small text-decoration-none">
                Mot de passe oublié ?
              </a>
            </div>

            <div class="d-grid gap-2 mt-4">
              <button class="btn btn-dark btn-lg" type="submit">
                <i class="bi bi-box-arrow-in-right"></i> Se connecter
              </button>
              <a class="btn btn-outline-secondary" href="register.php">
                Créer un compte
              </a>
            </div>
          </form>

        </div>
      </div>
    </div>
  </div>
</div>

<script>
const btn = document.getElementById("togglePwd");
const input = document.getElementById("pwd");

if (btn && input) {
  btn.addEventListener("click", () => {
    input.type = input.type === "password" ? "text" : "password";
    btn.innerHTML = input.type === "password"
      ? '<i class="bi bi-eye"></i>'
      : '<i class="bi bi-eye-slash"></i>';
  });
}
</script>

<?php include "includes/footer.php"; ?>