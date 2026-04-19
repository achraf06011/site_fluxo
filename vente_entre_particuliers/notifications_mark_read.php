<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

header("Content-Type: application/json; charset=UTF-8");

$userId = currentUserId();

try {
  $stmt = $pdo->prepare("
    UPDATE notification
    SET is_read = 1
    WHERE id_user = ?
      AND COALESCE(is_read, 0) = 0
  ");
  $stmt->execute([$userId]);

  echo json_encode([
    "ok" => true
  ]);
} catch (Exception $e) {
  echo json_encode([
    "ok" => false
  ]);
}