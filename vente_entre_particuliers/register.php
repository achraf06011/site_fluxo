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
        <h2 class="fw-bold mb-2">Créer un compte</h2>
        <p class="mb-0 opacity-75">
          Rejoins Fluxo et commence à publier tes annonces, discuter ou acheter en toute simplicité.
        </p>
        <div class="mt-3 d-flex flex-wrap gap-2">
          <span class="badge badge-soft">Publier annonces</span>
          <span class="badge badge-soft">Messagerie</span>
          <span class="badge badge-soft">Paiement direct</span>
          <span class="badge badge-soft">Avis</span>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body p-4 p-md-5">

          <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0 fw-bold">Inscription</h3>
            <span class="text-muted small"><i class="bi bi-shield-lock"></i> sécurisé</span>
          </div>

          <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
          <?php endif; ?>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>

          <form action="actions/register_action.php" method="POST" novalidate>
            <div class="mb-3">
              <label class="form-label">Nom complet</label>
              <input
                type="text"
                name="nom"
                class="form-control"
                placeholder="Ex: Youssef El..."
                value="<?php echo htmlspecialchars($old['nom'] ?? ''); ?>"
                required
              >
            </div>

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

            <div class="mb-3">
              <label class="form-label">Mot de passe</label>
              <div class="input-group">
                <input
                  type="password"
                  name="password"
                  class="form-control"
                  placeholder="Minimum 6 caractères"
                  minlength="6"
                  required
                  id="pwd"
                >
                <button class="btn btn-outline-secondary" type="button" id="togglePwd">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div class="form-text">Utilise un mot de passe fort (au moins 6 caractères).</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Confirmer mot de passe</label>
              <input
                type="password"
                name="password2"
                class="form-control"
                placeholder="Répète le mot de passe"
                minlength="6"
                required
              >
            </div>

            <div class="d-grid gap-2 mt-4">
              <button class="btn btn-dark btn-lg" type="submit">
                <i class="bi bi-person-plus"></i> Créer mon compte
              </button>
              <a class="btn btn-outline-secondary" href="login.php">
                J'ai déjà un compte
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