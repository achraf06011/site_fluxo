<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";

if (session_status() === PHP_SESSION_NONE) session_start();

header("Content-Type: application/json; charset=UTF-8");

if (!isLoggedIn()) {
  http_response_code(401);
  echo json_encode([
    "ok" => false,
    "message" => "Connexion requise."
  ]);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode([
    "ok" => false,
    "message" => "Méthode non autorisée."
  ]);
  exit;
}

$userId = currentUserId();
$idAnnonce = (int)($_POST["id_annonce"] ?? 0);

if ($idAnnonce <= 0) {
  http_response_code(400);
  echo json_encode([
    "ok" => false,
    "message" => "Annonce invalide."
  ]);
  exit;
}

try {
  // Vérifier que l'annonce existe
  $stmt = $pdo->prepare("
    SELECT id_annonce
    FROM annonce
    WHERE id_annonce = ?
    LIMIT 1
  ");
  $stmt->execute([$idAnnonce]);
  $annonce = $stmt->fetch();

  if (!$annonce) {
    http_response_code(404);
    echo json_encode([
      "ok" => false,
      "message" => "Annonce introuvable."
    ]);
    exit;
  }

  // Vérifier si déjà en favori
  $stmt = $pdo->prepare("
    SELECT id_favori
    FROM favoris
    WHERE id_user = ? AND id_annonce = ?
    LIMIT 1
  ");
  $stmt->execute([$userId, $idAnnonce]);
  $fav = $stmt->fetch();

  if ($fav) {
    $stmt = $pdo->prepare("
      DELETE FROM favoris
      WHERE id_user = ? AND id_annonce = ?
    ");
    $stmt->execute([$userId, $idAnnonce]);

    echo json_encode([
      "ok" => true,
      "favori" => false,
      "message" => "Retiré des favoris."
    ]);
    exit;
  }

  $stmt = $pdo->prepare("
    INSERT INTO favoris (id_user, id_annonce)
    VALUES (?, ?)
  ");
  $stmt->execute([$userId, $idAnnonce]);

  echo json_encode([
    "ok" => true,
    "favori" => true,
    "message" => "Ajouté aux favoris."
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "message" => "Erreur serveur."
  ]);
  exit;
}