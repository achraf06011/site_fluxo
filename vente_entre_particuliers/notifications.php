<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = currentUserId();
$isAdminViewer = (currentUserRole() === "ADMIN");

function e($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

function notificationsIcon(string $type): string {
  $type = strtoupper(trim($type));
  return match ($type) {
    "NEW_ORDER"           => "bi-bag-check-fill text-primary bg-primary-subtle",
    "ORDER_UPDATE"        => "bi-truck text-warning bg-warning-subtle",
    "ORDER_DELIVERED"     => "bi-check-circle-fill text-success bg-success-subtle",
    "ANNONCE_VALIDATED"   => "bi-check2-circle text-success bg-success-subtle",
    "ANNONCE_REFUSED"     => "bi-x-circle-fill text-danger bg-danger-subtle",
    "ANNONCE_DELETED"     => "bi-trash-fill text-danger bg-danger-subtle",
    "NEW_MESSAGE"         => "bi-chat-dots-fill text-info bg-info-subtle",
    "NEW_REVIEW"          => "bi-star-fill text-warning bg-warning-subtle",
    "ADMIN_ANNONCE_WAIT"  => "bi-hourglass-split text-warning bg-warning-subtle",
    "ADMIN_NEW_ORDER"     => "bi-receipt-cutoff text-primary bg-primary-subtle",
    "ADMIN_NEW_USER"      => "bi-person-plus-fill text-info bg-info-subtle",
    "ORDER_STATUS"        => "bi-truck text-warning bg-warning-subtle",
    "ADMIN_SIGNALEMENT"   => "bi-flag-fill text-danger bg-danger-subtle",
    "SIGNALEMENT_ENVOYE"  => "bi-flag-fill text-warning bg-warning-subtle",
    default               => "bi-bell-fill text-secondary bg-light",
  };
}

function notificationsBasePath(): string {
  $scriptDir = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? ""));
  $scriptDir = rtrim($scriptDir, "/");

  if (str_ends_with($scriptDir, "/admin")) {
    $scriptDir = substr($scriptDir, 0, -6);
  }

  return $scriptDir !== "" ? $scriptDir : "";
}

function notificationsLink(string $link): string {
  $link = trim($link);
  if ($link === "") return "";

  if (preg_match('#^https?://#i', $link)) {
    return $link;
  }

  $base = notificationsBasePath();

  while (str_starts_with($link, "../")) {
    $link = substr($link, 3);
  }

  $link = ltrim($link, "/");

  return $base . "/" . $link;
}

try {
  $stmt = $pdo->prepare("
    UPDATE notification
    SET is_read = 1
    WHERE id_user = ?
      AND COALESCE(is_read, 0) = 0
  ");
  $stmt->execute([$userId]);
} catch (Exception $e) {}

$rows = [];
try {
  $stmt = $pdo->prepare("
    SELECT *
    FROM notification
    WHERE id_user = ?
    ORDER BY id_notification DESC
    LIMIT 100
  ");
  $stmt->execute([$userId]);
  $rows = $stmt->fetchAll();
} catch (Exception $e) {
  $rows = [];
}

$backUrl = $isAdminViewer ? "admin/index.php" : "index.php";

include "includes/header.php";
if ($isAdminViewer) {
  include "includes/admin_navbar.php";
} else {
  include "includes/navbar.php";
}
?>

<div class="container my-4" style="max-width: 980px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h2 class="fw-bold mb-0"><i class="bi bi-bell"></i> Notifications</h2>
      <div class="text-muted">Toutes tes notifications récentes.</div>
    </div>
    <a class="btn btn-outline-secondary" href="<?php echo e($backUrl); ?>">Retour</a>
  </div>

  <div class="card shadow-sm border-0" style="border-radius:18px;">
    <div class="card-body p-0">
      <?php if (count($rows) === 0): ?>
        <div class="p-4">
          <div class="alert alert-warning mb-0">Aucune notification pour le moment.</div>
        </div>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($rows as $n): ?>
            <?php
              $link = notificationsLink((string)($n["lien"] ?? ""));
              $titre = trim((string)($n["titre"] ?? "Notification"));
              $contenu = trim((string)($n["contenu"] ?? ""));
              $date = trim((string)($n["created_at"] ?? ""));
              $isRead = (int)($n["is_read"] ?? 0) === 1;
              $type = (string)($n["type_notification"] ?? "");

              $iconClasses = notificationsIcon($type);
              $parts = explode(" ", $iconClasses, 2);
              $iconOnly = $parts[0] ?? "bi-bell-fill";
              $extraClass = $parts[1] ?? "text-secondary bg-light";
            ?>
            <div class="list-group-item border-0 border-bottom <?php echo !$isRead ? 'bg-light-subtle' : ''; ?>" style="padding:18px 20px;">
              <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div class="d-flex gap-3 align-items-start">
                  <div class="rounded-circle d-flex align-items-center justify-content-center <?php echo e($extraClass); ?>"
                       style="width:52px;height:52px;flex:0 0 52px;">
                    <i class="bi <?php echo e($iconOnly); ?> fs-5"></i>
                  </div>

                  <div>
                    <div class="fw-semibold d-flex align-items-center gap-2 flex-wrap">
                      <?php echo e($titre); ?>
                      <?php if (!$isRead): ?>
                        <span class="badge text-bg-danger">Nouveau</span>
                      <?php endif; ?>
                    </div>

                    <div class="text-muted mt-1"><?php echo e($contenu); ?></div>
                    <div class="small text-muted mt-2"><?php echo e(substr($date, 0, 16)); ?></div>
                  </div>
                </div>

                <?php if ($link !== ""): ?>
                  <a class="btn btn-sm btn-outline-dark" href="<?php echo e($link); ?>">
                    Ouvrir
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include "includes/footer.php"; ?>