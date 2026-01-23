<?php
$envPath = __DIR__ . "/../../config/.env.php";

if (!file_exists($envPath)) {
    exit("config/.env.php が見つかりません");
}

$env = require $envPath;

$db_name = $env["db_name"]; //データベース名
$db_host = $env["db_host"]; //DBホスト
$db_user = $env["db_user"]; //ユーザー名（さくらサーバーはDB名と同一）
$db_pass = $env["db_pass"]; //パスワード
$charset = $env["db_charset"];

$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$charset}";

try {
    $pdo = new PDO(
        $dsn,
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // エラーを例外で出す
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // fetch()が連想配列
            PDO::ATTR_EMULATE_PREPARES => false, // できるだけ本物のpreparedを使う
        ]
    );
} catch (PDOException $e) {
    exit("DB接続に失敗しました: " . $e->getMessage());
}