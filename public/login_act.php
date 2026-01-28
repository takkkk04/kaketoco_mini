<?php
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . "/../src/backend/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

$email = trim($_POST["email"] ?? "");
$password = (string)($_POST["password"] ?? "");

// 空だったらエラーを返す
if ($email === "" || $password === "") {
    header("Location: login.php?error=1");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([":email" => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // ユーザーがいないorパスワード不一致ならエラーを返す
    if (!$user || !password_verify($password, $user["password"])){
        header("Location: login.php?error=1");
        exit();
    }

    session_regenerate_id(true);
    $_SESSION["user_id"] = (int)$user["id"];
    $_SESSION["user_name"] = (string)$user["name"];

    header("Location: index.php");
    exit();
}

catch (PDOException $e) {
    header("Location: login.php?error=1");
    exit();
}

