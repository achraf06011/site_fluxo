<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

$userId = currentUserId();

// récupérer/ créer panier
$stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE id_user = ? LIMIT 1");
$stmt->execute([$userId]);
$p = $stmt->fetch();
$panierId = $p ? (int)$p["id_panier"] : 0;

$items = [];
$total = 0.0;

if ($panierId) {
  $stmt = $pdo->prepare("
    SELECT pi.id_panier_item, pi.quantity,
           a.id_annonce, a.titre, a.prix, a.stock, a.cover_image
    FROM panier_item pi
    JOIN annonce a ON a.id_annonce = pi.id_annonce
    WHERE pi.id_panier = ?
    ORDER BY pi.id_panier_item DESC
  ");
  $stmt->execute([$panierId]);
  $items = $stmt->fetchAll();

  foreach ($items as $it) {
    $total += ((float)$it["prix"]) * ((int)$it["quantity"]);
  }
}

function coverUrl($x) {
  $file = $x["cover_image"] ?? null;
  if (!$file) return "https://picsum.photos/seed/" . $x["id_annonce"] . "/800/600";
  if (str_starts_with($file, "http")) return $file;
  return "uploads/" . $file;
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<div class="container my-4" style="max-width: 980px;">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="fw-bold mb-0"><i class="bi bi-cart3"></i> Mon panier</h2>
    <a class="btn btn-outline-secondary" href="index.php">Continuer mes achats</a>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <?php if (count($items) === 0): ?>
    <div class="alert alert-warning">Ton panier est vide.</div>
  <?php else: ?>

    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>Produit</th>
                <th>Prix</th>
                <th style="width:180px;">Quantité</th>
                <th>Sous-total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <?php
                  $sub = ((float)$it["prix"]) * ((int)$it["quantity"]);
                  $max = max(1, (int)$it["stock"]);
                ?>
                <tr>
                  <td>
                    <div class="d-flex gap-3 align-items-center">
                      <img src="<?php echo htmlspecialchars(coverUrl($it)); ?>" style="width:70px;height:52px;object-fit:cover;border-radius:10px" alt="">
                      <div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($it["titre"]); ?></div>
                        <div class="text-muted small">Stock: <?php echo (int)$it["stock"]; ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="fw-semibold"><?php echo number_format((float)$it["prix"], 2); ?> DH</td>
                  <td>
                    <form class="d-flex gap-2" method="POST" action="actions/panier_action.php?action=update&id=<?php echo (int)$it["id_annonce"]; ?>">
                      <input class="form-control form-control-sm" type="number" name="qty" min="1" max="<?php echo $max; ?>" value="<?php echo (int)$it["quantity"]; ?>">
                      <button class="btn btn-sm btn-outline-primary" type="submit">OK</button>
                    </form>
                  </td>
                  <td class="fw-semibold"><?php echo number_format($sub, 2); ?> DH</td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-danger"
                       href="actions/panier_action.php?action=delete&id=<?php echo (int)$it["id_annonce"]; ?>"
                       onclick="return confirm('Supprimer cet article ?')">
                      <i class="bi bi-trash"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="p-3 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center border-top">
          <a class="btn btn-outline-danger" href="actions/panier_action.php?action=clear" onclick="return confirm('Vider le panier ?')">
            Vider le panier
          </a>
          <div class="d-flex gap-2 align-items-center">
            <div class="fs-5 fw-bold">Total: <?php echo number_format($total, 2); ?> DH</div>
            <a class="btn btn-dark" href="checkout.php">Passer commande</a>
          </div>
        </div>

      </div>
    </div>

  <?php endif; ?>
</div>

<?php include "includes/footer.php"; ?>