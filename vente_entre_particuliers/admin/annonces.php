<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

$filter = $_GET["statut"] ?? "TOUS";
$allowed = ["TOUS","EN_ATTENTE_VALIDATION","ACTIVE","REFUSEE","VENDUE","EXPIREE","DESACTIVEE"];
if (!in_array($filter, $allowed, true)) $filter = "TOUS";

$where = "";
$params = [];

if ($filter === "DESACTIVEE") {
  $where = "WHERE (a.statut = 'DESACTIVEE' OR a.statut IS NULL OR a.statut = '')";
} elseif ($filter !== "TOUS") {
  $where = "WHERE a.statut = ?";
  $params[] = $filter;
}

function e($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

function coverUrl($a) {
  $file = $a["cover_image"] ?? null;
  if (!$file) return "https://picsum.photos/seed/" . (int)$a["id_annonce"] . "/300/200";
  if (str_starts_with($file, "http")) return $file;
  return "../uploads/" . $file;
}

$stmt = $pdo->prepare("
  SELECT a.*, u.nom AS vendeur_nom, u.email AS vendeur_email
  FROM annonce a
  JOIN user u ON u.id_user = a.id_vendeur
  $where
  ORDER BY COALESCE(a.date_modification, a.date_publication) DESC, a.id_annonce DESC
  LIMIT 200
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

include __DIR__ . "/../includes/header.php";
include __DIR__ . "/../includes/admin_navbar.php";
?>

<div class="container my-4" style="max-width: 1200px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h2 class="fw-bold mb-0">Gérer Annonces</h2>

    <form class="d-flex gap-2" method="GET">
      <select class="form-select" name="statut">
        <?php foreach ($allowed as $st): ?>
          <option value="<?php echo e($st); ?>" <?php echo $st === $filter ? "selected" : ""; ?>>
            <?php echo e($st); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-dark" type="submit">Filtrer</button>
    </form>
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
              <th>Vendeur</th>
              <th>Statut</th>
              <th>Modif</th>
              <th style="width:380px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $a): ?>
              <?php
                $isMod = (int)($a["is_modifiee"] ?? 0) === 1;
                $modDate = $a["date_modification"] ?? null;
                $idA = (int)$a["id_annonce"];

                $rawStatut = trim((string)($a["statut"] ?? ""));
                $st = $rawStatut !== "" ? $rawStatut : "DESACTIVEE";

                $badge = "text-bg-secondary";
                if ($st === "ACTIVE") $badge = "text-bg-success";
                elseif ($st === "EN_ATTENTE_VALIDATION") $badge = "text-bg-warning";
                elseif ($st === "REFUSEE") $badge = "text-bg-danger";
                elseif ($st === "VENDUE") $badge = "text-bg-primary";
                elseif ($st === "EXPIREE") $badge = "text-bg-dark";
                elseif ($st === "DESACTIVEE") $badge = "text-bg-secondary";
              ?>
              <tr>
                <td>
                  <img
                    src="<?php echo e(coverUrl($a)); ?>"
                    alt=""
                    style="width:72px;height:54px;object-fit:cover;border-radius:10px;border:1px solid #eee;"
                  >
                </td>

                <td>#<?php echo $idA; ?></td>

                <td>
                  <div class="fw-semibold">
                    <?php echo e($a["titre"]); ?>
                    <?php if ($isMod): ?>
                      <span class="badge text-bg-warning ms-1">Modifiée</span>
                    <?php endif; ?>
                  </div>
                  <div class="text-muted small">
                    <?php echo e($a["type"] ?? ""); ?> · <?php echo e($a["mode_vente"] ?? ""); ?>
                  </div>
                </td>

                <td><?php echo number_format((float)$a["prix"], 2); ?> DH</td>

                <td>
                  <div><?php echo e($a["vendeur_nom"]); ?></div>
                  <div class="text-muted small"><?php echo e($a["vendeur_email"]); ?></div>
                </td>

                <td>
                  <span class="badge <?php echo $badge; ?>">
                    <?php echo e($st); ?>
                  </span>
                </td>

                <td class="small text-muted">
                  <?php echo $modDate ? e(substr((string)$modDate, 0, 16)) : "—"; ?>
                </td>

                <td>
                  <div class="d-flex gap-2 flex-wrap align-items-center">
                    <a
                      class="btn btn-outline-secondary btn-sm"
                      target="_blank"
                      href="../annonce.php?id=<?php echo $idA; ?>&from=admin_annonces"
                    >
                      Voir
                    </a>

                    <?php if ($st === "EN_ATTENTE_VALIDATION"): ?>
                      <form action="../actions/admin_annonce_validate.php" method="POST" class="d-inline">
                        <input type="hidden" name="id_annonce" value="<?php echo $idA; ?>">
                        <button class="btn btn-success btn-sm" type="submit">Valider</button>
                      </form>

                      <form action="../actions/admin_annonce_refuse.php" method="POST" class="d-inline d-flex gap-1">
                        <input type="hidden" name="id_annonce" value="<?php echo $idA; ?>">
                        <input
                          type="text"
                          name="reason"
                          class="form-control form-control-sm"
                          style="width:130px;"
                          placeholder="Raison"
                        >
                        <button class="btn btn-danger btn-sm" type="submit">Refuser</button>
                      </form>
                    <?php endif; ?>

                    <?php if ($st === "DESACTIVEE"): ?>
                      <form action="../actions/admin_annonce_reactivate.php" method="POST" class="d-inline">
                        <input type="hidden" name="id_annonce" value="<?php echo $idA; ?>">
                        <button class="btn btn-outline-success btn-sm" type="submit">
                          Réactiver
                        </button>
                      </form>
                    <?php endif; ?>

                    <form
                      action="../actions/admin_annonce_delete.php"
                      method="POST"
                      class="d-inline"
                      onsubmit="return confirm('Supprimer ou désactiver cette annonce ?');"
                    >
                      <input type="hidden" name="id_annonce" value="<?php echo $idA; ?>">
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

  <a class="btn btn-outline-dark mt-3" href="index.php">Retour Admin</a>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>