<?php

declare(strict_types=1);

require_once __DIR__ . "/../src/backend/db.php";

$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    $errorMessage = "不正なIDです。";
    $pesticide = null;
} else {
    $stmt = $pdo->prepare(
        "SELECT id, name, registration_number
         FROM pesticides
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $pesticide = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $errorMessage = $pesticide ? "" : "該当する農薬が見つかりません。";
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>農薬詳細</title>
    <link rel="stylesheet" href="./css/reset.css">
    <link rel="stylesheet" href="./css/style.css">
</head>

<body>
    <main class="app_main">
        <section class="result_section">
            <h1>農薬詳細</h1>

            <?php if ($pesticide === null): ?>
                <p><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8"); ?></p>
            <?php else: ?>
                <p>農薬名: <?php echo htmlspecialchars((string)$pesticide["name"], ENT_QUOTES, "UTF-8"); ?></p>
                <p>登録番号: <?php echo htmlspecialchars((string)$pesticide["registration_number"], ENT_QUOTES, "UTF-8"); ?></p>
            <?php endif; ?>

            <p><a href="./index.php">一覧へ戻る</a></p>
        </section>
    </main>
</body>

</html>
