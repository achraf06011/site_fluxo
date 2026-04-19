<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$idUser = (int)($data["id_user"] ?? 0);
$code = trim((string)($data["code"] ?? ""));

function jsonOut($ok, $message, $extra = [], $codeHttp = 200) {
    http_response_code($codeHttp);
    echo json_encode(array_merge([
        "ok" => $ok,
        "message" => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($idUser <= 0) {
    jsonOut(false, "Utilisateur invalide.", [], 400);
}

if ($code === "" || !preg_match('/^\d{6}$/', $code)) {
    jsonOut(false, "Code invalide.", [], 400);
}

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM user
        WHERE id_user = ?
        LIMIT 1
    ");
    $stmt->execute([$idUser]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonOut(false, "Utilisateur introuvable.", [], 404);
    }

    if ((int)($user["email_verifie"] ?? 0) === 1) {
        jsonOut(true, "Email déjà vérifié.", [
            "user" => [
                "id_user" => (int)$user["id_user"],
                "nom" => (string)$user["nom"],
                "email" => (string)$user["email"],
                "role" => (string)($user["role"] ?? "USER"),
            ]
        ]);
    }

    if ((string)($user["code_verification"] ?? "") !== $code) {
        jsonOut(false, "Code incorrect.", [], 400);
    }

    $expireAt = !empty($user["code_verification_expire"])
        ? strtotime((string)$user["code_verification_expire"])
        : 0;

    if ($expireAt > 0 && $expireAt < time()) {
        jsonOut(false, "Le code a expiré. Demande un nouveau code.", [], 400);
    }

    $stmt = $pdo->prepare("
        UPDATE user
        SET email_verifie = 1,
            code_verification = NULL,
            code_verification_expire = NULL,
            code_verification_renvoi_at = NULL
        WHERE id_user = ?
    ");
    $stmt->execute([$idUser]);

    $stmt = $pdo->prepare("SELECT * FROM user WHERE id_user = ? LIMIT 1");
    $stmt->execute([$idUser]);
    $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

    jsonOut(true, "Email vérifié avec succès.", [
        "user" => [
            "id_user" => (int)$updatedUser["id_user"],
            "nom" => (string)$updatedUser["nom"],
            "email" => (string)$updatedUser["email"],
            "role" => (string)($updatedUser["role"] ?? "USER"),
        ]
    ]);
} catch (Exception $e) {
    jsonOut(false, "Erreur serveur. Réessaye plus tard.", [], 500);
}