<?php
function ensureSessionStarted(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool {
    ensureSessionStarted();
    return !empty($_SESSION["user_id"]);
}

function requireLogin(string $redirectTo = "./login.php"): void {
    if (!isLoggedIn()) {
        header("Location: " . $redirectTo);
        exit();
    }
}

function currentUserId(): ?int {
    ensureSessionStarted();
    return isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
}

function currentUserName(): string {
    ensureSessionStarted();
    return(string)($_SESSION["user_name"] ?? "");
}

function currentUserEmail(): string {
    ensureSessionStarted();
    return(string)($_SESSION["user_email"] ?? "");
}