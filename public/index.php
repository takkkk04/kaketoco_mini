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

$methodLabelMap = [];
foreach ($methodLabels as $methodLabel) {
    $methodLabelMap[(string)($methodLabel["value"] ?? "")] = (string)($methodLabel["label"] ?? "");
}

$currentFilters = [];
if ($keyword !== "") {
    $currentFilters["農薬名"] = $keyword;
}
if ($category !== "") {
    $currentFilters["カテゴリ"] = $category;
}
if (!empty($crops)) {
    $currentFilters["作物"] = implode("、", $crops);
}
if ($insect !== "") {
    $currentFilters["害虫"] = $insect;
}
if ($disease !== "") {
    $currentFilters["病害"] = $disease;
}
if ($weed !== "") {
    $currentFilters["雑草"] = $weed;
}
if ($method !== "") {
    $currentFilters["使用方法"] = $methodLabelMap[$method] ?? $method;
}

$detailCarryParams = [];
foreach (["keyword", "category", "crop", "insect", "disease", "weed", "method", "sort", "page"] as $key) {
    $value = $_GET[$key] ?? null;
    if ($value === null) {
        continue;
    }
    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $item) {
            $itemText = trim((string)$item);
            if ($itemText === "") {
                continue;
            }
            $normalized[] = $itemText;
        }
        if (empty($normalized)) {
            continue;
        }
        $detailCarryParams[$key] = $normalized;
        continue;
    }
    if ($value === "") {
        continue;
    }
    $detailCarryParams[$key] = (string)$value;
}

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
    <?php require __DIR__ . "/parts/header.php"; ?>

    <main class="app_main">
        <section class="search_section">
            <div class="search_tabs" role="tablist" aria-label="検索モード">
                <button type="button" class="search_tab_btn is-active" role="tab" aria-selected="true" aria-controls="search_panel_rough" data-search-tab="rough">ざっくり検索</button>
                <button type="button" class="search_tab_btn" role="tab" aria-selected="false" aria-controls="search_panel_detail" data-search-tab="detail">詳細検索</button>
                <button type="button" class="search_tab_btn" role="tab" aria-selected="false" aria-controls="search_panel_ai" data-search-tab="ai">AI検索</button>
            </div>

            <div id="search_panel_rough" class="search_panel search_panel_rough is-active" data-search-panel="rough" role="tabpanel">
                <h2>ざっくり検索</h2>

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
                        <select name="crop[]" id="crop" class="js-select2" multiple>
                            <option value="">指定なし</option>
                            <?php foreach ($cropOptions as $label => $dbValue): ?>
                                <option value="<?php echo htmlspecialchars($dbValue, ENT_QUOTES, "UTF-8"); ?>"
                                    <?php echo in_array($dbValue, $crops, true) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form_row">
                        <label for="insect">害虫</label>
                        <select name="insect" id="insect" class="js-select2 js-select2-single-chip" multiple>
                            <option value="">指定なし</option>
                            <?php foreach ($insectOptions as $t): ?>
                                <option value="<?php echo htmlspecialchars($t, ENT_QUOTES, "UTF-8"); ?>"
                                    <?php echo ($insect === $t) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($t, ENT_QUOTES, "UTF-8"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form_row">
                        <label for="disease">病害</label>
                        <select name="disease" id="disease" class="js-select2 js-select2-single-chip" multiple>
                            <option value="">指定なし</option>
                            <?php foreach ($diseaseOptions as $t): ?>
                                <option value="<?php echo htmlspecialchars($t, ENT_QUOTES, "UTF-8"); ?>"
                                    <?php echo ($disease === $t) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($t, ENT_QUOTES, "UTF-8"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form_row">
                        <label for="weed">雑草</label>
                        <select name="weed" id="weed" class="js-select2 js-select2-single-chip" multiple>
                            <option value="">指定なし</option>
                            <?php foreach ($weedOptions as $t): ?>
                                <option value="<?php echo htmlspecialchars($t, ENT_QUOTES, "UTF-8"); ?>"
                                    <?php echo ($weed === $t) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($t, ENT_QUOTES, "UTF-8"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form_row">
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

                    <input type="hidden" name="sort" id="sort_hidden"
                        value="<?php echo htmlspecialchars($sort ?? "score_desk", ENT_QUOTES, "UTF-8") ?>">
                    <input type="hidden" name="is_search" id="is_search_hidden"
                        value="<?php echo !empty($isSearch) ? "1" : ""; ?>">

                </form>
            </div>

            <div id="search_panel_detail" class="search_panel search_panel_detail" data-search-panel="detail" role="tabpanel" hidden>
                <h2>詳細検索</h2>
                <p class="search_panel_note">今後ここに詳細条件を追加予定です。</p>
                <div class="search_panel_placeholder">
                    <div class="form_row">
                        <label for="detail_placeholder_1">仮条件</label>
                        <input type="text" id="detail_placeholder_1" placeholder="詳細条件を追加予定" disabled>
                    </div>
                    <div class="form_row">
                        <label for="detail_placeholder_2">仮条件2</label>
                        <input type="text" id="detail_placeholder_2" placeholder="今後ここを拡張します" disabled>
                    </div>
                </div>
            </div>

            <div id="search_panel_ai" class="search_panel search_panel_ai" data-search-panel="ai" role="tabpanel" hidden>
                <h2>AI検索</h2>
                <p class="search_panel_note">自然文で条件を入力できる予定です。</p>
                <div class="search_panel_placeholder">
                    <div class="form_row">
                        <label for="ai_search_placeholder">AIへの相談内容</label>
                        <textarea id="ai_search_placeholder" rows="4" placeholder="例：トマトでアブラムシに効いて、速効性のある農薬を探したい" disabled></textarea>
                    </div>
                    <div class="form_row_btn">
                        <button type="button" id="ai_search_btn" disabled>AIで探す</button>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($hasSearchCondition && !empty($currentFilters)): ?>
            <section class="current_filters_section <?php echo !empty($shouldShowResults) ? 'current_filters_section_sticky' : ''; ?>">
                <h2>現在の検索条件</h2>
                <div class="current_filters_list">
                    <?php foreach ($currentFilters as $label => $value): ?>
                        <div class="current_filter_item">
                            <span class="current_filter_label"><?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>：</span>
                            <span class="current_filter_value"><?php echo htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8"); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($shouldShowResults)): ?>
        <section class="result_section result_section_sticky">

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
                        $showRuleSpecs = !empty($crops);
                        $mixCount = (int)($p["mix_count"] ?? 0);
                        ?>

                        <article class="result_card fav_card">
                            <div class="card_title">
                                <!-- 商品名 -->
                                <span class="card_title_name">
                                    <?php
                                    $detailQuery = ["id" => (int)($p["pesticide_id"] ?? 0)] + $detailCarryParams;
                                    $detailUrl = "./pesticide_detail.php?" . http_build_query($detailQuery);
                                    ?>
                                    <a href="<?php echo htmlspecialchars($detailUrl, ENT_QUOTES, "UTF-8"); ?>">
                                        <?php echo htmlspecialchars(
                                            mb_convert_kana($p["name"] ?? "", "KV", "UTF-8"),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </a>
                                </span>

                                <!-- RACコード -->
                                <div class="card_title_right">
                                    <?php
                                    $pesticideId = (int)($p["pesticide_id"] ?? 0);
                                    $racText = (string)($racMap[$pesticideId] ?? "");
                                    $racLabels = array_values(array_filter(array_map("trim", explode("/", $racText))));
                                    ?>
                                    <?php if ($racText !== ""): ?>
                                        <?php foreach ($racLabels as $racLabel): ?>
                                            <?php
                                            $parts = explode(":", $racLabel, 2);
                                            $isRacLink = count($parts) === 2 && trim($parts[0]) !== "" && trim($parts[1]) !== "";
                                            ?>
                                            <?php if ($isRacLink): ?>
                                                <?php
                                                $racQuery = [
                                                    "group" => trim((string)$parts[0]),
                                                    "code" => trim((string)$parts[1]),
                                                ] + $detailCarryParams;
                                                $racUrl = "./rac_list.php?" . http_build_query($racQuery);
                                                ?>
                                                <a class="rac_code" href="<?php echo htmlspecialchars($racUrl, ENT_QUOTES, "UTF-8"); ?>">
                                                    RAC:<?php echo htmlspecialchars($racLabel, ENT_QUOTES, "UTF-8"); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="rac_code">
                                                    RAC:<?php echo htmlspecialchars($racLabel, ENT_QUOTES, "UTF-8"); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
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
                                    <?php if (!empty($badges) || $mixCount >= 2): ?>
                                        <div class="badge_row">
                                            <?php if ($mixCount >= 2): ?>
                                                <span class="badge">
                                                    <?php echo $mixCount; ?>種混合
                                                </span>
                                            <?php endif; ?>
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

            <?php if (($totalPages ?? 0) > 1): ?>
                <?php
                $window = 2;
                $currentPage = (int)$page;
                $lastPage = (int)$totalPages;
                $startPage = max(1, $currentPage - $window);
                $endPage = min($lastPage, $currentPage + $window);
                $buildPageUrl = static function (int $targetPage): string {
                    $pageQuery = $_GET;
                    $pageQuery["page"] = $targetPage;
                    return "?" . http_build_query($pageQuery);
                };
                ?>
                <nav class="pagination" aria-label="ページネーション">
                    <?php if ($currentPage > 1): ?>
                        <a class="page_link page_nav" href="<?php echo htmlspecialchars($buildPageUrl($currentPage - 1), ENT_QUOTES, "UTF-8"); ?>">前へ</a>
                    <?php else: ?>
                        <span class="page_link page_nav is_disabled">前へ</span>
                    <?php endif; ?>

                    <?php if ($startPage > 1): ?>
                        <a class="page_link" href="<?php echo htmlspecialchars($buildPageUrl(1), ENT_QUOTES, "UTF-8"); ?>">1</a>
                        <?php if ($startPage > 2): ?>
                            <span class="page_ellipsis">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <a
                            class="page_link <?php echo ($currentPage === $p) ? "active" : ""; ?>"
                            href="<?php echo htmlspecialchars($buildPageUrl($p), ENT_QUOTES, "UTF-8"); ?>">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($endPage < $lastPage): ?>
                        <?php if ($endPage < $lastPage - 1): ?>
                            <span class="page_ellipsis">...</span>
                        <?php endif; ?>
                        <a class="page_link" href="<?php echo htmlspecialchars($buildPageUrl($lastPage), ENT_QUOTES, "UTF-8"); ?>">
                            <?php echo $lastPage; ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($currentPage < $lastPage): ?>
                        <a class="page_link page_nav" href="<?php echo htmlspecialchars($buildPageUrl($currentPage + 1), ENT_QUOTES, "UTF-8"); ?>">次へ</a>
                    <?php else: ?>
                        <span class="page_link page_nav is_disabled">次へ</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

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
