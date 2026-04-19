<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = currentUserId();
$id = (int)($_GET["id"] ?? 0);

if ($id > 0) {
  try {
    $stmt = $pdo->prepare("
      UPDATE notification
      SET is_popup_seen = 1
      WHERE id_notification = ? AND id_user = ?
    ");
    $stmt->execute([$id, $userId]);
  } catch (Exception $e) {}
}

http_response_code(204);
exit;