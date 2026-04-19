<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();
$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

$q = trim($_GET["q"] ?? "");
$role = $_GET["role"] ?? "";
$statut = $_GET["statut"] ?? "";

$where = [];
$params = [];

if ($q !== "") {
  $where[] = "(u.nom LIKE ? OR u.email LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
}
if ($role !== "" && in_array($role, ["ADMIN","USER"], true)) {
  $where[] = "u.role = ?";
  $params[] = $role;
}
if ($statut !== "" && in_array($statut, ["ACTIVE","BLOQUE"], true)) {
  $where[] = "u.statut = ?";
  $params[] = $statut;
}

$sql = "
SELECT u.id_user, u.nom, u.email, u.role, u.statut
FROM user u
" . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
ORDER BY u.id_user DESC
LIMIT 300
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

include __DIR__ . "/../includes/header.php";
include __DIR__ . "/../includes/admin_navbar.php";
?>

<div class="container my-4" style="max-width: 1100px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h2 class="fw-bold mb-0">Utilisateurs</h2>

    <form class="d-flex gap-2" method="GET">
      <input class="form-control" name="q" placeholder="Nom ou email..." value="<?php echo htmlspecialchars($q); ?>" style="max-width:260px;">
      <select class="form-select" name="role" style="max-width:180px;">
        <option value="">Tous rôles</option>
        <option value="USER" <?php echo $role==="USER"?"selected":""; ?>>USER</option>
        <option value="ADMIN" <?php echo $role==="ADMIN"?"selected":""; ?>>ADMIN</option>
      </select>
      <select class="form-select" name="statut" style="max-width:180px;">
        <option value="">Tous statuts</option>
        <option value="ACTIVE" <?php echo $statut==="ACTIVE"?"selected":""; ?>>ACTIVE</option>
        <option value="BLOQUE" <?php echo $statut==="BLOQUE"?"selected":""; ?>>BLOQUE</option>
      </select>
      <button class="btn btn-dark">Filtrer</button>
    </form>
  </div>

  <?php if ($success): ?><div class="alert alert-success mt-3"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="card shadow-sm border-0 mt-3">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Nom</th>
              <th>Email</th>
              <th>Rôle</th>
              <th>Statut</th>
              <th style="width:360px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $u): ?>
              <?php
                $id = (int)$u["id_user"];
                $isMe = ($id === currentUserId());
                $roleU = $u["role"] ?? "USER";
                $stU = $u["statut"] ?? "ACTIVE";

                $badgeRole = $roleU === "ADMIN" ? "text-bg-danger" : "text-bg-secondary";
                $badgeSt   = $stU === "BLOQUE" ? "text-bg-warning" : "text-bg-success";
              ?>
              <tr>
                <td>#<?php echo $id; ?></td>
                <td class="fw-semibold"><?php echo htmlspecialchars($u["nom"]); ?></td>
                <td class="text-muted"><?php echo htmlspecialchars($u["email"]); ?></td>
                <td><span class="badge <?php echo $badgeRole; ?>"><?php echo htmlspecialchars($roleU); ?></span></td>
                <td><span class="badge <?php echo $badgeSt; ?>"><?php echo htmlspecialchars($stU); ?></span></td>
                <td class="d-flex gap-2 flex-wrap">

                  <!-- Toggle STATUS -->
                  <form action="../actions/admin_user_toggle_status.php" method="POST" class="d-inline">
                    <input type="hidden" name="id_user" value="<?php echo $id; ?>">
                    <?php if ($stU === "ACTIVE"): ?>
                      <button class="btn btn-outline-warning btn-sm" type="submit" <?php echo $isMe ? "disabled" : ""; ?>>
                        Bloquer
                      </button>
                    <?php else: ?>
                      <button class="btn btn-outline-success btn-sm" type="submit" <?php echo $isMe ? "disabled" : ""; ?>>
                        Activer
                      </button>
                    <?php endif; ?>
                  </form>

                  <!-- Toggle ROLE -->
                  <form action="../actions/admin_user_toggle_role.php" method="POST" class="d-inline">
                    <input type="hidden" name="id_user" value="<?php echo $id; ?>">
                    <?php if ($roleU === "USER"): ?>
                      <button class="btn btn-outline-danger btn-sm" type="submit" <?php echo $isMe ? "disabled" : ""; ?>>
                        Passer ADMIN
                      </button>
                    <?php else: ?>
                      <button class="btn btn-outline-secondary btn-sm" type="submit" <?php echo $isMe ? "disabled" : ""; ?>>
                        Revenir USER
                      </button>
                    <?php endif; ?>
                  </form>

                  <?php if ($isMe): ?>
                    <span class="text-muted small align-self-center">(toi)</span>
                  <?php endif; ?>

                </td>
              </tr>
            <?php endforeach; ?>

            <?php if (count($rows) === 0): ?>
              <tr><td colspan="6" class="text-center text-muted p-4">Aucun utilisateur.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <a class="btn btn-outline-dark mt-3" href="index.php">Retour Admin</a>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>