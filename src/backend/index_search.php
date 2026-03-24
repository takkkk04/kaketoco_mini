<?php

// +++++++++++++++++++++++++++++++++++++++++++++
// =============================================
// 作物・病害虫・使用方法プルダウン
// =============================================
// +++++++++++++++++++++++++++++++++++++++++++++
$category = $_GET["category"] ?? "殺虫剤";
$keyword = trim($_GET["keyword"] ?? "");
$crop = trim($_GET["crop"] ?? "");
$target = trim($_GET["target"] ?? "");
$method = trim($_GET["method"] ?? "");
$methodGroups = [
    "散布" => ["散布"],
    "全面土壌散布" => ["全面土壌散布"],
    "常温煙霧" => ["常温煙霧"],
    "灌注" => ["灌注", "株元灌注", "灌水ﾁｭｰﾌﾞを用いた灌注処理", "苗床灌注"],
    "浸漬" => ["120分間鱗片浸漬", "30分間種球浸漬", "30分間苗浸漬"],
    "ドローン散布" => ["無人航空機による散布"],
    "その他" => [
        "主幹から株元に散布",
        "主幹部に吹きつけ",
        "散布､但し花穂の発生期にはﾏﾙﾁﾌｨﾙﾑ被覆により散布液が直接花穂に飛散しない状態で使用する｡",
        "木屑排出孔を中心に薬液が滴るまで樹幹注入",
        "本剤1g当り水1mLの割合で混合し､主幹から主枝の粗皮を環状に剥いだ部分に塗布する｡",
        "植溝内土壌散布",
        "樹幹散布",
        "添加"
    ]
];
$methodLabels = [
    ["value" => "", "label" => "指定なし"],
    ["value" => "散布", "label" => "散布"],
    ["value" => "灌注", "label" => "灌注"],
    ["value" => "ドローン散布", "label" => "ドローン散布"],
];
$sort = $_GET["sort"] ?? "score_desc";

// =============================================
// 農薬名キーワード（スペース=OR, +=AND, -=除外）
// =============================================
$normalizedKeyword = mb_convert_kana($keyword, "as", "UTF-8");
$normalizedKeyword = str_replace("\u{3000}", " ", $normalizedKeyword);
$normalizedKeyword = trim((string)preg_replace('/\s+/u', ' ', $normalizedKeyword));
$keywordWhereSql = "";
$keywordParams = [];
if ($normalizedKeyword !== "") {
    $spaceTokens = preg_split('/\s+/u', $normalizedKeyword, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $orBlocks = [];
    foreach ($spaceTokens as $token) {
        if ($token === "") {
            continue;
        }

        // "-語" は直前ブロックにぶら下げて、AND NOT として扱う
        if (strpos($token, "-") === 0 && !empty($orBlocks)) {
            $orBlocks[count($orBlocks) - 1] .= "+" . $token;
            continue;
        }

        $orBlocks[] = $token;
    }

    $orParts = [];
    $kwIndex = 0;

    foreach ($orBlocks as $block) {
        $terms = preg_split('/\++/u', trim($block), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $blockParts = [];

        foreach ($terms as $term) {
            if ($term === "-") {
                continue;
            }

            if (strpos($term, "-") === 0) {
                $exclude = substr($term, 1);
                if ($exclude === "") {
                    continue;
                }
                $key = ":kw{$kwIndex}";
                $kwIndex++;
                $blockParts[] = "p_main.name NOT LIKE {$key}";
                $keywordParams[$key] = "%" . $exclude . "%";
                continue;
            }

            $key = ":kw{$kwIndex}";
            $kwIndex++;
            $blockParts[] = "p_main.name LIKE {$key}";
            $keywordParams[$key] = "%" . $term . "%";
        }

        if (!empty($blockParts)) {
            $orParts[] = "(" . implode(" AND ", $blockParts) . ")";
        }
    }

    if (!empty($orParts)) {
        $keywordWhereSql = " AND (" . implode(" OR ", $orParts) . ")";
    }
}

// =============================================
// 作物・病害虫・使用方法絞り込み処理
// =============================================
$methodWhereSql = "";
$methodParams = [];
if ($method !== "") {
    $selectedMethods = $methodGroups[$method] ?? [$method];
    $in = [];
    foreach ($selectedMethods as $i => $m) {
        $key = ":m{$i}";
        $in[] = $key;
        $methodParams[$key] = $m;
    }
    $methodInSql = implode(",", $in);
    $methodWhereSql = " AND mf.name IN ($methodInSql)";
}

// =============================================
// DBから情報取ってくるSQLゾーン（新DB構造）
// =============================================
// =============================================
// 検索SQLの設計意図
// =============================================
// 1. 検索結果は「適用ルール単位」ではなく「農薬1商品につき1カード」で出す。
//    pesticide_rules は1農薬に対して複数行あるため、まず pesticide_id ごとに
//    MIN(prf.id) を pick_id として代表1行だけ拾っている。
//
// 2. 一覧では OEM / 販売会社違いで同じ農薬が重複表示されやすいため、
//    pesticides.hide_in_search = 0 のものだけを表示対象にしている。
//    hide_in_search=1 は、事前バッチで「クミアイ○○」「協友○○」など
//    メーカー接頭辞付きの重複候補に付与しており、検索時の負荷を軽くするため
//    一覧表示時には重い名寄せ判定を毎回行わない設計にしている。
//
// 3. 作物・病害虫・使用方法の絞り込みは picked サブクエリ側で先に行う。
//    これにより、候補集合を先に絞ってから代表行を決める形になり、
//    「条件に合う農薬だけを1件ずつ出す」という一覧用途に合った動きになる。
//
// 4. crop_count / target_count はカード表示用の集計値。
//    これは代表1行の内容ではなく、その農薬全体の登録作物数・対象数を見せたいので、
//    category単位で別集計して LEFT JOIN している。
//
// 5. rac_code / quickly / systemic / translaminar は将来 pesticides 側の
//    基本性能データと接続する想定。現時点では未実装のため NULL を返している。
//    一覧UIを先に成立させるための暫定対応。
//
// 6. ORDER BY は最終表示前のベース順序として p.name ASC を入れている。
//    実際の表示順はこのあと PHP 側で score / name / year の並び替えを行う。
//
// 注意:
// - hide_in_search は「一覧で隠すためのフラグ」であり、DB上から削除はしない。
// - 詳細ページでは将来的に OEM / メーカー違いも辿れるよう、元データは保持する。
// - 名寄せはまだ第1段階で、完全一致ベースの安全側設計。今後さらに改善余地あり。
$sql =
    "SELECT
        p.registration_number AS registration_number,
        p.name AS name,
        p.shopify_id AS shopify_id,
        p.registered_on AS registered_on,
        pr.magnification_text AS magnification,
        pr.timing_text AS timing,
        pr.times_text AS times,
        m.name AS method,
        NULL AS rac_code,
        NULL AS quickly,
        NULL AS systemic,
        NULL AS translaminar,
        COALESCE(stats.crop_count, 0) AS crop_count,
        COALESCE(stats.target_count, 0) AS target_count
    FROM (
        SELECT
            prf.pesticide_id,
            MIN(prf.id) AS pick_id
        FROM pesticide_rules prf
        JOIN pesticides p_main
            ON p_main.id = prf.pesticide_id
        LEFT JOIN crops cf
            ON cf.id = prf.crop_id
        LEFT JOIN targets tf
            ON tf.id = prf.target_id
        LEFT JOIN methods mf
            ON mf.id = prf.method_id
        WHERE
            prf.category = :category_pick
            AND p_main.hide_in_search = 0
            $keywordWhereSql
            AND (:crop1 = '' OR cf.name = :crop2)
            AND (:target1 = '' OR tf.name = :target2)
            $methodWhereSql
        GROUP BY prf.pesticide_id
    ) AS picked
    JOIN pesticide_rules pr
        ON pr.id = picked.pick_id
    JOIN pesticides p
        ON p.id = pr.pesticide_id
    LEFT JOIN methods m
        ON m.id = pr.method_id
    LEFT JOIN (
        SELECT
            pesticide_id,
            COUNT(DISTINCT crop_id) AS crop_count,
            COUNT(DISTINCT target_id) AS target_count
        FROM pesticide_rules
        WHERE category = :category_stats
        GROUP BY pesticide_id
    ) AS stats
        ON stats.pesticide_id = pr.pesticide_id
    ORDER BY p.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ":category_pick" => $category,
    ":category_stats" => $category,
    ":crop1" => $crop,
    ":crop2" => $crop,
    ":target1" => $target,
    ":target2" => $target,
] + $methodParams + $keywordParams);

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
