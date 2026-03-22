<?php

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
$methodLabels = ["散布", "灌注", "ドローン散布"];
$sort = $_GET["sort"] ?? "score_desc";

// =============================================
// 作物・病害虫・使用方法絞り込み処理
// =============================================
$selectedMethods = $methodGroups[$method] ?? [$method];
$in = [];
$methodParams = [];
foreach ($selectedMethods as $i => $m) {
    $key = ":m{$i}";
    $in[] = $key;
    $methodParams[$key] = $m;
}
$methodInSql = implode(",", $in);

// =============================================
// DBから情報取ってくるSQLゾーン（新DB構造）
// =============================================
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
        LEFT JOIN crops cf
            ON cf.id = prf.crop_id
        LEFT JOIN targets tf
            ON tf.id = prf.target_id
        LEFT JOIN methods mf
            ON mf.id = prf.method_id
        WHERE
            prf.category = :category_pick
            AND (:crop1 = '' OR cf.name = :crop2)
            AND (:target1 = '' OR tf.name = :target2)
            AND mf.name IN ($methodInSql)
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
