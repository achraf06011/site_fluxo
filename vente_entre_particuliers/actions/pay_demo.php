<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../index.php");
  exit;
}

$idOrder = (int)($_POST["id_order"] ?? 0);
if ($idOrder <= 0) {
  header("Location: ../index.php");
  exit;
}

$userId = currentUserId();

try {
  // Vérifier que la commande appartient à l'utilisateur
  $stmt = $pdo->prepare("SELECT * FROM orders WHERE id_order = ? AND id_user = ? LIMIT 1");
  $stmt->execute([$idOrder, $userId]);
  $o = $stmt->fetch();
  if (!$o) {
    $_SESSION["flash_error"] = "Commande introuvable.";
    header("Location: ../index.php");
    exit;
  }

  $pdo->beginTransaction();

  // passer commande à PAYE
  $pdo->prepare("UPDATE orders SET statut = 'PAYE' WHERE id_order = ?")->execute([$idOrder]);

  // paiement à ACCEPTE
  $pdo->prepare("UPDATE paiement SET statut = 'ACCEPTE', methode = 'STRIPE' WHERE id_order = ?")->execute([$idOrder]);

  $pdo->commit();

  $_SESSION["flash_success"] = "Paiement accepté (démo).";
  header("Location: ../checkout_success.php?id=" . $idOrder);
  exit;

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $_SESSION["flash_error"] = "Erreur: " . $e->getMessage();
  header("Location: ../checkout_success.php?id=" . $idOrder);
  exit;
}