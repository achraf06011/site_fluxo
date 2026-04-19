<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/auth.php";
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

$success = $_SESSION["flash_success"] ?? "";
$error   = $_SESSION["flash_error"] ?? "";
$lastOrderId = $_SESSION["last_order_id"] ?? 0;
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

$userId = currentUserId();

// villes Maroc
$MAROC_CITIES = [
  "Agadir","Al Hoceima","Asilah","Azrou","Beni Mellal","Berkane","Boujdour",
  "Casablanca","Chefchaouen","Dakhla","El Jadida","Errachidia","Essaouira",
  "Fès","Guelmim","Ifrane","Kenitra","Khemisset","Khouribga","Laâyoune",
  "Larache","Marrakech","Meknès","Mohammedia","Nador","Ouarzazate",
  "Oujda","Rabat","Safi","Salé","Settat","Sidi Ifni","Tanger","Tarfaya",
  "Taza","Tétouan"
];

// récupérer panier
$stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE id_user = ? LIMIT 1");
$stmt->execute([$userId]);
$p = $stmt->fetch();
$panierId = $p ? (int)$p["id_panier"] : 0;

$items = [];
$subtotal = 0.0;
$hasInvalid = false;
$oneSellerOnly = true;

$sellerId = 0;
$sellerCity = "";
$sellerLivOn = 0;
$sellerFeeSame = 15.0;
$sellerFeeOther = 40.0;

if ($panierId) {
  $stmt = $pdo->prepare("
    SELECT pi.quantity,
           a.id_annonce, a.titre, a.prix, a.stock, a.mode_vente, a.cover_image,
           a.id_vendeur, a.ville,
           a.livraison_active, a.livraison_prix_same_city, a.livraison_prix_other_city
    FROM panier_item pi
    JOIN annonce a ON a.id_annonce = pi.id_annonce
    WHERE pi.id_panier = ?
    ORDER BY pi.id_panier_item DESC
  ");
  $stmt->execute([$panierId]);
  $items = $stmt->fetchAll();

  foreach ($items as $it) {
    $qty = (int)$it["quantity"];
    $stock = (int)$it["stock"];
    $mode = $it["mode_vente"];
    $canBuy = in_array($mode, ["PAIEMENT_DIRECT", "LES_DEUX"], true);
    if (!$canBuy || $stock < $qty) $hasInvalid = true;

    $subtotal += ((float)$it["prix"]) * $qty;

    // vendeur unique
    $vid = (int)$it["id_vendeur"];
    if ($sellerId === 0) {
      $sellerId = $vid;
      $sellerCity = (string)($it["ville"] ?? "");
      $sellerLivOn = (int)($it["livraison_active"] ?? 0);
      $sellerFeeSame = (float)($it["livraison_prix_same_city"] ?? 15);
      $sellerFeeOther = (float)($it["livraison_prix_other_city"] ?? 40);
    } elseif ($sellerId !== $vid) {
      $oneSellerOnly = false;
    }
  }
}

function coverUrl($x) {
  $file = $x["cover_image"] ?? null;
  if (!$file) return "https://picsum.photos/seed/" . $x["id_annonce"] . "/800/600";
  if (str_starts_with($file, "http")) return $file;
  return "uploads/" . $file;
}

// Choix livraison (par défaut main propre)
$mode_reception = $_SESSION["checkout_mode_reception"] ?? "PICKUP";
$ville_livraison = $_SESSION["checkout_ville_livraison"] ?? ($sellerCity ?: "Marrakech");

// champs client
$telephone_client = $_SESSION["checkout_telephone_client"] ?? "";
$adresse_livraison = $_SESSION["checkout_adresse_livraison"] ?? "";

if (isset($_GET["mode"])) {
  $m = $_GET["mode"];
  if (in_array($m, ["PICKUP","LIVRAISON"], true)) $mode_reception = $m;
}
if (isset($_GET["ville"])) {
  $v = trim($_GET["ville"]);
  if (in_array($v, $MAROC_CITIES, true)) $ville_livraison = $v;
}

// calcul frais
$frais_livraison = 0.0;
if ($mode_reception === "LIVRAISON") {
  if ($sellerLivOn !== 1) {
    $mode_reception = "PICKUP";
  } else {
    $sameCity = (mb_strtolower($ville_livraison) === mb_strtolower($sellerCity));
    $frais_livraison = $sameCity ? $sellerFeeSame : $sellerFeeOther;
  }
}

$total = $subtotal + $frais_livraison;

// mémoriser choix
$_SESSION["checkout_mode_reception"] = $mode_reception;
$_SESSION["checkout_ville_livraison"] = $ville_livraison;
?>

<?php include "includes/header.php"; ?>
<?php include "includes/navbar.php"; ?>

<div class="container my-4" style="max-width: 980px;">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="fw-bold mb-0"><i class="bi bi-credit-card"></i> Checkout</h2>
    <a class="btn btn-outline-secondary" href="panier.php">Retour panier</a>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div><?php echo htmlspecialchars($success); ?></div>

      <?php if (!empty($_GET["order"])): ?>
        <a class="btn btn-sm btn-dark" href="checkout_success.php?id=<?php echo (int)$_GET["order"]; ?>">Voir confirmation</a>
      <?php elseif ($lastOrderId): ?>
        <a class="btn btn-sm btn-dark" href="checkout_success.php?id=<?php echo (int)$lastOrderId; ?>">Voir confirmation</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <?php $showEmpty = (count($items) === 0 && !$success); ?>

  <?php if ($showEmpty): ?>
    <div class="alert alert-warning">Ton panier est vide.</div>
    <a class="btn btn-dark" href="index.php">Voir les annonces</a>

  <?php elseif (count($items) > 0): ?>

    <?php if (!$oneSellerOnly): ?>
      <div class="alert alert-warning">
        Pour simplifier (PFE), le checkout supporte <b>un seul vendeur par commande</b>.
        Retire les autres produits du panier.
      </div>
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0">
          <div class="card-body">
            <h5 class="fw-bold mb-3">Résumé de commande</h5>

            <?php foreach ($items as $it): ?>
              <?php
                $qty = (int)$it["quantity"];
                $stock = (int)$it["stock"];
                $sub = ((float)$it["prix"]) * $qty;

                $mode = $it["mode_vente"];
                $canBuy = in_array($mode, ["PAIEMENT_DIRECT", "LES_DEUX"], true);
                $stockOk = ($stock >= $qty);
              ?>
              <div class="d-flex gap-3 align-items-center py-2 border-bottom">
                <img src="<?php echo htmlspecialchars(coverUrl($it)); ?>" style="width:84px;height:62px;object-fit:cover;border-radius:12px" alt="">
                <div class="flex-grow-1">
                  <div class="fw-semibold"><?php echo htmlspecialchars($it["titre"]); ?></div>
                  <div class="text-muted small">
                    Quantité: <?php echo $qty; ?> · Stock: <?php echo $stock; ?> · Mode: <?php echo htmlspecialchars($mode); ?>
                  </div>

                  <div class="text-muted small">
                    Ville vendeur: <b><?php echo htmlspecialchars($it["ville"] ?? ""); ?></b>
                    <?php if (!empty($it["livraison_active"])): ?>
                      · Livraison: <b>Oui</b>
                    <?php else: ?>
                      · Livraison: <b>Non</b>
                    <?php endif; ?>
                  </div>

                  <?php if (!$canBuy): ?>
                    <div class="text-danger small">⚠ Pas disponible en paiement direct.</div>
                  <?php elseif (!$stockOk): ?>
                    <div class="text-danger small">⚠ Stock insuffisant.</div>
                  <?php endif; ?>
                </div>

                <div class="text-end">
                  <div class="fw-semibold"><?php echo number_format((float)$it["prix"], 2); ?> DH</div>
                  <div class="text-muted small"><?php echo number_format($sub, 2); ?> DH</div>
                </div>
              </div>
            <?php endforeach; ?>

            <div class="mt-3">
              <div class="d-flex justify-content-between">
                <div class="text-muted">Sous-total</div>
                <div class="fw-semibold"><?php echo number_format($subtotal, 2); ?> DH</div>
              </div>
              <div class="d-flex justify-content-between">
                <div class="text-muted">Livraison</div>
                <div class="fw-semibold"><?php echo number_format($frais_livraison, 2); ?> DH</div>
              </div>
              <hr>
              <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted">Total</div>
                <div class="fs-4 fw-bold"><?php echo number_format($total, 2); ?> DH</div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <div class="col-12 col-lg-5">
        <div class="card shadow-sm border-0">
          <div class="card-body">
            <h5 class="fw-bold mb-3">Livraison & Paiement</h5>

            <?php if ($sellerLivOn !== 1): ?>
              <div class="alert alert-info">
                Le vendeur n’a pas activé la livraison → <b>Main propre</b> uniquement.
              </div>
            <?php else: ?>
              <div class="mb-2 fw-semibold">Mode de réception</div>
              <div class="d-flex gap-2 flex-wrap">
                <a class="btn <?php echo $mode_reception==='PICKUP'?'btn-dark':'btn-outline-dark'; ?>"
                   href="checkout.php?mode=PICKUP">Main propre</a>
                <a class="btn <?php echo $mode_reception==='LIVRAISON'?'btn-dark':'btn-outline-dark'; ?>"
                   href="checkout.php?mode=LIVRAISON">Livraison</a>
              </div>

              <?php if ($mode_reception === "LIVRAISON"): ?>
                <div class="mt-3">
                  <label class="form-label fw-semibold">Ville de livraison (Maroc)</label>
                  <form method="GET" class="d-flex gap-2">
                    <input type="hidden" name="mode" value="LIVRAISON">
                    <select class="form-select" name="ville">
                      <?php foreach ($MAROC_CITIES as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($ville_livraison===$c)?"selected":""; ?>>
                          <?php echo htmlspecialchars($c); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-secondary" type="submit">OK</button>
                  </form>

                  <div class="small text-muted mt-2">
                    Même ville: <b><?php echo number_format($sellerFeeSame,2); ?> DH</b> ·
                    Autre ville: <b><?php echo number_format($sellerFeeOther,2); ?> DH</b>
                  </div>
                </div>
              <?php endif; ?>
            <?php endif; ?>

            <hr>

            <?php if ($hasInvalid || !$oneSellerOnly): ?>
              <div class="alert alert-warning">
                Corrige d’abord le panier (produit non payable, stock insuffisant, ou plusieurs vendeurs).
              </div>
              <a class="btn btn-outline-secondary w-100" href="panier.php">Retour au panier</a>
            <?php else: ?>
              <form action="actions/stripe_create_session.php" method="POST">
                <input type="hidden" name="confirm" value="1">
                <input type="hidden" name="mode_reception" value="<?php echo htmlspecialchars($mode_reception); ?>">
                <input type="hidden" name="ville_livraison" value="<?php echo htmlspecialchars($ville_livraison); ?>">
                <input type="hidden" name="frais_livraison" value="<?php echo htmlspecialchars((string)$frais_livraison); ?>">

                <div class="mb-3">
                  <label class="form-label fw-semibold">Numéro de téléphone</label>
                  <input
                    type="text"
                    class="form-control"
                    name="telephone_client"
                    value="<?php echo htmlspecialchars($telephone_client); ?>"
                    placeholder="Ex: 0612345678"
                    required
                  >
                </div>

                <?php if ($mode_reception === "LIVRAISON"): ?>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Adresse de livraison</label>
                    <textarea
                      class="form-control"
                      name="adresse_livraison"
                      rows="3"
                      placeholder="Quartier, rue, numéro, immeuble..."
                      required
                    ><?php echo htmlspecialchars($adresse_livraison); ?></textarea>
                  </div>
                <?php else: ?>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Lieu de rencontre / adresse (optionnel)</label>
                    <textarea
                      class="form-control"
                      name="adresse_livraison"
                      rows="2"
                      placeholder="Ex: Café X, Avenue Y..."
                    ><?php echo htmlspecialchars($adresse_livraison); ?></textarea>
                  </div>
                <?php endif; ?>

                <button class="btn btn-dark w-100 btn-lg" type="submit">
                  <i class="bi bi-credit-card"></i> Payer avec Stripe
                </button>
              </form>

              <div class="mt-2 small text-muted">
                Après paiement : tu verras le <b>suivi de commande</b>.
              </div>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>

  <?php else: ?>
    <div class="d-flex gap-2">
      <a class="btn btn-dark" href="index.php">Retour annonces</a>
      <a class="btn btn-outline-secondary" href="profil.php">Mon compte</a>
    </div>
  <?php endif; ?>

</div>

<?php include "includes/footer.php"; ?>