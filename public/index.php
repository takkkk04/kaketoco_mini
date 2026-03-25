<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../src/backend/index_bootstrap.php";
require_once __DIR__ . "/../src/backend/index_helpers.php";
require_once __DIR__ . "/../src/backend/index_search.php";
require_once __DIR__ . "/../src/backend/index_options.php";

require_once __DIR__ . "/../src/backend/index_favorites.php";
require_once __DIR__ . "/../src/backend/index_card_lists.php";

// =============================================
// カード内バッジ
// =============================================
$BADGE_DEFS = [
    [
        "key" => "systemic",
        "label" => "浸透移行性",
        "class" => "badge_systemic"
    ],

    [
        "key" => "translaminar",
        "label" => "浸達性",
        "class" => "badge_translaminar"
    ],

    [
        "key" => "quickly",
        "label" => "速効性",
        "class" => "badge_quickly",
        "min" => "4"
    ], //速効性４以上を指定する
];

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>カケトコ_mini</title>
    <link rel="stylesheet" href="./css/reset.css">
    <link rel="stylesheet" href="./css/style.css">
    <!-- Select2 プルダウン内検索 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>

<body>
    <header class="app_header">
        <h1 class="app_title">
            <a href="./index.php">カケトコ mini</a>
        </h1>

        <!-- <a href="./user_create.php" class="register_btn">会員登録</a> -->
        <?php if ($isLoggedIn): ?>
            <div class="header_user">
                <span class="user_name">
                    <?= htmlspecialchars($userName, ENT_QUOTES, "UTF-8") ?>さん
                </span>
                <a href="./logout.php" class="logout_btn">ログアウト</a>
            </div>
        <?php else: ?>
            <a href="./login.php" class="register_btn">ログイン</a>
        <?php endif; ?>

        <div class="header_menu">
            <button type="button" id="menu_btn" class="menu_btn" aria-expanded="false" aria-controls="menu_panel">
                <span class="menu_icon" aria-hidden="true"></span>
                <span class="sr_only">メニュー</span>
            </button>

            <div id="menu_panel" class="menu_panel" hidden>
                <a href="./admin/admin.php" class="menu_item">管理画面</a>
                <?php if ($isLoggedIn): ?>
                    <a href="./mypage.php" class="menu_item">マイページ</a>
                    <a href="./logout.php" class="menu_item">ログアウト</a>
                <?php else: ?>
                    <a href="./login.php" class="menu_item">ログイン</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="app_main">
        <section class="search_section">
            <h2>ザックリ検索</h2>

            <form id="search_form" method="GET" action="">
                <div class="form_row">
                    <label for="keyword">農薬名</label>
                    <input
                        type="search"
                        id="keyword"
                        name="keyword"
                        value="<?php echo htmlspecialchars($keyword, ENT_QUOTES, "UTF-8"); ?>"
                        placeholder="農薬名で検索">
                </div>

                <div class="form_row">
                    <label for="category">カテゴリ</label>
                    <div class="category_picker" role="radiogroup" aria-label="カテゴリ">
                        <label class="cat_item">
                            <input type="radio" name="category" value="" <?php echo ($category === "") ? "checked" : ""; ?>>
                            <span class="cat_btn">
                                <img class="cat_icon_placeholder" src="./image/icon_butterfly.png" alt="" aria-hidden="true">
                                <span class="cat_text">指定なし</span>
                            </span>
                        </label>

                        <label class="cat_item">
                            <input type="radio" name="category" value="殺虫剤" <?php echo ($category === "殺虫剤") ? "checked" : ""; ?>>
                            <span class="cat_btn">
                                <img src="./image/icon_butterfly.png" alt="">
                                <span class="cat_text">殺虫剤</span>
                            </span>
                        </label>

                        <label class="cat_item">
                            <input type="radio" name="category" value="殺菌剤" <?php echo ($category === "殺菌剤") ? "checked" : ""; ?>>
                            <span class="cat_btn">
                                <img src="./image/icon_virus.png" alt="">
                                <span class="cat_text">殺菌剤</span>
                            </span>
                        </label>

                        <label class="cat_item">
                            <input type="radio" name="category" value="除草剤" <?php echo ($category === "除草剤") ? "checked" : ""; ?>>
                            <span class="cat_btn">
                                <img src="./image/icon_leaf.png" alt="">
                                <span class="cat_text">除草剤</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="form_row">
                    <label for="crop">作物名</label>
                    <!-- キーワード検索 Select2(プルダウン内検索)-->
                    <select name="crop" id="crop" class="js-select2">
                        <option value="">指定なし</option>
                        <!-- 作物名プルダウン -->
                        <!-- カタカナ全角→半角処理 -->
                        <?php foreach ($cropOptions as $label => $dbValue): ?>
                            <!-- <select>のプルダウンの中身<option>をHTMLで作っている -->
                            <!-- htmlspecialchars()は安全装置,記号とかをエスケープする -->
                            <option value="<?php echo htmlspecialchars($dbValue, ENT_QUOTES, "UTF-8"); ?>"
                                <?php
                                // selectedがあると検索ボタン押しても選択状態になる
                                echo ($crop === $dbValue) ? "selected" : ""; ?>>
                                <!-- <option>トマト</option>のトマトの部分 -->
                                <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form_row">
                    <label for="target">病害虫</label>
                    <select name="target" id="target" class="js-select2">
                        <option value="">指定なし</option>
                        <!-- 病害虫プルダウン -->
                        <?php foreach ($targetOptions as $t): ?>
                            <option value="<?php echo htmlspecialchars($t, ENT_QUOTES, "UTF-8"); ?>"
                                <?php echo ($target === $t) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($t, ENT_QUOTES, "UTF-8"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form_row">
                    <!-- 使用方法ラジオボタン -->
                    <label for="method">使用方法</label>
                    <div class="method_picker" role="radiogroup" aria-label="使用方法">
                        <?php foreach ($methodLabels as $m): ?>
                            <label class="method_item">
                                <input type="radio" name="method"
                                    value="<?php echo htmlspecialchars((string)$m["value"], ENT_QUOTES, "UTF-8"); ?>"
                                    <?php echo ($method === (string)$m["value"]) ? "checked" : ""; ?>>
                                <span class="method_btn">
                                    <?php echo htmlspecialchars((string)$m["label"], ENT_QUOTES, "UTF-8"); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form_row_btn">
                    <button type="submit" id="search_btn">検索</button>
                    <button type="button" id="reset_btn">リセット</button>
                </div>

                <!-- ソートと繋ぐ役割 -->
                <input type="hidden" name="sort" id="sort_hidden"
                    value="<?php echo htmlspecialchars($sort ?? "score_desk", ENT_QUOTES, "UTF-8") ?>">

            </form>
        </section>

        <?php if ($hasSearchCondition): ?>
        <section class="result_section">

            <div class="result_header">
                <div class="result_left">
                    <h2>検索結果</h2>
                    <span id="result_count"><?php echo (int)$count ?>件</span>
                </div>

                <!-- ソート ここに置きたいけどここじゃ効かない-->
                <div class="result_right">
                    <label for="sort" class="sort_label"></label>
                    <select name="sort" id="sort">
                        <option value="score_desc" <?php echo ($sort === "score_desc") ? "selected" : ""; ?>>カケトコスコア順</option>
                        <option value="name_asc" <?php echo ($sort === "name_asc") ? "selected" : ""; ?>>名前順</option>
                        <option value="year_desc" <?php echo ($sort === "year_desc") ? "selected" : ""; ?>>登録が新しい順</option>
                    </select>
                </div>
            </div>

            <!-- 検索結果表示エリア -->
            <div id="result_list" class="result_list">
                <?php if ($count === 0): ?>
                    <p>該当する農薬がありません。</p>
                <?php else: ?>
                    <?php foreach ($filtered as $i => $p): ?>
                        <?php
                        $pid = (string)($p["shopify_id"] ?? "");
                        $boxId = "buy-" . $i;
                        $reg = (string)($p["registration_number"] ?? "");
                        //カード内 作物・病害虫一覧
                        $cropListStmt->execute([":reg" => $reg, ":category" => $category]);
                        $cropList = $cropListStmt->fetchAll(PDO::FETCH_COLUMN);
                        $targetListStmt->execute([":reg" => $reg, ":category" => $category]);
                        $targetList = $targetListStmt->fetchAll(PDO::FETCH_COLUMN);
                        //カード内バッジ 浸透移行性、浸達性、速効性
                        $badges = buildBadges($p, $BADGE_DEFS);
                        $showRuleSpecs = ($crop !== "");
                        ?>

                        <article class="result_card fav_card">
                            <div class="card_title">
                                <!-- 商品名 -->
                                <span class="card_title_name">
                                    <?php echo htmlspecialchars(
                                        mb_convert_kana($p["name"] ?? "", "KV", "UTF-8"),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </span>

                                <!-- RACコード -->
                                <div class="card_title_right">
                                    <?php if (!empty($p["rac_code"])): ?>
                                        <span class="rac_code">
                                            RAC:<?php echo htmlspecialchars($p["rac_code"], ENT_QUOTES, "UTF-8"); ?>
                                        </span>
                                    <?php endif; ?>

                                    <!-- お気に入りハート -->
                                    <?php
                                        $regInt = (int)$reg;
                                        $isFav = !empty($favMap[$regInt]);
                                    ?>
                                    <button
                                        type="button"
                                        class="fav_btn <?php echo $isFav ? 'is-on' : ''; ?>"
                                        aria-pressed="<?php echo $isFav ? 'true' : 'false'; ?>"
                                        data-reg="<?php echo (int)$regInt; ?>"
                                        title="お気に入り">
                                        <svg class="fav_icon" viewBox="0 0 24 24" aria-hidden="true">
                                            <!-- SVGハート描画 -->
                                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                        </svg>
                                        <span class="sr_only">お気に入り</span>
                                    </button>
                                </div>
                            </div>

                            <div class="card_mid">
                                <!-- 商品画像 -->
                                <div class="card_left">
                                    <?php if (!empty($p["shopify_id"])): ?>
                                        <div
                                            class="shopify_img shopify_cell"
                                            data-product-id="<?php echo htmlspecialchars(
                                                                    (string)($p["shopify_id"] ?? ""),
                                                                    ENT_QUOTES,
                                                                    "UTF-8"
                                                                ); ?>">
                                        </div>
                                    <?php else: ?>
                                        <div class="shopify_img_placeholder">
                                            <img src="./image/coming_soon.jpeg" alt="準備中">
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="card_specs">
                                    <div class="spec_row">
                                        <span class="spec_label">希釈倍率</span>
                                        <span class="spec_val">
                                            <?php
                                            echo $showRuleSpecs && isset($p["magnification"])
                                                ? htmlspecialchars((string)$p["magnification"], ENT_QUOTES, "UTF-8") : "-";
                                            ?>
                                        </span>
                                    </div>

                                    <div class="spec_row">
                                        <span class="spec_label">使用回数</span>
                                        <span class="spec_val">
                                            <?php
                                            echo $showRuleSpecs && isset($p["times"])
                                                ? htmlspecialchars((string)$p["times"], ENT_QUOTES, "UTF-8") : "-";
                                            ?>
                                        </span>
                                    </div>

                                    <div class="spec_row">
                                        <span class="spec_label">収穫前日数</span>
                                        <span class="spec_val">
                                            <?php
                                            echo $showRuleSpecs && isset($p["timing"])
                                                ? htmlspecialchars((string)$p["timing"], ENT_QUOTES, "UTF-8") : "-";
                                            ?>
                                        </span>
                                    </div>

                                    <div class="spec_row">
                                        <span class="spec_label">使用方法</span>
                                        <span class="spec_val">
                                            <?php echo $showRuleSpecs
                                                ? htmlspecialchars((string)$p["method"] ?? "", ENT_QUOTES, "UTF-8")
                                                : "-"; ?>
                                        </span>
                                    </div>

                                    <div class="spec_row">
                                        <span class="spec_label">カケトコスコア</span>
                                        <span class="spec_val">
                                            <?php echo (int)($p["_score"] ?? 0); ?>
                                        </span>
                                    </div>

                                    <!-- 特徴バッジ -->
                                    <?php if (!empty($badges)): ?>
                                        <div class="badge_row">
                                            <?php foreach ($badges as $b): ?>
                                                <span class="badge <?php echo htmlspecialchars($b["class"], ENT_QUOTES, "UTF-8"); ?>">
                                                    <?php echo htmlspecialchars($b["label"], ENT_QUOTES, "UTF-8"); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                </div>
                            </div>

                            <div class="card_lists">
                                <details class="card_detail">
                                    <summary>登録作物(<?php echo count($cropList); ?>)</summary>
                                    <div class="detail_body">
                                        <?php if (count($cropList) === 0): ?>
                                            <p>なし</p>
                                        <?php else: ?>
                                            <ul>
                                                <?php foreach ($cropList as $cItem): ?>
                                                    <li><?php echo htmlspecialchars((string)$cItem, ENT_QUOTES, "UTF-8"); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </details>

                                <details class="card_detail">
                                    <summary>適用病害虫(<?php echo count($targetList); ?>)</summary>
                                    <div class="detail_body">
                                        <?php if (count($targetList) === 0): ?>
                                            <p>なし</p>
                                        <?php else: ?>
                                            <ul>
                                                <?php foreach ($targetList as $tItem): ?>
                                                    <li><?php echo htmlspecialchars((string)$tItem, ENT_QUOTES, "UTF-8"); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </details>

                            </div>

                            <div class="card_bottom">
                                <div class="shopify_mount"></div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </section>
        <?php endif; ?>
    </main>



    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- Select2 プルダウン内検索 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="./js/shopify.js"></script>
    <script src="./js/app.js"></script>
</body>

</html>
