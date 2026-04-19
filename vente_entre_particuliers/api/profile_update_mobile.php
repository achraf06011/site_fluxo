<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$userId = (int)($data["user_id"] ?? 0);
$nom = trim((string)($data["nom"] ?? ""));
$email = trim((string)($data["email"] ?? ""));

if ($userId <= 0) {
  http_response_code(401);
  echo json_encode([
    "ok" => false,
    "message" => "Connexion requise."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($nom === "" || mb_strlen($nom) < 2) {
  http_response_code(400);
  echo json_encode([
    "ok" => false,
    "message" => "Nom invalide."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode([
    "ok" => false,
    "message" => "Email invalide."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $st = $pdo->prepare("
    SELECT id_user
    FROM user
    WHERE email = ? AND id_user <> ?
    LIMIT 1
  ");
  $st->execute([$email, $userId]);

  if ($st->fetch()) {
    echo json_encode([
      "ok" => false,
      "message" => "Cet email est déjà utilisé."
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $st = $pdo->prepare("
    UPDATE user
    SET nom = ?, email = ?
    WHERE id_user = ?
  ");
  $st->execute([$nom, $email, $userId]);

  echo json_encode([
    "ok" => true,
    "message" => "Profil mis à jour."
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "message" => "Erreur serveur."
  ], JSON_UNESCAPED_UNICODE);
}