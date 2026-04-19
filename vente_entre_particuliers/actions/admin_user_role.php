<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../admin/users.php");
  exit;
}

$id = (int)($_POST["id_user"] ?? 0);
$role = $_POST["role"] ?? "USER";
if ($id <= 0) { $_SESSION["flash_error"] = "ID invalide."; header("Location: ../admin/users.php"); exit; }
if (!in_array($role, ["ADMIN","USER"], true)) { $_SESSION["flash_error"] = "Rôle invalide."; header("Location: ../admin/users.php"); exit; }

// éviter de te retirer admin à toi-même
if ($id === (int)currentUserId() && $role !== "ADMIN") {
  $_SESSION["flash_error"] = "Tu ne peux pas retirer ton propre rôle ADMIN.";
  header("Location: ../admin/users.php");
  exit;
}

try {
  $stmt = $pdo->prepare("UPDATE user SET role = ? WHERE id_user = ?");
  $stmt->execute([$role, $id]);

  $_SESSION["flash_success"] = "Rôle utilisateur #$id => $role";
  header("Location: ../admin/users.php");
  exit;

} catch (Exception $e) {
  $_SESSION["flash_error"] = "Erreur serveur: " . $e->getMessage();
  header("Location: ../admin/users.php");
  exit;
}