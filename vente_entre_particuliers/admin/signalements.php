<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireLogin();

if (currentUserRole() !== "ADMIN") {
  header("Location: ../index.php");
  exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

$statut = trim((string)($_GET["statut"] ?? ""));

$where = [];
$params = [];

if ($statut !== "") {
  $where[] = "s.statut = ?";
  $params[] = $statut;
}

$sql = "
  SELECT
    s.*,
    a.titre AS annonce_titre,
    a.statut AS annonce_statut,
    a.id_vendeur,
    u.nom AS signaleur_nom,
    u.email AS signaleur_email,
    v.nom AS vendeur_nom,
    v.email AS vendeur_email,
    adminu.nom AS admin_nom
  FROM signalement s
  JOIN annonce a ON a.id_annonce = s.id_annonce
  JOIN user u ON u.id_user = s.id_user
  JOIN user v ON v.id_user = a.id_vendeur
  LEFT JOIN user adminu ON adminu.id_user = s.treated_by
";

if (count($where) > 0) {
  $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY s.id_signalement DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

function e($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

function badgeSignalement(string $st): string {
  return match ($st) {
    "EN_ATTENTE" => "text-bg-warning",
    "TRAITE" => "text-bg-success",
    "REJETE" => "text-bg-secondary",
    default => "text-bg-secondary",
  };
}

include __DIR__ . "/../includes/header.php";
include __DIR__ . "/../includes/admin_navbar.php";
?>

<div class="container my-4" style="max-width: 1280px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h2 class="fw-bold mb-0"><i class="bi bi-flag"></i> Signalements</h2>
      <div class="text-muted">Gestion des annonces signalées par les utilisateurs.</div>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo e($success); ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo e($error); ?></div>
  <?php endif; ?>

  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
          <label class="form-label">Statut</label>
          <select name="statut" class="form-select">
            <option value="" <?php echo $statut === "" ? "selected" : ""; ?>>Tous</option>
            <option value="EN_ATTENTE" <?php echo $statut === "EN_ATTENTE" ? "selected" : ""; ?>>EN_ATTENTE</option>
            <option value="TRAITE" <?php echo $statut === "TRAITE" ? "selected" : ""; ?>>TRAITE</option>
            <option value="REJETE" <?php echo $statut === "REJETE" ? "selected" : ""; ?>>REJETE</option>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <button class="btn btn-dark w-100" type="submit">Filtrer</button>
        </div>

        <div class="col-12 col-md-4">
          <a class="btn btn-outline-secondary w-100" href="signalements.php">Réinitialiser</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body p-0">
      <?php if (count($rows) === 0): ?>
        <div class="p-4">
          <div class="alert alert-warning mb-0">Aucun signalement.</div>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Annonce</th>
                <th>Motif</th>
                <th>Signalé par</th>
                <th>Vendeur</th>
                <th>Statut</th>
                <th>Date</th>
                <th>Description</th>
                <th>Traitement</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td>#<?php echo (int)$r["id_signalement"]; ?></td>

                  <td>
                    <div class="fw-semibold"><?php echo e($r["annonce_titre"]); ?></div>
                    <div class="small text-muted">Annonce #<?php echo (int)$r["id_annonce"]; ?></div>
                  </td>

                  <td>
                    <span class="badge text-bg-danger"><?php echo e($r["motif"]); ?></span>
                  </td>

                  <td>
                    <div class="fw-semibold"><?php echo e($r["signaleur_nom"]); ?></div>
                    <div class="small text-muted"><?php echo e($r["signaleur_email"]); ?></div>
                  </td>

                  <td>
                    <div class="fw-semibold"><?php echo e($r["vendeur_nom"]); ?></div>
                    <div class="small text-muted"><?php echo e($r["vendeur_email"]); ?></div>
                  </td>

                  <td>
                    <span class="badge <?php echo badgeSignalement((string)$r["statut"]); ?>">
                      <?php echo e($r["statut"]); ?>
                    </span>
                  </td>

                  <td><?php echo e($r["created_at"]); ?></td>

                  <td style="min-width:220px;">
                    <div class="small text-muted">
                      <?php echo nl2br(e($r["description"] ?: "Aucune description.")); ?>
                    </div>
                  </td>

                  <td style="min-width:180px;">
                    <div class="small">
                      Admin : <b><?php echo e($r["admin_nom"] ?: "—"); ?></b><br>
                      Date : <b><?php echo e($r["treated_at"] ?: "—"); ?></b><br>
                      Note : <b><?php echo e($r["admin_note"] ?: "—"); ?></b>
                    </div>
                  </td>

                  <td class="text-end" style="min-width:260px;">
                    <div class="d-flex gap-2 flex-wrap justify-content-end">
                      <a class="btn btn-sm btn-outline-primary" href="../annonce.php?id=<?php echo (int)$r["id_annonce"]; ?>&from=admin_annonces">
                        Voir annonce
                      </a>

                      <?php if ((string)$r["statut"] === "EN_ATTENTE"): ?>
                        <form action="signalement_update_action.php" method="POST" class="d-inline">
                          <input type="hidden" name="id_signalement" value="<?php echo (int)$r["id_signalement"]; ?>">
                          <input type="hidden" name="new_statut" value="TRAITE">
                          <button class="btn btn-sm btn-success" type="submit">Traiter</button>
                        </form>

                        <form action="signalement_update_action.php" method="POST" class="d-inline">
                          <input type="hidden" name="id_signalement" value="<?php echo (int)$r["id_signalement"]; ?>">
                          <input type="hidden" name="new_statut" value="REJETE">
                          <button class="btn btn-sm btn-secondary" type="submit">Rejeter</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>