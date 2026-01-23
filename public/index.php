<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../src/backend/db.php";

// +++++++++++++++++++++++++++++++++++++++++++++
// =============================================
// 作物・病害虫・使用方法プルダウン
// =============================================
// +++++++++++++++++++++++++++++++++++++++++++++
$category = $_GET["category"] ?? "殺虫剤";
$crop = trim($_GET["crop"] ?? "");
$target = trim($_GET["target"] ?? "");
$method = trim($_GET["method"] ?? "散布");
$methodGroups = [
    "散布" => ["散布"],
    "全面土壌散布" => ["全面土壌散布"],
    "常温煙霧" => ["常温煙霧"],
    "灌注" => ["灌注", "株元灌注", "灌水ﾁｭｰﾌﾞを用いた灌注処理", "苗床灌注"],
    "浸漬" => ["120分間鱗片浸漬", "30分間種球浸漬", "30分間苗浸漬"],
    "ドローン散布" => ["無人航空機による散布"],
    "その他" => ["主幹から株元に散布", "主幹部に吹きつけ", "散布､但し花穂の発生期にはﾏﾙﾁﾌｨﾙﾑ被覆により散布液が直接花穂に飛散しない状態で使用する｡",
        "木屑排出孔を中心に薬液が滴るまで樹幹注入", "本剤1g当り水1mLの割合で混合し､主幹から主枝の粗皮を環状に剥いだ部分に塗布する｡",
        "植溝内土壌散布", "樹幹散布", "添加"]
];
$methodLabels = ["散布", "灌注", "ドローン散布"];
$sort = $_GET["sort"] ?? "score_desc";

// =============================================
// ザックリ検索,作物プルダウン
// =============================================
//作物プルダウン（DB自動取得ver）
// $stmt = $pdo->prepare(
//     "SELECT DISTINCT crop 
//     FROM pesticides_rules 
//     WHERE category = :category AND crop <> '' 
//     ORDER BY crop ASC
//     ");
// $stmt->execute([":category" => $category]);
// $cropOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

//ザックリ作物決め打ちプルダウン
$quickCropLabels = [
    "アスパラガス","いちご","えだまめ","おうとう","オクラ","かき","かぶ","かぼちゃ","カリフラワー",
    "かんきつ","かんしょ","きく","キャベツ","きゅうり","ごぼう","こまつな","さといも",
    "さやいんげん","さやえんどう","ししとう","しそ","しゅんぎく","しょうが","すいか","ズッキーニ",
    "セルリー","だいこん","だいず","たまねぎ","てんさい","とうがらし類","トマト","なし","なす",
    "にがうり","にら","にんじん","にんにく","ねぎ","はくさい","ばれいしょ","ピーマン","ぶどう",
    "ブロッコリー","ほうれんそう","ミニトマト","メロン","もも","やまのいも","りんご","レタス",
    "未成熟とうもろこし","茶","野菜類","非結球あぶらな科葉菜類","非結球レタス",
];

$cropOptions = [];
foreach ($quickCropLabels as $label) {
    $dbValue = mb_convert_kana($label, "k", "UTF-8"); //カタカナ半角化
    $cropOptions[$label] = $dbValue;
}

//病害虫プルダウン(DB自動取得)
$stmt = $pdo->prepare(
    "SELECT DISTINCT target 
    FROM pesticides_rules 
    WHERE category = :category AND target <> '' 
    ORDER BY target ASC
    ");
$stmt->execute([":category" => $category]);
$targetOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// +++++++++++++++++++++++++++++++++++++++++++++
// =============================================
// DBから検索結果カード情報取得
// =============================================
// +++++++++++++++++++++++++++++++++++++++++++++

// =============================================
// 作物・病害虫・使用方法絞り込み処理
// =============================================
//まず使用方法で絞る
$selectedMethods = $methodGroups[$method];
$in = [];
$methodParams = [];
foreach ($selectedMethods as $i => $m) {
    $key = ":m{$i}";
    $in[] = $key;
    $methodParams[$key] = $m;
}
$methodInSql = implode(",", $in);

// =============================================
// DBから情報取ってくるSQLゾーン
// =============================================
$sql = 
    "SELECT 
        r.*, 
        b.rac_code,
        b.registered_on,
        b.quickly,
        b.systemic,
        b.translaminar,
        b.shopify_id,
        COALESCE(stats.crop_count, 0) AS crop_count,
        COALESCE(stats.target_count, 0) AS target_count
    FROM (
        SELECT 
            registration_number,
            MIN(id) AS pick_id
        FROM pesticides_rules
        WHERE 
            category = :category_pick
            AND (:crop1 = '' OR crop = :crop2)
            AND (:target1 = '' OR target = :target2)
            AND method IN ($methodInSql)
        GROUP BY registration_number
    ) AS picked
    JOIN pesticides_rules AS r
        ON r.id = picked.pick_id
    LEFT JOIN pesticides_base AS b
        ON b.registration_number = r.registration_number
    LEFT JOIN (
        SELECT
            registration_number,
            COUNT(DISTINCT NULLIF(crop, '')) AS crop_count,
            COUNT(DISTINCT NULLIF(target, '')) AS target_count
        FROM pesticides_rules
        WHERE category = :category_stats
        GROUP BY registration_number
    ) AS stats
        ON stats.registration_number = r.registration_number
    ORDER BY r.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ":category_pick" => $category,
    ":category_stats" => $category,
    ":crop1" => $crop,
    ":crop2" => $crop,
    ":target1" => $target,
    ":target2" => $target,
] + $methodParams);

// =============================================
// 並び替え（スコア順、名前順）
// =============================================
$filtered = $stmt->fetchAll();

foreach ($filtered as &$row) {
    //カケトコスコア
    $row["_score"] = (int)kaketocoScore($row);

    //登録年度
    $d = (string)($row["registered_on"] ?? "");
    $row["_year"] = ($d !== "" && preg_match('/^\d{4}/', $d)) ? (int)substr($d, 0, 4) : 0;
}
unset($row);

if ($sort === "name_asc") {
    usort($filtered, function ($a, $b) {
        return strcmp(
            (string)($a["name"] ?? ""),
            (string)($b["name"] ?? "")
        );
    });
} elseif ($sort === "year_desc") {
    usort($filtered, function ($a, $b) {
        $ya = (int)($a["_year"] ?? 0);
        $yb = (int)($b["_year"] ?? 0);
        if ($yb !== $ya) return $yb <=> $ya;
        return strcmp(
            (string)($a["name"] ?? ""),
            (string)($b["name"] ?? ""),
        );
    });
} else {
    usort($filtered, function ($a, $b) {
        $sa = (int)($a["_score"] ?? 0);
        $sb = (int)($b["_score"] ?? 0);
        if ($sb !== $sa) {
            return $sb <=> $sa;
        }
        return strcmp(
            (string)($a["name"] ?? ""),
            (string)($b["name"] ?? "")
        );
    });
}

$count = count($filtered);

// =============================================
// カード内作物、病害虫一覧取得
// =============================================
$cropListStmt = $pdo->prepare(
    "SELECT DISTINCT crop
    FROM pesticides_rules
    WHERE registration_number = :reg
        AND category = :category
        AND crop <> ''
    ORDER BY crop ASC"
);

$targetListStmt = $pdo->prepare(
    "SELECT DISTINCT target
    FROM pesticides_rules
    WHERE registration_number = :reg
        AND category = :category
        AND target <> ''
    ORDER BY target ASC"
);

// =============================================
// カード内バッジ
// =============================================
$BADGE_DEFS = [
    ["key" => "systemic",
    "label" => "浸透移行性",
    "class" => "badge_systemic"],

    ["key" => "translaminar",
    "label" => "浸達性",
    "class" => "badge_translaminar"],

    ["key" => "quickly",
    "label" => "速効性",
    "class" => "badge_quickly",
    "min" => "4"], //速効性４以上を指定する
];

//0→表示なし、1→表示あり、min指定あるならmin以上表示
function buildBadges(array $row, array $defs) : array {
    $out = [];
    foreach ($defs as $def) {
        $key = $def["key"];

        if (!isset($row[$key])) {
            continue;
        }

        if (isset($def["min"])) {
            if ((int)$row[$key] < $def["min"]) {
                continue;
            }
        } else {
            if (empty($row[$key])) {
                continue;
            }
        }   
            $out[] = $def;
        }
        return $out;
}

// =============================================
// カケトコスコア
// =============================================
//登録年月日：~1990 =1, 1990~ =2, 2000~ =3, 2010~ =4, 2020~ =5,×6点 Max=30点
function yearScore30($registeredOn): int {
    if (empty($registeredOn)) return 6;

    $s = (string)$registeredOn;
    $y = (int)substr($s, 0, 4);

    if ($y <= 0) return 6;
    if ($y >= 1990) return 12;
    if ($y >= 2000) return 18;
    if ($y >= 2010) return 24;
    if ($y >= 2020) return 30;
    return 6;
}

//速効性：DBに入ってる数字×6点 Max=30点
function quicklyScore30($quickly): int {
    $q = (int)($quickly ?? 0);
    if ($q < 0) $q = 0;
    if ($q < 5) $q = 5;
    return $q * 6;
}

//作物数：~30 =1, 30~ =2, 40~ =3, 50~ =4, 60~ =5,×4点 Max=20点
function cropCountScore20($cropCount) : int {
    $n = (int)($cropCount ?? 0);
    if ($n >= 30) return 8;
    if ($n >= 40) return 12;
    if ($n >= 50) return 16;
    if ($n >= 60) return 20;
    return 4;
}

//浸透移行性：DBで0なら5点、１なら10点
function systemicScore10($systemic) : int {
    return !empty($systemic) ? 10 : 5;
}

//病害虫数：DBから合計30未満なら5点、30以上なら10点
function targetCountScore10($targetCount) : int {
    $n = (int)($targetCount ?? 0);
    return ($n >= 30) ? 10 : 5;
}

//カケトコスコア：全部の合計 Max=100点
function kaketocoScore(array $p) : int {
    $total =
        yearScore30($p["registered_on"] ?? null) +
        quicklyScore30($p["quickly"] ?? null) +
        cropCountScore20($p["crop_count"] ?? null) +
        systemicScore10($p["systemic"] ?? null) +
        targetCountScore10($p["target_count"] ?? null);
    return $total;
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
    <header class="app_header">
        <h1 class="app_title">
            <a href="./index.php">カケトコ mini</a>
        </h1>

        <a href="./user_create.php" class="register_btn">会員登録</a>
        
        <div class="header_menu">
            <button type="button" id="menu_btn" class="menu_btn" aria-expanded="false" aria-controls="menu_panel">
                <span class="menu_icon" aria-hidden="true"></span>
                <span class="sr_only">メニュー</span>
            </button>

            <div id="menu_panel" class="menu_panel" hidden>
                <a href="./admin/admin.php" class="menu_item">管理画面</a>
                <a href="./login.php" class="menu_item">ログイン</a>
            </div>
        </div>
    </header>

    <main class="app_main">
        <section class="search_section">
            <h2>ザックリ検索</h2>

            <form id="search_form" method="GET" action="">
                <div class="form_row">
                    <label for="category">カテゴリ</label>
                        <div class="category_picker" role="radiogroup" aria-label="カテゴリ">
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
                        <?php foreach ($cropOptions as $label =>$dbValue): ?> 
                            <!-- <select>のプルダウンの中身<option>をHTMLで作っている -->
                            <!-- htmlspecialchars()は安全装置,記号とかをエスケープする -->
                            <option value="<?php echo htmlspecialchars($dbValue, ENT_QUOTES, "UTF-8"); ?>"
                                <?php 
                                // selectedがあると検索ボタン押しても選択状態になる
                                echo($crop === $dbValue) ? "selected" : ""; ?>>
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
                                <?php echo($target === $t) ? "selected" : ""; ?>
                            >
                                <?php echo htmlspecialchars($t, ENT_QUOTES, "UTF-8"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form_row">
                    <!-- 使用方法ラジオボタン、デフォルトは"散布" -->
                    <label for="method">使用方法</label>
                    <div class="method_picker" role="radiogroup" aria-label="使用方法">
                        <?php foreach ($methodLabels as $m): ?>
                            <label class="method_item">
                                <input type="radio" name="method" 
                                    value="<?php echo htmlspecialchars($m, ENT_QUOTES, "UTF-8"); ?>"
                                    <?php echo ($method === $m) ? "checked" : ""; ?>
                                >
                                <span class="method_btn">
                                    <?php echo htmlspecialchars($m, ENT_QUOTES, "UTF-8"); ?>
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
                    value="<?php echo htmlspecialchars($sort ?? "score_desk", ENT_QUOTES, "UTF-8")?>">

            </form>
        </section>

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
                        <option value="score_desc" <?php echo ($sort ==="score_desc") ? "selected" : ""; ?>>カケトコスコア順</option>
                        <option value="name_asc" <?php echo ($sort === "name_asc") ? "selected" : "";?>>名前順</option>
                        <option value="year_desc" <?php echo ($sort === "year_desc") ? "selected" : ""; ?>>登録が新しい順</option>
                    </select>
                </div>
            </div>

            <!-- 検索結果表示エリア -->
            <div id="result_list" class="result_list">
                <?php if ($count === 0): ?>
                    <p>該当する農薬がありません。</p>
                <?php else: ?>
                    <?php foreach ($filtered as $i =>$p): ?>
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
                        ?>
                            
                        <article class="result_card">
                            <!-- 商品名 -->
                            <div class="card_title">
                                <span class="card_title_name">
                                    <?php echo htmlspecialchars(
                                        mb_convert_kana($p["name"] ?? "", "KV", "UTF-8"), ENT_QUOTES, "UTF-8"); ?>
                                </span>
                                <!-- RACコード -->
                                <?php if (!empty($p["rac_code"])): ?>
                                    <span class="rac_code">
                                        RAC:<?php echo htmlspecialchars($p["rac_code"], ENT_QUOTES, "UTF-8"); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="card_mid">
                                <!-- 商品画像 -->
                                <div class="card_left">
                                    <div 
                                        class="shopify_img shopify_cell" 
                                        data-product-id="<?php echo htmlspecialchars(
                                            (string)($p["shopify_id"] ?? ""), ENT_QUOTES, "UTF-8"); ?>">
                                    </div>
                                </div>

                                <div class="card_specs">
                                    <div class="spec_row">
                                        <span class="spec_label">希釈倍率</span>
                                        <span class="spec_val">
                                            <?php
                                                echo isset($p["magnification"])
                                                    ? htmlspecialchars((string)$p["magnification"], ENT_QUOTES, "UTF-8") : "";
                                            ?>
                                        </span>
                                    </div>

                                    <div class="spec_row">
                                        <span class="spec_label">使用回数</span>
                                        <span class="spec_val">
                                            <?php
                                                echo isset($p["times"])
                                                    ? htmlspecialchars((string)$p["times"], ENT_QUOTES, "UTF-8") : ""; 
                                            ?>
                                        </span>
                                    </div>

                                    <div class="spec_row">
                                        <span class="spec_label">収穫前日数</span>
                                        <span class="spec_val">
                                            <?php
                                                echo isset($p["timing"])
                                                    ? htmlspecialchars((string)$p["timing"], ENT_QUOTES, "UTF-8") : "";
                                            ?>
                                        </span>
                                    </div>

                                    <div class="spec_row">
                                        <span class="spec_label">使用方法</span>
                                        <span class="spec_val">
                                            <?php echo htmlspecialchars((string)$p["method"] ?? "", ENT_QUOTES, "UTF-8");?>
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
                                        <?php if (count($cropList) ===0): ?>
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
                                        <?php if (count($targetList) ===0): ?>
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
    </main>

    

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- Select2 プルダウン内検索 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="./js/shopify.js"></script>
    <script src="./js/app.js"></script>
</body>
</html>