<?php
// =============================================
// 詳細ページ
// =============================================
declare(strict_types=1);

require_once __DIR__ . "/../src/backend/pesticide_detail_fetch.php";

$displayValue = static function ($value): string {
    $text = trim((string)($value ?? ""));
    return $text === "" ? "-" : $text;
};
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

                <h2>適用表</h2>
                <?php if ($ruleRows === []): ?>
                    <p>適用情報がありません。</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>作物名</th>
                                <th>適用病害虫雑草名</th>
                                <th>希釈倍数使用量</th>
                                <th>使用時期</th>
                                <th>本剤の使用回数</th>
                                <th>使用方法</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ruleRows as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($displayValue($row["crop_name"] ?? null), ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($displayValue($row["target_name"] ?? null), ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($displayValue($row["magnification_text"] ?? null), ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($displayValue($row["timing_text"] ?? null), ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($displayValue($row["times_text"] ?? null), ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($displayValue($row["method_name"] ?? null), ENT_QUOTES, "UTF-8"); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>

            <p><a href="./index.php">一覧へ戻る</a></p>
        </section>
    </main>
</body>

</html>
