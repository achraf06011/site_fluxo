<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$userId = (int)($_GET["user_id"] ?? 0);

if ($userId <= 0) {
  http_response_code(401);
  echo json_encode([
    "ok" => false,
    "message" => "Connexion requise."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $stmt = $pdo->prepare("
    SELECT id_user, nom, email, role, date_inscription
    FROM user
    WHERE id_user = ?
    LIMIT 1
  ");
  $stmt->execute([$userId]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$u) {
    http_response_code(404);
    echo json_encode([
      "ok" => false,
      "message" => "Utilisateur introuvable."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo json_encode([
    "ok" => true,
    "user" => [
      "id_user" => (int)$u["id_user"],
      "nom" => (string)$u["nom"],
      "email" => (string)$u["email"],
      "role" => (string)$u["role"],
      "date_inscription" => (string)$u["date_inscription"],
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "message" => "Erreur serveur."
  ], JSON_UNESCAPED_UNICODE);
}