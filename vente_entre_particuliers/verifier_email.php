<?php
require_once "config/db.php";
require_once "config/auth.php";

if (session_status() === PHP_SESSION_NONE) session_start();

$pendingUserId = (int)($_SESSION["pending_verification_user_id"] ?? 0);
$pendingEmail = $_SESSION["pending_verification_email"] ?? "";

if ($pendingUserId <= 0 || $pendingEmail === "") {
    $_SESSION["flash_error"] = "Aucune vérification email en attente.";
    header("Location: login.php");
    exit;
}

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";

unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

$resendSeconds = 0;

try {
    $stmt = $pdo->prepare("
        SELECT code_verification_renvoi_at
        FROM user
        WHERE id_user = ?
        LIMIT 1
    ");
    $stmt->execute([$pendingUserId]);
    $userRow = $stmt->fetch();

    if ($userRow && !empty($userRow["code_verification_renvoi_at"])) {
        $resendAt = strtotime($userRow["code_verification_renvoi_at"]);
        if ($resendAt > time()) {
            $resendSeconds = $resendAt - time();
        }
    }
} catch (Exception $e) {
    $resendSeconds = 0;
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<div class="container my-5" style="max-width: 720px;">
  <div class="card shadow-sm border-0">
    <div class="card-body p-4 p-md-5">
      <h2 class="fw-bold mb-2">Vérifier ton email</h2>
      <p class="text-muted mb-4">
        Un code de vérification a été envoyé à :
        <b><?php echo htmlspecialchars($pendingEmail); ?></b>
      </p>

      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form action="actions/verifier_email_action.php" method="POST">
        <div class="mb-3">
          <label class="form-label">Code de vérification</label>
          <input
            type="text"
            name="code"
            class="form-control"
            maxlength="6"
            placeholder="Ex: 123456"
            required
          >
        </div>

        <div class="d-grid gap-2">
          <button class="btn btn-dark" type="submit">
            Vérifier mon email
          </button>

          <a
            class="btn btn-outline-secondary <?php echo $resendSeconds > 0 ? 'disabled' : ''; ?>"
            href="<?php echo $resendSeconds > 0 ? '#' : 'actions/renvoyer_code_verification.php'; ?>"
            id="resendBtn"
            <?php echo $resendSeconds > 0 ? 'aria-disabled="true"' : ''; ?>
          >
            Renvoyer le code
            <span id="resendTimerText">
              <?php echo $resendSeconds > 0 ? '(' . (int)$resendSeconds . 's)' : ''; ?>
            </span>
          </a>

          <a class="btn btn-outline-dark" href="login.php">
            Retour connexion
          </a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const resendBtn = document.getElementById("resendBtn");
  const resendTimerText = document.getElementById("resendTimerText");
  let seconds = <?php echo (int)$resendSeconds; ?>;

  if (!resendBtn || !resendTimerText || seconds <= 0) return;

  const timer = setInterval(function () {
    seconds--;

    if (seconds > 0) {
      resendTimerText.textContent = "(" + seconds + "s)";
    } else {
      clearInterval(timer);
      resendTimerText.textContent = "";
      resendBtn.classList.remove("disabled");
      resendBtn.removeAttribute("aria-disabled");
      resendBtn.setAttribute("href", "actions/renvoyer_code_verification.php");
    }
  }, 1000);
});
</script>

<?php include "includes/footer.php"; ?>