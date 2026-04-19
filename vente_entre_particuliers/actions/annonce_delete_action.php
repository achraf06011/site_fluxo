<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../mes_annonces.php");
  exit;
}

$userId = currentUserId();
$id = (int)($_POST["id_annonce"] ?? 0);

function back(string $msg, bool $ok = false): void {
  if ($ok) {
    $_SESSION["flash_success"] = $msg;
  } else {
    $_SESSION["flash_error"] = $msg;
  }

  header("Location: ../mes_annonces.php");
  exit;
}

if ($id <= 0) {
  back("Annonce invalide.");
}

try {
  $pdo->beginTransaction();

  // Vérifier que l'annonce appartient bien au vendeur connecté
  $stmt = $pdo->prepare("
    SELECT id_annonce, titre, statut, cover_image
    FROM annonce
    WHERE id_annonce = ? AND id_vendeur = ?
    LIMIT 1
  ");
  $stmt->execute([$id, $userId]);
  $ann = $stmt->fetch();

  if (!$ann) {
    $pdo->rollBack();
    back("Annonce introuvable.");
  }

  // Vérifier si l'annonce est déjà liée à une commande
  $stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM order_details
    WHERE id_annonce = ?
  ");
  $stmt->execute([$id]);
  $usedInOrders = (int)$stmt->fetchColumn() > 0;

  // Si déjà commandée => désactivation au lieu de suppression
  if ($usedInOrders) {
    $stmt = $pdo->prepare("
      UPDATE annonce
      SET statut = 'DESACTIVEE'
      WHERE id_annonce = ? AND id_vendeur = ?
      LIMIT 1
    ");
    $stmt->execute([$id, $userId]);

    // Retirer du panier au cas où
    try {
      $pdo->prepare("DELETE FROM panier_item WHERE id_annonce = ?")->execute([$id]);
    } catch (Exception $e) {}

    $pdo->commit();
    back("Annonce désactivée car elle est déjà liée à une commande.", true);
  }

  // --- sinon suppression complète ---
  $stmt = $pdo->prepare("
    SELECT id_image, url
    FROM annonce_image
    WHERE id_annonce = ?
  ");
  $stmt->execute([$id]);
  $images = $stmt->fetchAll();

  // dépendances supprimables
  try {
    $pdo->prepare("DELETE FROM annonce_image WHERE id_annonce = ?")->execute([$id]);
  } catch (Exception $e) {}

  try {
    $pdo->prepare("DELETE FROM review WHERE id_annonce = ?")->execute([$id]);
  } catch (Exception $e) {}

  try {
    $pdo->prepare("DELETE FROM message WHERE id_annonce = ?")->execute([$id]);
  } catch (Exception $e) {}

  try {
    $pdo->prepare("DELETE FROM panier_item WHERE id_annonce = ?")->execute([$id]);
  } catch (Exception $e) {}

  $stmt = $pdo->prepare("
    DELETE FROM annonce
    WHERE id_annonce = ? AND id_vendeur = ?
    LIMIT 1
  ");
  $stmt->execute([$id, $userId]);

  $pdo->commit();

  // supprimer fichiers après commit
  $cover = $ann["cover_image"] ?? null;
  if (!empty($cover) && !str_starts_with($cover, "http")) {
    $coverAbs = __DIR__ . "/../uploads/" . $cover;
    if (is_file($coverAbs)) {
      @unlink($coverAbs);
    }
  }

  foreach ($images as $img) {
    $url = (string)($img["url"] ?? "");
    $rel = ltrim($url, "/");
    if (str_starts_with($rel, "uploads/")) {
      $abs = __DIR__ . "/../" . $rel;
      if (is_file($abs)) {
        @unlink($abs);
      }
    }
  }

  back("Annonce supprimée avec succès.", true);

} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  back("Erreur suppression : " . $e->getMessage());
}