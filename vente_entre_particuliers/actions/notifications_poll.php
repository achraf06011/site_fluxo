<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";

if (session_status() === PHP_SESSION_NONE) session_start();

header("Content-Type: application/json; charset=UTF-8");

if (!isLoggedIn()) {
  echo json_encode([
    "ok" => false,
    "message" => "Non autorisé."
  ]);
  exit;
}

$uid = currentUserId();

function notifIconClassApi(string $type): array {
  $type = strtoupper(trim($type));

  return match ($type) {
    "NEW_ORDER"           => ["bi-bag-check-fill", "text-primary bg-primary-subtle"],
    "ORDER_UPDATE"        => ["bi-truck", "text-warning bg-warning-subtle"],
    "ORDER_DELIVERED"     => ["bi-check-circle-fill", "text-success bg-success-subtle"],
    "ANNONCE_VALIDATED"   => ["bi-check2-circle", "text-success bg-success-subtle"],
    "ANNONCE_REFUSED"     => ["bi-x-circle-fill", "text-danger bg-danger-subtle"],
    "ANNONCE_DELETED"     => ["bi-trash-fill", "text-danger bg-danger-subtle"],
    "NEW_MESSAGE"         => ["bi-chat-dots-fill", "text-info bg-info-subtle"],
    "NEW_REVIEW"          => ["bi-star-fill", "text-warning bg-warning-subtle"],
    "ADMIN_ANNONCE_WAIT"  => ["bi-hourglass-split", "text-warning bg-warning-subtle"],
    "ADMIN_NEW_ORDER"     => ["bi-receipt-cutoff", "text-primary bg-primary-subtle"],
    "ADMIN_NEW_USER"      => ["bi-person-plus-fill", "text-info bg-info-subtle"],
    "ORDER_STATUS"        => ["bi-truck", "text-warning bg-warning-subtle"],
    "SIGNALEMENT_ENVOYE"  => ["bi-flag-fill", "text-warning bg-warning-subtle"],
    default               => ["bi-bell-fill", "text-secondary bg-light"],
  };
}

function appBasePathApi(): string {
  $scriptDir = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? ""));
  $scriptDir = rtrim($scriptDir, "/");

  $pos = strpos($scriptDir, "/actions");
  if ($pos !== false) {
    $scriptDir = substr($scriptDir, 0, $pos);
  }

  if (str_ends_with($scriptDir, "/admin")) {
    $scriptDir = substr($scriptDir, 0, -6);
  }

  return $scriptDir !== "" ? $scriptDir : "";
}

function notifHrefApi(string $link): string {
  $link = trim($link);
  if ($link === "") return "notifications.php";

  if (preg_match('#^https?://#i', $link)) {
    return $link;
  }

  $base = appBasePathApi();

  while (str_starts_with($link, "../")) {
    $link = substr($link, 3);
  }

  $link = ltrim($link, "/");

  return $base . "/" . $link;
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

  $stmt = $pdo->prepare("
    SELECT *
    FROM notification
    WHERE id_user = ?
    ORDER BY id_notification DESC
    LIMIT 6
  ");
  $stmt->execute([$uid]);
  $notifList = $stmt->fetchAll();

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

  $items = [];
  foreach ($notifList as $n) {
    [$iconOnly, $extraClass] = notifIconClassApi((string)($n["type_notification"] ?? ""));
    $items[] = [
      "id_notification" => (int)$n["id_notification"],
      "titre" => (string)($n["titre"] ?? "Notification"),
      "contenu" => (string)($n["contenu"] ?? ""),
      "created_at" => substr((string)($n["created_at"] ?? ""), 0, 16),
      "is_read" => (int)($n["is_read"] ?? 0),
      "href" => notifHrefApi((string)($n["lien"] ?? "notifications.php")),
      "icon" => $iconOnly,
      "extra_class" => $extraClass,
    ];
  }

  $popup = null;
  if ($popupNotif) {
    [$popupIconOnly, $popupExtraClass] = notifIconClassApi((string)($popupNotif["type_notification"] ?? ""));
    $popup = [
      "id_notification" => (int)$popupNotif["id_notification"],
      "titre" => (string)($popupNotif["titre"] ?? "Notification"),
      "contenu" => (string)($popupNotif["contenu"] ?? ""),
      "href" => notifHrefApi((string)($popupNotif["lien"] ?? "notifications.php")),
      "icon" => $popupIconOnly,
      "extra_class" => $popupExtraClass,
    ];
  }

  echo json_encode([
    "ok" => true,
    "badge" => $notifBadge,
    "items" => $items,
    "popup" => $popup
  ]);
  exit;

} catch (Exception $e) {
  echo json_encode([
    "ok" => false,
    "message" => "Erreur serveur."
  ]);
  exit;
}