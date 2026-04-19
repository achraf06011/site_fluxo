<?php
require_once "config/db.php";
require_once "config/auth.php";

if (session_status() === PHP_SESSION_NONE) session_start();

$token = trim($_GET["token"] ?? "");
if ($token === "") {
  $_SESSION["flash_error"] = "Lien invalide.";
  header("Location: login.php");
  exit;
}

$tokenHash = hash("sha256", $token);

$stmt = $pdo->prepare("
  SELECT id_user, email, reset_token_expire
  FROM user
  WHERE reset_token = ?
  LIMIT 1
");
$stmt->execute([$tokenHash]);
$user = $stmt->fetch();

if (!$user) {
  $_SESSION["flash_error"] = "Lien invalide ou expiré.";
  header("Location: login.php");
  exit;
}

if (empty($user["reset_token_expire"]) || strtotime($user["reset_token_expire"]) < time()) {
  $_SESSION["flash_error"] = "Lien expiré. Refais une demande.";
  header("Location: forgot_password.php");
  exit;
}

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";

unset($_SESSION["flash_success"], $_SESSION["flash_error"]);
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<div class="container my-5" style="max-width: 760px;">
  <div class="card shadow-sm border-0">
    <div class="card-body p-4 p-md-5">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0 fw-bold">Nouveau mot de passe</h3>
        <span class="text-muted small"><i class="bi bi-key"></i> sécurisé</span>
      </div>

      <p class="text-muted">
        Choisis un nouveau mot de passe pour :
        <b><?php echo htmlspecialchars($user["email"]); ?></b>
      </p>

      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form action="actions/reset_password_action.php" method="POST" novalidate>
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <div class="mb-3">
          <label class="form-label">Nouveau mot de passe</label>
          <div class="input-group">
            <input
              type="password"
              name="password"
              class="form-control"
              minlength="6"
              required
              id="pwd"
              placeholder="Minimum 6 caractères"
            >
            <button class="btn btn-outline-secondary" type="button" id="togglePwd">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Confirmer le mot de passe</label>
          <input
            type="password"
            name="password2"
            class="form-control"
            minlength="6"
            required
            placeholder="Répète le mot de passe"
          >
        </div>

        <div class="d-grid gap-2 mt-4">
          <button class="btn btn-dark btn-lg" type="submit">
            <i class="bi bi-check2-circle"></i> Enregistrer
          </button>
          <a class="btn btn-outline-secondary" href="login.php">Retour connexion</a>
        </div>
      </form>
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