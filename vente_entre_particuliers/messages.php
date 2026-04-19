<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = currentUserId();

$flashSuccess = $_SESSION["flash_success"] ?? "";
$flashError   = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

$annonceId = (int)($_GET["annonce"] ?? 0);
$toId      = (int)($_GET["to"] ?? 0);

function e($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

$hasReadCols = false;
try {
  $cols = $pdo->query("SHOW COLUMNS FROM message")->fetchAll(PDO::FETCH_COLUMN, 0);
  $hasReadCols = in_array("is_lu", $cols, true) && in_array("date_lu", $cols, true);
} catch (Exception $e) {
  $hasReadCols = false;
}

$totalUnread = 0;
if ($hasReadCols) {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM message WHERE id_destinataire = ? AND is_lu = 0");
  $stmt->execute([$userId]);
  $totalUnread = (int)$stmt->fetchColumn();
}

if ($hasReadCols) {
  $sqlConv = "
    SELECT
      m.id_annonce,
      CASE
        WHEN m.id_expediteur = ? THEN m.id_destinataire
        ELSE m.id_expediteur
      END AS other_id,
      MAX(m.date_envoi) AS last_date,
      SUM(CASE WHEN m.id_destinataire = ? AND m.is_lu = 0 THEN 1 ELSE 0 END) AS unread_count
    FROM message m
    WHERE m.id_expediteur = ? OR m.id_destinataire = ?
    GROUP BY m.id_annonce, other_id
    ORDER BY last_date DESC
    LIMIT 80
  ";
  $stmt = $pdo->prepare($sqlConv);
  $stmt->execute([$userId, $userId, $userId, $userId]);
} else {
  $sqlConv = "
    SELECT
      m.id_annonce,
      CASE
        WHEN m.id_expediteur = ? THEN m.id_destinataire
        ELSE m.id_expediteur
      END AS other_id,
      MAX(m.date_envoi) AS last_date,
      0 AS unread_count
    FROM message m
    WHERE m.id_expediteur = ? OR m.id_destinataire = ?
    GROUP BY m.id_annonce, other_id
    ORDER BY last_date DESC
    LIMIT 80
  ";
  $stmt = $pdo->prepare($sqlConv);
  $stmt->execute([$userId, $userId, $userId]);
}

$convsRaw = $stmt->fetchAll();
$convs = [];

if ($convsRaw) {
  foreach ($convsRaw as $c) {
    $otherId = (int)$c["other_id"];
    $aId     = (int)$c["id_annonce"];

    $stmt2 = $pdo->prepare("
      SELECT m.*,
             a.titre AS annonce_titre,
             a.mode_vente,
             a.statut AS annonce_statut,
             u.nom AS other_nom
      FROM message m
      JOIN annonce a ON a.id_annonce = m.id_annonce
      JOIN user u ON u.id_user = ?
      WHERE m.id_annonce = ?
        AND (
          (m.id_expediteur = ? AND m.id_destinataire = ?)
          OR
          (m.id_expediteur = ? AND m.id_destinataire = ?)
        )
      ORDER BY m.date_envoi DESC, m.id_message DESC
      LIMIT 1
    ");
    $stmt2->execute([$otherId, $aId, $userId, $otherId, $otherId, $userId]);
    $last = $stmt2->fetch();

    if ($last) {
      $convs[] = [
        "id_annonce" => $aId,
        "other_id" => $otherId,
        "other_nom" => $last["other_nom"] ?? ("User#" . $otherId),
        "annonce_titre" => $last["annonce_titre"] ?? ("Annonce#" . $aId),
        "annonce_statut" => $last["annonce_statut"] ?? "",
        "mode_vente" => $last["mode_vente"] ?? "",
        "last_date" => $last["date_envoi"] ?? "",
        "last_contenu" => $last["contenu"] ?? "",
        "unread_count" => (int)($c["unread_count"] ?? 0),
      ];
    }
  }
}

$thread = [];
$annonce = null;
$other = null;
$canChat = false;

if ($annonceId > 0 && $toId > 0) {
  $stmt = $pdo->prepare("SELECT * FROM annonce WHERE id_annonce = ? LIMIT 1");
  $stmt->execute([$annonceId]);
  $annonce = $stmt->fetch();

  $stmt = $pdo->prepare("SELECT id_user, nom FROM user WHERE id_user = ? LIMIT 1");
  $stmt->execute([$toId]);
  $other = $stmt->fetch();

  if ($annonce) {
    $mode = $annonce["mode_vente"] ?? "";
    $canChat = in_array($mode, ["POSSIBILITE_CONTACTE", "LES_DEUX"], true);
  }

  if ($hasReadCols) {
    $stmt = $pdo->prepare("
      UPDATE message
      SET is_lu = 1, date_lu = NOW()
      WHERE id_annonce = ?
        AND id_expediteur = ?
        AND id_destinataire = ?
        AND is_lu = 0
    ");
    $stmt->execute([$annonceId, $toId, $userId]);
  }

  $stmt = $pdo->prepare("
    SELECT m.*
    FROM message m
    WHERE m.id_annonce = ?
      AND (
        (m.id_expediteur = ? AND m.id_destinataire = ?)
        OR
        (m.id_expediteur = ? AND m.id_destinataire = ?)
      )
    ORDER BY m.date_envoi ASC, m.id_message ASC
    LIMIT 300
  ");
  $stmt->execute([$annonceId, $userId, $toId, $toId, $userId]);
  $thread = $stmt->fetchAll();
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<div class="container my-4" style="max-width: 1100px;">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h2 class="fw-bold mb-0"><i class="bi bi-chat-dots"></i> Messages</h2>
    <div class="text-muted small">
      Non lus: <b><?php echo (int)$totalUnread; ?></b>
    </div>
  </div>

  <?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?php echo e($flashSuccess); ?></div>
  <?php endif; ?>
  <?php if ($flashError): ?>
    <div class="alert alert-danger"><?php echo e($flashError); ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h5 class="fw-bold mb-3">Conversations</h5>

          <?php if (count($convs) === 0): ?>
            <div class="alert alert-warning mb-0">Aucune conversation.</div>
          <?php else: ?>
            <div class="list-group">
              <?php foreach ($convs as $c): ?>
                <?php
                  $active = ($annonceId === (int)$c["id_annonce"] && $toId === (int)$c["other_id"]);
                  $href = "messages.php?annonce=" . (int)$c["id_annonce"] . "&to=" . (int)$c["other_id"];
                  $unread = (int)$c["unread_count"];
                ?>
                <a class="list-group-item list-group-item-action <?php echo $active ? "active" : ""; ?>" href="<?php echo e($href); ?>">
                  <div class="d-flex justify-content-between align-items-start gap-2">
                    <div style="min-width:0;">
                      <div class="d-flex align-items-center gap-2">
                        <div class="fw-semibold"><?php echo e($c["other_nom"]); ?></div>
                        <?php if ($unread > 0): ?>
                          <span class="badge rounded-pill <?php echo $active ? "text-bg-light" : "text-bg-danger"; ?>">
                            <?php echo $unread; ?>
                          </span>
                        <?php endif; ?>
                      </div>

                      <div class="small <?php echo $active ? "text-white-50" : "text-muted"; ?>">
                        <?php echo e($c["annonce_titre"]); ?>
                      </div>
                      <div class="small <?php echo $active ? "text-white-50" : "text-muted"; ?>">
                        <?php echo e(mb_strimwidth($c["last_contenu"], 0, 46, "…")); ?>
                      </div>
                    </div>
                    <div class="small <?php echo $active ? "text-white-50" : "text-muted"; ?>">
                      <?php echo e(substr((string)$c["last_date"], 0, 16)); ?>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <?php if (!$annonceId || !$toId): ?>
            <div class="alert alert-info mb-0">
              Choisis une conversation à gauche, ou clique sur “Message” depuis une annonce.
            </div>
          <?php else: ?>

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
              <div>
                <div class="fw-bold">
                  Discussion avec
                  <a
                    href="vendeur.php?id=<?php echo (int)$toId; ?>"
                    class="text-decoration-none"
                  >
                    <?php echo e($other["nom"] ?? ("User#" . $toId)); ?>
                  </a>
                </div>
                <div class="text-muted small">
                  Annonce: <b><?php echo e($annonce["titre"] ?? ("Annonce#" . $annonceId)); ?></b>
                  · Mode: <b><?php echo e($annonce["mode_vente"] ?? ""); ?></b>
                </div>
              </div>
              <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="annonce.php?id=<?php echo (int)$annonceId; ?>">Voir annonce</a>
                <a class="btn btn-outline-dark btn-sm" href="vendeur.php?id=<?php echo (int)$toId; ?>">Voir profil vendeur</a>
              </div>
            </div>

            <?php if (!$canChat): ?>
              <div class="alert alert-warning">
                Cette annonce n’autorise pas la discussion (mode vente = paiement direct).
              </div>
            <?php endif; ?>

            <div class="border rounded p-3" style="height: 420px; overflow:auto; background:#fafafa;">
              <?php if (count($thread) === 0): ?>
                <div class="text-muted">Aucun message. Écris le premier.</div>
              <?php else: ?>
                <?php foreach ($thread as $m): ?>
                  <?php $mine = ((int)$m["id_expediteur"] === $userId); ?>
                  <div class="d-flex <?php echo $mine ? "justify-content-end" : "justify-content-start"; ?> mb-2">
                    <div style="max-width: 78%;" class="p-2 px-3 rounded <?php echo $mine ? "bg-dark text-white" : "bg-white border"; ?>">
                      <div class="small <?php echo $mine ? "text-white-50" : "text-muted"; ?>">
                        <?php echo e(substr((string)$m["date_envoi"], 0, 16)); ?>
                      </div>
                      <div style="white-space:pre-wrap;"><?php echo e($m["contenu"]); ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <form class="mt-3" action="actions/message_action.php" method="POST">
              <input type="hidden" name="id_annonce" value="<?php echo (int)$annonceId; ?>">
              <input type="hidden" name="to" value="<?php echo (int)$toId; ?>">

              <div class="input-group">
                <textarea class="form-control" name="contenu" rows="2" placeholder="Écrire un message..." <?php echo $canChat ? "" : "disabled"; ?>></textarea>
                <button class="btn btn-primary" type="submit" <?php echo $canChat ? "" : "disabled"; ?>>
                  Envoyer
                </button>
              </div>
            </form>

          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include "includes/footer.php"; ?>