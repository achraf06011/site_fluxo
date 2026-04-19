<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = currentUserId();
$orderId = (int)($_GET["order"] ?? 0);

function e($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

$success = "";
$error = "";

if ($orderId > 0) {
  try {
    $stmt = $pdo->prepare("
      SELECT *
      FROM orders
      WHERE id_order = ? AND id_user = ?
      LIMIT 1
    ");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch();

    if ($order) {
      $stmt = $pdo->prepare("
        SELECT *
        FROM paiement
        WHERE id_order = ?
        LIMIT 1
      ");
      $stmt->execute([$orderId]);
      $pay = $stmt->fetch();

      $isAlreadyPaid = false;

      if ($order && (($order["statut"] ?? "") === "PAYE")) {
        $isAlreadyPaid = true;
      }

      if ($pay && (($pay["statut"] ?? "") === "ACCEPTE")) {
        $isAlreadyPaid = true;
      }

      if ($isAlreadyPaid) {
        header("Location: checkout_success.php?id=" . $orderId);
        exit;
      }

      $pdo->beginTransaction();

      $stmt = $pdo->prepare("
        SELECT od.id_annonce, od.quantite
        FROM order_details od
        WHERE od.id_order = ?
      ");
      $stmt->execute([$orderId]);
      $details = $stmt->fetchAll();

      // restaurer le panier utilisateur
      $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE id_user = ? LIMIT 1");
      $stmt->execute([$userId]);
      $panier = $stmt->fetch();

      if ($panier) {
        $panierId = (int)$panier["id_panier"];
      } else {
        $stmt = $pdo->prepare("INSERT INTO panier (id_user) VALUES (?)");
        $stmt->execute([$userId]);
        $panierId = (int)$pdo->lastInsertId();
      }

      // remettre le stock + remettre les articles dans le panier
      foreach ($details as $d) {
        $idAnnonce = (int)$d["id_annonce"];
        $qty = (int)$d["quantite"];

        if ($qty > 0) {
          $stmt = $pdo->prepare("
            UPDATE annonce
            SET stock = stock + ?
            WHERE id_annonce = ?
          ");
          $stmt->execute([$qty, $idAnnonce]);

          $stmt = $pdo->prepare("
            SELECT id_panier_item, quantity
            FROM panier_item
            WHERE id_panier = ? AND id_annonce = ?
            LIMIT 1
          ");
          $stmt->execute([$panierId, $idAnnonce]);
          $existing = $stmt->fetch();

          if ($existing) {
            $newQty = (int)$existing["quantity"] + $qty;

            $stmt = $pdo->prepare("
              UPDATE panier_item
              SET quantity = ?
              WHERE id_panier_item = ?
            ");
            $stmt->execute([$newQty, (int)$existing["id_panier_item"]]);
          } else {
            $stmt = $pdo->prepare("
              INSERT INTO panier_item (id_panier, id_annonce, quantity)
              VALUES (?, ?, ?)
            ");
            $stmt->execute([$panierId, $idAnnonce, $qty]);
          }
        }
      }

      // supprimer paiement, détails, commande
      $stmt = $pdo->prepare("DELETE FROM paiement WHERE id_order = ?");
      $stmt->execute([$orderId]);

      $stmt = $pdo->prepare("DELETE FROM order_details WHERE id_order = ?");
      $stmt->execute([$orderId]);

      $stmt = $pdo->prepare("DELETE FROM orders WHERE id_order = ? AND id_user = ?");
      $stmt->execute([$orderId, $userId]);

      $pdo->commit();

      $success = "Paiement annulé. Le panier et le stock ont été restaurés.";
    } else {
      $error = "Commande introuvable ou déjà supprimée.";
    }
  } catch (Exception $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    $error = "Erreur lors de l’annulation : " . $e->getMessage();
  }
} else {
  $error = "Commande invalide.";
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<div class="container my-5" style="max-width: 920px;">
  <div class="card shadow-sm border-0">
    <div class="card-body p-4">
      <h2 class="fw-bold mb-2"><i class="bi bi-x-circle"></i> Paiement annulé</h2>

      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
      <?php endif; ?>

      <p class="text-muted">
        Tu peux revenir au panier et relancer le paiement quand tu veux.
      </p>

      <div class="d-grid gap-2">
        <a class="btn btn-dark" href="panier.php">Retour panier</a>
        <a class="btn btn-outline-secondary" href="checkout.php">Retour checkout</a>
        <a class="btn btn-outline-secondary" href="index.php">Retour annonces</a>
      </div>
    </div>
  </div>
</div>

<?php include "includes/footer.php"; ?>