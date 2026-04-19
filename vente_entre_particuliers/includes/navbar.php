<?php
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/db.php";

$unreadBadge = 0;
$newSalesBadge = 0;
$buyerOrderUpdatesBadge = 0;

$notifBadge = 0;
$popupNotif = null;
$notifList = [];

$currentPage = basename($_SERVER["PHP_SELF"] ?? "");

function notifIconClass(string $type): string {
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
    "SIGNALEMENT_ENVOYE"  => "bi-flag-fill text-warning bg-warning-subtle",
    default               => "bi-bell-fill text-secondary bg-light",
  };
}

function appBasePath(): string {
  $scriptDir = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? ""));
  $scriptDir = rtrim($scriptDir, "/");

  if (str_ends_with($scriptDir, "/admin")) {
    $scriptDir = substr($scriptDir, 0, -6);
  }

  return $scriptDir !== "" ? $scriptDir : "";
}

function notifHref(string $link, bool $isAdmin = false): string {
  $link = trim($link);
  if ($link === "") return "notifications.php";

  if (preg_match('#^https?://#i', $link)) {
    return $link;
  }

  $base = appBasePath();

  while (str_starts_with($link, "../")) {
    $link = substr($link, 3);
  }

  $link = ltrim($link, "/");

  if (str_starts_with($link, "admin/")) {
    return $base . "/" . $link;
  }

  return $base . "/" . $link;
}

if (isLoggedIn()) {
  $uid = currentUserId();

  try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM message WHERE id_destinataire = ? AND is_lu = 0");
    $stmt->execute([$uid]);
    $unreadBadge = (int)$stmt->fetchColumn();
  } catch (Exception $e) {
    $unreadBadge = 0;
  }

  try {
    $stmt = $pdo->prepare("
      SELECT COUNT(DISTINCT o.id_order)
      FROM orders o
      JOIN order_details od ON od.id_order = o.id_order
      JOIN annonce a ON a.id_annonce = od.id_annonce
      LEFT JOIN paiement p ON p.id_order = o.id_order
      WHERE a.id_vendeur = ?
        AND COALESCE(p.statut, 'EN_ATTENTE') = 'ACCEPTE'
        AND COALESCE(o.seller_seen, 0) = 0
    ");
    $stmt->execute([$uid]);
    $newSalesBadge = (int)$stmt->fetchColumn();
  } catch (Exception $e) {
    $newSalesBadge = 0;
  }

  try {
    $stmt = $pdo->prepare("
      SELECT COUNT(*)
      FROM orders
      WHERE id_user = ?
        AND COALESCE(buyer_seen, 1) = 0
        AND statut = 'PAYE'
    ");
    $stmt->execute([$uid]);
    $buyerOrderUpdatesBadge = (int)$stmt->fetchColumn();
  } catch (Exception $e) {
    $buyerOrderUpdatesBadge = 0;
  }

  try {
    $stmt = $pdo->prepare("
      SELECT COUNT(*)
      FROM notification
      WHERE id_user = ?
        AND COALESCE(is_read, 0) = 0
    ");
    $stmt->execute([$uid]);
    $notifBadge = (int)$stmt->fetchColumn();
  } catch (Exception $e) {
    $notifBadge = 0;
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
    $popupNotif = $stmt->fetch();
  } catch (Exception $e) {
    $popupNotif = null;
  }

  try {
    $stmt = $pdo->prepare("
      SELECT *
      FROM notification
      WHERE id_user = ?
      ORDER BY id_notification DESC
      LIMIT 6
    ");
    $stmt->execute([$uid]);
    $notifList = $stmt->fetchAll();
  } catch (Exception $e) {
    $notifList = [];
  }
}
?>

<style>
  .fluxo-navbar {
    min-height: 72px;
    box-shadow: 0 2px 14px rgba(0, 0, 0, 0.04);
  }

  .fluxo-navbar .container {
    align-items: center;
  }

  .fluxo-navbar .navbar-brand {
    padding-top: 0;
    padding-bottom: 0;
    margin-right: 12px;
  }

  .fluxo-navbar .fluxo-logo {
    height: 42px;
    width: auto;
    object-fit: contain;
    display: block;
  }

  .fluxo-navbar .navbar-nav {
    align-items: center;
  }

  .fluxo-navbar .nav-item {
    display: flex;
    align-items: center;
  }

  .fluxo-navbar .fluxo-nav-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    padding: 6px 8px !important;
    font-size: 14px;
    line-height: 1.15;
    color: #4b5563;
    text-align: center;
    white-space: nowrap;
    transition: 0.2s ease;
  }

  .fluxo-navbar .fluxo-nav-link i {
    font-size: 18px;
    line-height: 1;
  }

  .fluxo-navbar .fluxo-nav-link:hover,
  .fluxo-navbar .fluxo-nav-link:focus {
    color: #111827;
  }

  .fluxo-navbar .badge {
    font-size: 10px;
    padding: 4px 6px;
  }

  .fluxo-navbar .btn {
    padding: 8px 14px;
    font-size: 14px;
    border-radius: 12px;
  }

  .fluxo-navbar .dropdown-menu {
    margin-top: 12px;
  }

  @media (max-width: 991.98px) {
    .fluxo-navbar .navbar-collapse {
      padding-top: 12px;
      padding-bottom: 8px;
    }

    .fluxo-navbar .navbar-nav {
      align-items: stretch !important;
      gap: 0 !important;
    }

    .fluxo-navbar .nav-item {
      width: 100%;
    }

    .fluxo-navbar .fluxo-nav-link {
      flex-direction: row;
      justify-content: flex-start;
      text-align: left;
      gap: 10px;
      padding: 10px 0 !important;
      white-space: normal;
    }

    .fluxo-navbar .fluxo-nav-link i {
      font-size: 17px;
      min-width: 20px;
    }

    .fluxo-navbar .btn {
      width: 100%;
      margin-top: 8px;
    }
  }
</style>

<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top py-2 fluxo-navbar">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center py-0 me-3" href="index.php">
      <img src="assets/img/logo.png" alt="Fluxo" class="fluxo-logo">
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">

        <li class="nav-item">
          <a class="nav-link fluxo-nav-link" href="index.php">
            <i class="bi bi-grid"></i>
            <span>Annonces</span>
          </a>
        </li>

        <?php if (isLoggedIn()): ?>

          <li class="nav-item">
            <a class="nav-link fluxo-nav-link" href="dashboard_vendeur.php">
              <i class="bi bi-speedometer2"></i>
              <span>Dashboard</span>
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link fluxo-nav-link position-relative" href="mes_commandes.php">
              <i class="bi bi-receipt"></i>
              <span>Mes commandes</span>
              <?php if ($buyerOrderUpdatesBadge > 0): ?>
                <span class="badge rounded-pill text-bg-danger ms-1"><?php echo (int)$buyerOrderUpdatesBadge; ?></span>
              <?php endif; ?>
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link fluxo-nav-link position-relative" href="mes_ventes.php">
              <i class="bi bi-bag-check"></i>
              <span>Ventes</span>
              <?php if ($newSalesBadge > 0): ?>
                <span class="badge rounded-pill text-bg-danger ms-1"><?php echo (int)$newSalesBadge; ?></span>
              <?php endif; ?>
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link fluxo-nav-link" href="mes_favoris.php">
              <i class="bi bi-heart"></i>
              <span>Favoris</span>
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link fluxo-nav-link" href="publier.php">
              <i class="bi bi-plus-circle"></i>
              <span>Publier</span>
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link fluxo-nav-link" href="panier.php">
              <i class="bi bi-cart3"></i>
              <span>Panier</span>
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link fluxo-nav-link position-relative" href="messages.php">
              <i class="bi bi-chat-dots"></i>
              <span>Messages</span>
              <?php if ($unreadBadge > 0): ?>
                <span class="badge rounded-pill text-bg-danger ms-1"><?php echo (int)$unreadBadge; ?></span>
              <?php endif; ?>
            </a>
          </li>

          <li class="nav-item dropdown">
            <a
              class="nav-link fluxo-nav-link position-relative"
              href="#"
              id="notifDropdownBtn"
              data-bs-toggle="dropdown"
              aria-expanded="false"
            >
              <i class="bi bi-bell"></i>
              <span>Notifications</span>
              <?php if ($notifBadge > 0): ?>
                <span id="notifBadgeBubble" class="badge rounded-pill text-bg-danger ms-1"><?php echo (int)$notifBadge; ?></span>
              <?php endif; ?>
            </a>

            <ul
              class="dropdown-menu dropdown-menu-end shadow border-0 p-0 overflow-hidden"
              style="width: 380px; border-radius: 16px;"
              id="notifDropdownMenu"
            >
              <li class="px-3 py-3 border-bottom bg-light">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="fw-bold">Notifications</span>
                  <a href="<?php echo htmlspecialchars(notifHref('notifications.php', currentUserRole() === 'ADMIN')); ?>" class="small text-decoration-none">Voir tout</a>
                </div>
              </li>

              <div id="notifDropdownItems">
                <?php if (count($notifList) === 0): ?>
                  <li class="px-3 py-4 text-center text-muted" id="notifEmptyState">Aucune notification</li>
                <?php else: ?>
                  <?php foreach ($notifList as $n): ?>
                    <?php
                      $type = (string)($n["type_notification"] ?? "");
                      $iconClasses = notifIconClass($type);
                      $parts = explode(" ", $iconClasses, 2);
                      $iconOnly = $parts[0] ?? "bi-bell-fill";
                      $extraClass = $parts[1] ?? "text-secondary bg-light";
                      $isRead = (int)($n["is_read"] ?? 0) === 1;
                      $notifLink = notifHref((string)($n["lien"] ?? "notifications.php"), currentUserRole() === "ADMIN");
                    ?>
                    <li>
                      <a
                        class="dropdown-item px-3 py-3 border-bottom <?php echo !$isRead ? 'bg-light-subtle notif-unread-item' : ''; ?>"
                        href="<?php echo htmlspecialchars($notifLink); ?>"
                      >
                        <div class="d-flex gap-3 align-items-start">
                          <div class="rounded-circle d-flex align-items-center justify-content-center <?php echo htmlspecialchars($extraClass); ?>"
                               style="width:44px;height:44px;flex:0 0 44px;">
                            <i class="bi <?php echo htmlspecialchars($iconOnly); ?>"></i>
                          </div>

                          <div class="flex-grow-1" style="min-width:0;">
                            <div class="fw-semibold d-flex align-items-center gap-2 flex-wrap">
                              <span><?php echo htmlspecialchars($n["titre"] ?? "Notification"); ?></span>
                              <?php if (!$isRead): ?>
                                <span class="badge text-bg-danger notif-new-badge">Nouveau</span>
                              <?php endif; ?>
                            </div>
                            <div class="small text-muted mt-1 text-wrap">
                              <?php echo htmlspecialchars($n["contenu"] ?? ""); ?>
                            </div>
                            <div class="small text-muted mt-1">
                              <?php echo htmlspecialchars(substr((string)($n["created_at"] ?? ""), 0, 16)); ?>
                            </div>
                          </div>
                        </div>
                      </a>
                    </li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </ul>
          </li>

          <li class="nav-item">
            <a class="nav-link fluxo-nav-link" href="profil.php">
              <i class="bi bi-person"></i>
              <span>Mon compte</span>
            </a>
          </li>

          <?php if (currentUserRole() === "ADMIN"): ?>
            <li class="nav-item">
              <a class="nav-link fluxo-nav-link text-danger fw-bold" href="admin/index.php">
                <i class="bi bi-shield-lock"></i>
                <span>Admin Panel</span>
              </a>
            </li>
          <?php endif; ?>

          <li class="nav-item ms-lg-2">
            <a class="btn btn-dark" href="logout.php">Déconnexion</a>
          </li>

        <?php else: ?>

          <li class="nav-item">
            <a class="nav-link fluxo-nav-link" href="login.php">
              <i class="bi bi-box-arrow-in-right"></i>
              <span>Connexion</span>
            </a>
          </li>

          <li class="nav-item ms-lg-2">
            <a class="btn btn-dark" href="register.php">Inscription</a>
          </li>

        <?php endif; ?>

      </ul>
    </div>
  </div>
</nav>

<?php if (!empty($popupNotif) && $currentPage !== "notifications.php"): ?>
  <?php
    $popupType = (string)($popupNotif["type_notification"] ?? "");
    $popupIconClasses = notifIconClass($popupType);
    $popupParts = explode(" ", $popupIconClasses, 2);
    $popupIcon = $popupParts[0] ?? "bi-bell-fill";
    $popupOpenLink = notifHref((string)($popupNotif["lien"] ?? "notifications.php"), currentUserRole() === "ADMIN");
  ?>
  <div id="notif-popup" style="
    position: fixed;
    top: 82px;
    right: 20px;
    z-index: 99999;
    min-width: 330px;
    max-width: 390px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 16px 40px rgba(0,0,0,.20);
    padding: 16px;
    border: 1px solid #eee;
  ">
    <div class="d-flex align-items-start gap-3">
      <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
           style="width:46px;height:46px;flex:0 0 46px;">
        <i class="bi <?php echo htmlspecialchars($popupIcon); ?> fs-5"></i>
      </div>

      <div class="flex-grow-1">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="fw-bold"><?php echo htmlspecialchars($popupNotif["titre"] ?? "Notification"); ?></div>
          <button type="button" id="closeNotifPopup" class="btn-close"></button>
        </div>

        <div class="text-muted small mt-1">
          <?php echo htmlspecialchars($popupNotif["contenu"] ?? ""); ?>
        </div>

        <?php if (!empty($popupNotif["lien"])): ?>
          <div class="mt-3">
            <a href="<?php echo htmlspecialchars($popupOpenLink); ?>" class="btn btn-sm btn-dark">
              Ouvrir
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    (function () {
      const popup = document.getElementById("notif-popup");
      const closeBtn = document.getElementById("closeNotifPopup");

      function markSeen() {
        fetch("notifications_seen.php?id=<?php echo (int)$popupNotif["id_notification"]; ?>", {
          method: "GET",
          cache: "no-store"
        }).catch(() => {});
      }

      function closePopup() {
        if (popup) popup.remove();
        markSeen();
      }

      if (closeBtn) {
        closeBtn.addEventListener("click", closePopup);
      }

      setTimeout(closePopup, 3000);
    })();
  </script>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const notifDropdown = document.querySelector(".nav-item.dropdown");
  const notifDropdownItems = document.getElementById("notifDropdownItems");
  const notifBtn = document.getElementById("notifDropdownBtn");

  let alreadyMarked = false;
  let lastPopupId = 0;

  function escapeHtml(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function renderBadge(count) {
    let badge = document.getElementById("notifBadgeBubble");

    if (count > 0) {
      if (!badge && notifBtn) {
        badge = document.createElement("span");
        badge.id = "notifBadgeBubble";
        badge.className = "badge rounded-pill text-bg-danger ms-1";
        notifBtn.appendChild(badge);
      }
      if (badge) {
        badge.textContent = count;
      }
    } else {
      if (badge) badge.remove();
    }
  }

  function renderNotifItems(items) {
    if (!notifDropdownItems) return;

    if (!items || items.length === 0) {
      notifDropdownItems.innerHTML = '<li class="px-3 py-4 text-center text-muted" id="notifEmptyState">Aucune notification</li>';
      return;
    }

    let html = "";

    items.forEach(function (n) {
      html += `
        <li>
          <a class="dropdown-item px-3 py-3 border-bottom ${Number(n.is_read) === 0 ? "bg-light-subtle notif-unread-item" : ""}" href="${escapeHtml(n.href)}">
            <div class="d-flex gap-3 align-items-start">
              <div class="rounded-circle d-flex align-items-center justify-content-center ${escapeHtml(n.extra_class)}"
                   style="width:44px;height:44px;flex:0 0 44px;">
                <i class="bi ${escapeHtml(n.icon)}"></i>
              </div>

              <div class="flex-grow-1" style="min-width:0;">
                <div class="fw-semibold d-flex align-items-center gap-2 flex-wrap">
                  <span>${escapeHtml(n.titre)}</span>
                  ${Number(n.is_read) === 0 ? '<span class="badge text-bg-danger notif-new-badge">Nouveau</span>' : ""}
                </div>
                <div class="small text-muted mt-1 text-wrap">
                  ${escapeHtml(n.contenu)}
                </div>
                <div class="small text-muted mt-1">
                  ${escapeHtml(n.created_at)}
                </div>
              </div>
            </div>
          </a>
        </li>
      `;
    });

    notifDropdownItems.innerHTML = html;
  }

  function showRealtimePopup(popup) {
    if (!popup || !popup.id_notification) return;
    if (Number(popup.id_notification) === Number(lastPopupId)) return;
    if (document.getElementById("notif-popup-live")) return;

    lastPopupId = Number(popup.id_notification);

    const html = `
      <div id="notif-popup-live" style="
        position: fixed;
        top: 82px;
        right: 20px;
        z-index: 99999;
        min-width: 330px;
        max-width: 390px;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 16px 40px rgba(0,0,0,.20);
        padding: 16px;
        border: 1px solid #eee;
      ">
        <div class="d-flex align-items-start gap-3">
          <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
               style="width:46px;height:46px;flex:0 0 46px;">
            <i class="bi ${escapeHtml(popup.icon)} fs-5"></i>
          </div>

          <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="fw-bold">${escapeHtml(popup.titre)}</div>
              <button type="button" id="closeNotifPopupLive" class="btn-close"></button>
            </div>

            <div class="text-muted small mt-1">
              ${escapeHtml(popup.contenu)}
            </div>

            <div class="mt-3">
              <a href="${escapeHtml(popup.href)}" class="btn btn-sm btn-dark">
                Ouvrir
              </a>
            </div>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML("beforeend", html);

    const popupEl = document.getElementById("notif-popup-live");
    const closeBtn = document.getElementById("closeNotifPopupLive");

    function markSeen() {
      fetch("notifications_seen.php?id=" + encodeURIComponent(popup.id_notification), {
        method: "GET",
        cache: "no-store"
      }).catch(() => {});
    }

    function closePopup() {
      if (popupEl) popupEl.remove();
      markSeen();
    }

    if (closeBtn) {
      closeBtn.addEventListener("click", closePopup);
    }

    setTimeout(closePopup, 4000);
  }

  async function pollNotifications() {
    try {
      const res = await fetch("actions/notifications_poll.php", {
        method: "GET",
        cache: "no-store"
      });

      const data = await res.json();
      if (!res.ok || !data.ok) return;

      renderBadge(Number(data.badge || 0));
      renderNotifItems(data.items || []);

      if ("<?php echo $currentPage; ?>" !== "notifications.php" && data.popup) {
        showRealtimePopup(data.popup);
      }
    } catch (e) {
      // ignore
    }
  }

  function markNotificationsRead() {
    if (alreadyMarked) return;
    alreadyMarked = true;

    fetch("notifications_mark_read.php", {
      method: "GET",
      cache: "no-store"
    })
    .then(async (res) => {
      let data = {};
      try {
        data = await res.json();
      } catch (e) {}

      if (!res.ok || data.ok === false) {
        alreadyMarked = false;
        return;
      }

      renderBadge(0);

      document.querySelectorAll(".notif-new-badge").forEach(function (el) {
        el.remove();
      });

      document.querySelectorAll(".notif-unread-item").forEach(function (el) {
        el.classList.remove("bg-light-subtle");
      });
    })
    .catch(() => {
      alreadyMarked = false;
    });
  }

  if (notifDropdown) {
    notifDropdown.addEventListener("hidden.bs.dropdown", function () {
      markNotificationsRead();
    });
  }

  <?php if (isLoggedIn()): ?>
    pollNotifications();
    setInterval(pollNotifications, 5000);
  <?php endif; ?>
});
</script>