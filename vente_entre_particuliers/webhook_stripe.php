<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/vendor/autoload.php";

$cfg = require __DIR__ . "/config/stripe.php";
\Stripe\Stripe::setApiKey($cfg["secret_key"]);

$payload = @file_get_contents("php://input");
$sigHeader = $_SERVER["HTTP_STRIPE_SIGNATURE"] ?? "";

try {
  // Vérification signature webhook (recommandé Stripe). :contentReference[oaicite:3]{index=3}
  $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $cfg["webhook_secret"]);
} catch (Exception $e) {
  http_response_code(400);
  echo "Webhook error: " . $e->getMessage();
  exit;
}

if (($event->type ?? "") === "checkout.session.completed") {
  $session = $event->data->object;

  $sessionId = $session->id ?? null;
  $paymentIntent = $session->payment_intent ?? null;

  if ($sessionId) {
    // Mettre paiement=ACCEPTE et commande=PAYE
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE paiement SET statut='ACCEPTE', stripe_payment_intent=? WHERE stripe_session_id=?");
    $stmt->execute([$paymentIntent, $sessionId]);

    $stmt = $pdo->prepare("
      UPDATE orders o
      JOIN paiement p ON p.id_order = o.id_order
      SET o.statut='PAYE'
      WHERE p.stripe_session_id = ?
    ");
    $stmt->execute([$sessionId]);

    $pdo->commit();
  }
}

http_response_code(200);
echo "OK";