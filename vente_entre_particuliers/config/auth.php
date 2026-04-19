<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function currentUserId(): int {
  return (int)($_SESSION["user"]["id_user"] ?? 0);
}

function currentUserRole(): string {
  return (string)($_SESSION["user"]["role"] ?? "USER");
}

function isLoggedIn(): bool {
  return currentUserId() > 0;
}


function basePath(): string {
  $scriptName = str_replace("\\", "/", $_SERVER["SCRIPT_NAME"] ?? "");

  $pos = strpos($scriptName, "/config/");
  if ($pos !== false) {
    return substr($scriptName, 0, $pos);
  }

  $pos = strpos($scriptName, "/actions/");
  if ($pos !== false) {
    return substr($scriptName, 0, $pos);
  }

  $pos = strpos($scriptName, "/admin/");
  if ($pos !== false) {
    return substr($scriptName, 0, $pos);
  }

  return rtrim(dirname($scriptName), "/");
}

function redirectTo(string $path): void {
  header("Location: " . basePath() . "/" . ltrim($path, "/"));
  exit;
}

function requireLogin(): void {
  if (!isLoggedIn()) {
    // ici tu veux inscription direct
    redirectTo("register.php");
  }
}

function requireAdmin(): void {
  requireLogin();

  if (currentUserRole() !== "ADMIN") {
    http_response_code(403);
    die("Accès refusé (ADMIN uniquement).");
  }
}