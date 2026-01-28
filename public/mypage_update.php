<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../src/backend/auth.php";
require_once __DIR__ . "/../src/backend/db.php";

requireLogin("./login.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ./mypage.php");
    exit();
}

$userId = currentUserId();
if ($userId === null) {
    header("Location: ./login.php");
    exit();
}

$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");

if ($name === "" || $email === "") {
    header("Location: ./mypage.php?error=empty");
    exit();
}

try {
    $stmt = $pdo -> prepare(
        "SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1"
    );
    $stmt -> execute([
        ":email" => $email,
        ":id" => $userId,
    ]);
    $dup = $stmt -> fetch(PDO::FETCH_ASSOC);

    if ($dup) {
        header("Location: ./mypage.php?error=email_taken");
        exit();
    }

    $stmt = $pdo -> prepare(
        "UPDATE users
        SET name = :name, email = :email, updated_at = NOW()
        WHERE id = :id
        LIMIT 1"
    );
    $stmt -> execute([
        ":name" => $name,
        ":email" => $email,
        ":id" => $userId,
    ]);

    ensureSessionStarted();
    $_SESSION["user_name"] = $name;

    header("Location: ./mypage.php?updated=1");
    exit();

} catch(PDOException $e) {
    header("Location: ./mypage.php?error=failed");
    exit();
}