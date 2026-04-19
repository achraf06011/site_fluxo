<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = currentUserId();

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

$stmt = $pdo->prepare("
  SELECT
    id_annonce,
    titre,
    prix,
    ancien_prix,
    stock,
    statut,
    date_publication,
    cover_image,
    COALESCE(nb_vues, 0) AS nb_vues
  FROM annonce
  WHERE id_vendeur = ?
  ORDER BY id_annonce DESC
  LIMIT 200
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll();

function e($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

function coverUrl($a) {
  $file = $a["cover_image"] ?? null;
  if (!$file) return null;
  if (str_starts_with($file, "http")) return $file;
  return "uploads/" . $file;
}

function statutBadge(string $statut): string {
  return match ($statut) {
    "ACTIVE" => "text-bg-success",
    "EN_ATTENTE_VALIDATION" => "text-bg-warning",
    "REFUSEE" => "text-bg-danger",
    "VENDUE" => "text-bg-primary",
    "EXPIREE" => "text-bg-dark",
    "DESACTIVEE" => "text-bg-secondary",
    "", "NULL" => "text-bg-secondary",
    default => "text-bg-secondary",
  };
}

function hasPromo(array $a): bool {
  return isset($a["ancien_prix"]) && $a["ancien_prix"] !== null && (float)$a["ancien_prix"] > (float)$a["prix"];
}

include "includes/header.php";
include "includes/navbar.php";
?>

<div class="container my-4" style="max-width:1150px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h2 class="fw-bold mb-0"><i class="bi bi-megaphone"></i> Mes annonces</h2>
    <a class="btn btn-outline-secondary" href="profil.php">Retour profil</a>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success mt-3"><?php echo e($success); ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger mt-3"><?php echo e($error); ?></div>
  <?php endif; ?>

  <div class="card shadow-sm border-0 mt-3">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:90px;">Photo</th>
              <th>ID</th>
              <th>Titre</th>
              <th>Prix</th>
              <th>Stock</th>
              <th>Vues</th>
              <th>Statut</th>
              <th style="width:250px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $a): ?>
              <?php
                $img = coverUrl($a);
                $st = trim((string)($a["statut"] ?? ""));
                if ($st === "") $st = "DESACTIVEE";
                $badge = statutBadge($st);
              ?>
              <tr>
                <td>
                  <?php if ($img): ?>
                    <img
                      src="<?php echo e($img); ?>"
                      alt=""
                      style="width:72px;height:54px;object-fit:cover;border-radius:10px;"
                    >
                  <?php else: ?>
                    <div class="d-flex flex-column gap-1">
                      <div class="text-muted small">Aucune</div>
                      <span class="badge text-bg-warning">Photo requise</span>
                    </div>
                  <?php endif; ?>
                </td>

                <td>#<?php echo (int)$a["id_annonce"]; ?></td>
                <td><?php echo e($a["titre"]); ?></td>

                <td>
                  <?php if (hasPromo($a)): ?>
                    <div class="small text-muted text-decoration-line-through">
                      <?php echo number_format((float)$a["ancien_prix"], 2); ?> DH
                    </div>
                    <div class="fw-bold text-danger">
                      <?php echo number_format((float)$a["prix"], 2); ?> DH
                    </div>
                  <?php else: ?>
                    <?php echo number_format((float)$a["prix"], 2); ?> DH
                  <?php endif; ?>
                </td>

                <td><?php echo (int)$a["stock"]; ?></td>
                <td>
                  <span class="small">
                    <i class="bi bi-eye"></i> <?php echo (int)$a["nb_vues"]; ?>
                  </span>
                </td>
                <td>
                  <span class="badge <?php echo e($badge); ?>">
                    <?php echo e($st); ?>
                  </span>
                </td>

                <td>
                  <div class="d-flex gap-2 flex-wrap">
                    <a
                      class="btn btn-outline-dark btn-sm"
                      href="annonce_edit.php?id=<?php echo (int)$a["id_annonce"]; ?>"
                    >
                      Modifier
                    </a>

                    <form
                      action="actions/annonce_delete_action.php"
                      method="POST"
                      class="d-inline"
                      onsubmit="return confirm('Supprimer cette annonce ? Si elle a déjà été commandée, elle sera désactivée.');"
                    >
                      <input type="hidden" name="id_annonce" value="<?php echo (int)$a["id_annonce"]; ?>">
                      <button class="btn btn-outline-danger btn-sm" type="submit">
                        Supprimer
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>

            <?php if (count($rows) === 0): ?>
              <tr>
                <td colspan="8" class="text-center text-muted p-4">Aucune annonce.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include "includes/footer.php"; ?>