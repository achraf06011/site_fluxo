<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/db.php";

$adminNotifBadge = 0;
$adminPopupNotif = null;
$adminNotifList = [];
$currentPage = basename($_SERVER["PHP_SELF"] ?? "");

if (isLoggedIn() && currentUserRole() === "ADMIN") {
  $uid = currentUserId();

  try {
    $stmt = $pdo->prepare("
      SELECT COUNT(*)
      FROM notification
      WHERE id_user = ?
        AND COALESCE(is_read, 0) = 0
    ");
    $stmt->execute([$uid]);
    $adminNotifBadge = (int)$stmt->fetchColumn();
  } catch (Exception $e) {
    $adminNotifBadge = 0;
  }

  try {
    $stmt = $pdo->prepare("
      SELECT *
      FROM notification
      WHERE id_user = ?
        AND COALESCE(is_popup_seen, 0) = 0
      ORDER BY id_notification DESC
      LIMIT 1
    ");
    $stmt->execute([$uid]);
    $adminPopupNotif = $stmt->fetch();
  } catch (Exception $e) {
    $adminPopupNotif = null;
  }

  try {
    $stmt = $pdo->prepare("
      SELECT *
      FROM notification
      WHERE id_user = ?
      ORDER BY id_notification DESC
      LIMIT 8
    ");
    $stmt->execute([$uid]);
    $adminNotifList = $stmt->fetchAll();
  } catch (Exception $e) {
    $adminNotifList = [];
  }
}

function adminNotifIcon(string $type): string {
  $type = strtoupper(trim($type));
  return match ($type) {
    "ADMIN_ANNONCE_WAIT"      => "bi-hourglass-split text-warning",
    "ADMIN_ANNONCE_VALIDATED" => "bi-check-circle-fill text-success",
    "ADMIN_ANNONCE_REFUSED"   => "bi-x-circle-fill text-danger",
    "ADMIN_ANNONCE_DELETED"   => "bi-trash-fill text-danger",
    "ADMIN_NEW_ORDER"         => "bi-bag-check-fill text-primary",
    "ADMIN_NEW_USER"          => "bi-person-plus-fill text-info",
    default                   => "bi-bell-fill text-secondary",
  };
}
?>

<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="../index.php">
  <img src="../assets/img/logo.png" alt="Fluxo" style="height:60px; width:auto; object-fit:contain;">
</a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav ms-auto gap-2">

        <li class="nav-item">
          <a class="nav-link" href="../admin/index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="../admin/annonces.php"><i class="bi bi-megaphone"></i> Annonces</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="../admin/users.php"><i class="bi bi-people"></i> Utilisateurs</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="../admin/orders.php"><i class="bi bi-receipt"></i> Commandes</a>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link position-relative"
             href="#"
             id="adminNotifDropdownBtn"
             data-bs-toggle="dropdown"
             aria-expanded="false">
            <i class="bi bi-bell"></i> Notifications
            <?php if ($adminNotifBadge > 0): ?>
              <span id="adminNotifBadgeBubble" class="badge rounded-pill text-bg-danger ms-1"><?php echo (int)$adminNotifBadge; ?></span>
            <?php endif; ?>
          </a>

          <ul id="adminNotifDropdownMenu" class="dropdown-menu dropdown-menu-end shadow p-0 overflow-hidden" style="width: 380px; border-radius: 16px;">
            <li class="px-3 py-2 border-bottom bg-light fw-bold">
              Notifications admin
            </li>

            <?php if (count($adminNotifList) === 0): ?>
              <li class="p-3 text-muted">Aucune notification.</li>
            <?php else: ?>
              <?php foreach ($adminNotifList as $n): ?>
                <?php
                  $link = trim((string)($n["lien"] ?? "../admin/index.php"));
                  $title = trim((string)($n["titre"] ?? "Notification"));
                  $content = trim((string)($n["contenu"] ?? ""));
                  $type = (string)($n["type_notification"] ?? "");
                  $isRead = (int)($n["is_read"] ?? 0) === 1;
                ?>
                <li>
                  <a class="dropdown-item py-3 px-3 border-bottom <?php echo !$isRead ? 'bg-light-subtle admin-notif-unread-item' : ''; ?>"
                     href="<?php echo htmlspecialchars($link); ?>">
                    <div class="d-flex gap-3 align-items-start">
                      <div class="fs-5">
                        <i class="bi <?php echo adminNotifIcon($type); ?>"></i>
                      </div>
                      <div class="flex-grow-1">
                        <div class="fw-semibold">
                          <?php echo htmlspecialchars($title); ?>
                          <?php if (!$isRead): ?>
                            <span class="badge text-bg-danger ms-1 admin-notif-new-badge">Nouveau</span>
                          <?php endif; ?>
                        </div>
                        <div class="small text-muted mt-1"><?php echo htmlspecialchars($content); ?></div>
                      </div>
                    </div>
                  </a>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>

            <li>
              <a class="dropdown-item text-center py-2 fw-semibold" href="../notifications.php">
                Voir tout
              </a>
            </li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="../index.php"><i class="bi bi-arrow-left"></i> Retour site</a>
        </li>

        <li class="nav-item d-flex align-items-center ms-lg-2">
          <span class="badge text-bg-danger me-2"><i class="bi bi-shield-lock"></i> ADMIN</span>
          <a class="btn btn-dark btn-sm" href="../logout.php">Déconnexion</a>
        </li>

      </ul>
    </div>
  </div>
</nav>

<?php if (!empty($adminPopupNotif) && $currentPage !== "notifications.php"): ?>
  <div id="admin-notif-popup" style="
    position: fixed;
    top: 85px;
    right: 20px;
    z-index: 99999;
    min-width: 330px;
    max-width: 400px;
    background: #fff;
    border-left: 5px solid #dc3545;
    border-radius: 16px;
    box-shadow: 0 14px 35px rgba(0,0,0,.18);
    padding: 14px 16px;
  ">
    <div class="d-flex justify-content-between align-items-start gap-3">
      <div class="d-flex gap-3">
        <div class="fs-4">
          <i class="bi <?php echo adminNotifIcon((string)($adminPopupNotif["type_notification"] ?? "")); ?>"></i>
        </div>
        <div>
          <div class="fw-bold mb-1"><?php echo htmlspecialchars($adminPopupNotif["titre"] ?? "Notification"); ?></div>
          <div class="text-muted small"><?php echo htmlspecialchars($adminPopupNotif["contenu"] ?? ""); ?></div>
        </div>
      </div>
      <button type="button" id="closeAdminNotifPopup" class="btn-close"></button>
    </div>

    <?php if (!empty($adminPopupNotif["lien"])): ?>
      <div class="mt-2">
        <a href="<?php echo htmlspecialchars($adminPopupNotif["lien"]); ?>" class="btn btn-sm btn-dark">Ouvrir</a>
      </div>
    <?php endif; ?>
  </div>

  <script>
    (function() {
      const popup = document.getElementById('admin-notif-popup');
      const closeBtn = document.getElementById('closeAdminNotifPopup');

      function closePopup() {
        if (popup) popup.remove();
        fetch('../notifications_seen.php?id=<?php echo (int)$adminPopupNotif["id_notification"]; ?>').catch(() => {});
      }

      if (closeBtn) closeBtn.addEventListener('click', closePopup);
      setTimeout(closePopup, 3000);
    })();
  </script>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const notifBtn = document.getElementById("adminNotifDropdownBtn");
  const notifBadge = document.getElementById("adminNotifBadgeBubble");
  let alreadyMarked = false;

  function markAdminNotificationsRead() {
    if (alreadyMarked) return;
    alreadyMarked = true;

    fetch("../notifications_mark_read.php", {
      method: "GET",
      cache: "no-store"
    })
    .then(() => {
      if (notifBadge) {
        notifBadge.remove();
      }

      document.querySelectorAll(".admin-notif-new-badge").forEach(function (el) {
        el.remove();
      });

      document.querySelectorAll(".admin-notif-unread-item").forEach(function (el) {
        el.classList.remove("bg-light-subtle");
      });
    })
    .catch(() => {});
  }

  if (notifBtn) {
    notifBtn.addEventListener("hidden.bs.dropdown", function () {
      markAdminNotificationsRead();
    });
  }
});
</script>