<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data["email"] ?? "");
$password = trim($data["password"] ?? "");

if ($email === "" || $password === "") {
    echo json_encode([
        "ok" => false,
        "message" => "Email et mot de passe requis"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            "ok" => false,
            "message" => "Utilisateur introuvable"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!password_verify($password, $user["password"])) {
        echo json_encode([
            "ok" => false,
            "message" => "Mot de passe incorrect"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        "ok" => true,
        "message" => "Connexion réussie",
        "user" => [
            "id_user" => $user["id_user"],
            "nom" => $user["nom"],
            "email" => $user["email"],
            "role_user" => $user["role_user"] ?? "USER"
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "Erreur serveur"
    ], JSON_UNESCAPED_UNICODE);
}