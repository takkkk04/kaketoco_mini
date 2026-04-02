<?php
// =============================================
// RACコード押したら遷移する一覧ページ
// =============================================
declare(strict_types=1);

require_once __DIR__ . "/../src/backend/index_bootstrap.php";

$group = strtoupper(trim((string)($_GET["group"] ?? "")));
$code = trim((string)($_GET["code"] ?? ""));
$rows = [];
$errorMessage = "";

$searchParamKeys = ["keyword", "category", "crop", "target", "method", "sort", "page"];
$searchParams = [];
foreach ($searchParamKeys as $key) {
    $value = trim((string)($_GET[$key] ?? ""));
    if ($value !== "") {
        $searchParams[$key] = $value;
    }
}
$returnQuery = http_build_query($searchParams);
$backToSearchUrl = "./index.php" . ($returnQuery !== "" ? "?" . $returnQuery : "") . "#search_form";

if ($group === "" || $code === "") {
    $errorMessage = "RACの指定が不正です。";
} else {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT
            p.id AS pesticide_id,
            p.name,
            p.category,
            ing.ingredients_text
         FROM ingredient_rac_labels irl
         JOIN pesticide_ingredients pi
            ON pi.ingredient_id = irl.ingredient_id
         JOIN pesticides p
            ON p.id = pi.pesticide_id
         LEFT JOIN (
            SELECT
                pi2.pesticide_id,
                GROUP_CONCAT(
                    DISTINCT CONCAT(
                        i.name,
                        CASE
                            WHEN pi2.concentration_text IS NULL OR pi2.concentration_text = '' THEN ''
                            ELSE CONCAT('（', pi2.concentration_text, '）')
                        END
                    )
                    ORDER BY pi2.id ASC
                    SEPARATOR '\n'
                ) AS ingredients_text
            FROM pesticide_ingredients pi2
            JOIN ingredients i
                ON i.id = pi2.ingredient_id
            GROUP BY pi2.pesticide_id
         ) ing
            ON ing.pesticide_id = p.id
         WHERE irl.rac_group = :rac_group
           AND irl.rac_code = :rac_code
         ORDER BY p.name ASC"
    );
    $stmt->execute([
        ":rac_group" => $group,
        ":rac_code" => $code,
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAC一覧 | カケトコ mini</title>
    <link rel="stylesheet" href="./css/reset.css">
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/rac_list.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>

<body>
    <?php require __DIR__ . "/parts/header.php"; ?>

    <main class="app_main rac_list_main">
        <section class="result_section rac_list_section">
            <div class="rac_heading">
                <div class="rac_heading_left">
                    <h2 class="rac_heading_title"><?php echo htmlspecialchars($group . ":" . $code, ENT_QUOTES, "UTF-8"); ?> の農薬一覧</h2>
                    <span id="result_count"><?php echo (int)count($rows); ?>件</span>
                </div>
                <div class="rac_heading_right">
                    <a href="<?php echo htmlspecialchars($backToSearchUrl, ENT_QUOTES, "UTF-8"); ?>" class="rac_back_btn">検索に戻る</a>
                </div>
            </div>

            <?php if ($errorMessage !== ""): ?>
                <p><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8"); ?></p>
            <?php elseif ($rows === []): ?>
                <p>該当する農薬がありません。</p>
            <?php else: ?>
                <div id="result_list" class="result_list">
                    <?php foreach ($rows as $row): ?>
                        <article class="result_card">
                            <div class="card_title">
                                <span class="card_title_name">
                                    <a href="./pesticide_detail.php?id=<?php echo (int)($row["pesticide_id"] ?? 0); ?>">
                                        <?php echo htmlspecialchars((string)($row["name"] ?? ""), ENT_QUOTES, "UTF-8"); ?>
                                    </a>
                                </span>
                            </div>
                            <div class="card_specs">
                                <div class="spec_row">
                                    <span class="spec_label">カテゴリ</span>
                                    <span class="spec_val"><?php echo htmlspecialchars((string)($row["category"] ?? "-"), ENT_QUOTES, "UTF-8"); ?></span>
                                </div>
                                <div class="spec_row">
                                    <span class="spec_label">有効成分</span>
                                    <span class="spec_val">
                                        <?php
                                        $ingredientsText = trim((string)($row["ingredients_text"] ?? ""));
                                        if ($ingredientsText === "") {
                                            echo "-";
                                        } else {
                                            echo nl2br(htmlspecialchars($ingredientsText, ENT_QUOTES, "UTF-8"));
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="./js/app.js"></script>
</body>

</html>
