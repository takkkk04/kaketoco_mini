<?php
// =============================================
// お気に入り登録・解除処理
// =============================================
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../src/backend/auth.php";
require_once __DIR__ . "/../src/backend/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "method_not_allowed"]);
    exit();
}

$userId = currentUserId();
if ($userId === null) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "not_logged_in"]);
    exit();
}

$reg = (int)($_POST["reg"] ?? 0);
if ($reg <= 0) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "invalid_reg"]);
    exit();
}

try {
    //すでにお気に入りに入っているか確認
    $st = $pdo -> prepare(
        "SELECT id
        FROM favorites
        WHERE user_id = :uid AND registration_number = :reg
        LIMIT 1"
    );
    $st -> execute([":uid" => $userId, ":reg" => $reg]);
    $exists = $st -> fetch(PDO::FETCH_ASSOC);

    //すでにあれば削除
    if ($exists) {
        $del = $pdo -> prepare(
            "DELETE FROM favorites
            WHERE user_id = :uid AND registration_number = :reg
            LIMIT 1"
        );
        $del -> execute([":uid" => $userId, ":reg" => $reg]);

        echo json_encode([
            "ok" => true,
            "fav" => false,
            "reg" => $reg
        ]);
        exit();
    }

    //なければ追加する
    $ins = $pdo -> prepare(
        "INSERT INTO favorites (user_id,registration_number)
        VALUES (:uid, :reg)"
    );
    $ins -> execute([":uid" => $userId, ":reg" => $reg]);

    echo json_encode([
        "ok" => true,
        "fav" => true,
        "reg" => $reg
    ]);
    exit();

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => "db_error",
        "message" => $e -> getMessage(),
    ]);
    exit();
}