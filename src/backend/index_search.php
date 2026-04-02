<?php

// +++++++++++++++++++++++++++++++++++++++++++++
// =============================================
// 作物・病害虫・使用方法プルダウン
// =============================================
// +++++++++++++++++++++++++++++++++++++++++++++
$category = $_GET["category"] ?? "";
$keyword = trim($_GET["keyword"] ?? "");
$cropValues = $_GET["crop"] ?? [];
if (!is_array($cropValues)) {
    $cropValues = $cropValues === "" ? [] : [$cropValues];
}
$crops = [];
foreach ($cropValues as $cropValue) {
    $cropName = trim((string)$cropValue);
    if ($cropName === "") {
        continue;
    }
    $crops[$cropName] = $cropName;
}
$crops = array_values($crops);
$insectRaw = $_GET["insect"] ?? "";
$insect = trim((string)(is_array($insectRaw) ? ($insectRaw[0] ?? "") : $insectRaw));
$diseaseRaw = $_GET["disease"] ?? "";
$disease = trim((string)(is_array($diseaseRaw) ? ($diseaseRaw[0] ?? "") : $diseaseRaw));
$weedRaw = $_GET["weed"] ?? "";
$weed = trim((string)(is_array($weedRaw) ? ($weedRaw[0] ?? "") : $weedRaw));
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
$methodLabels = [
    ["value" => "散布", "label" => "散布"],
    ["value" => "灌注", "label" => "灌注"],
    ["value" => "ドローン散布", "label" => "ドローン散布"],
    ["value" => "", "label" => "指定なし"],
];
$sort = $_GET["sort"] ?? "score_desc";
$isSearch = isset($_GET["is_search"]) && (string)$_GET["is_search"] === "1";

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

$hasSearchCondition =
    ($category !== "") ||
    ($normalizedKeyword !== "") ||
    !empty($crops) ||
    ($insect !== "") ||
    ($disease !== "") ||
    ($weed !== "") ||
    ($method !== "");
$shouldShowResults = $isSearch && $hasSearchCondition;

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
$filtered = [];
$count = 0;
$perPage = 50;
$page = max(1, (int)($_GET["page"] ?? 1));
$totalPages = 0;

if ($shouldShowResults) {
    $pickedParams = [
        ":insect1" => $insect,
        ":insect2" => $insect,
        ":disease1" => $disease,
        ":disease2" => $disease,
        ":weed1" => $weed,
        ":weed2" => $weed,
    ];
    $statsParams = [];
    $pickedCategoryWhereSql = "";
    $statsCategoryWhereSql = "";
    if ($category !== "") {
        $pickedCategoryWhereSql = " AND prf.category = :category_pick";
        $statsCategoryWhereSql = " AND category = :category_stats";
        $pickedParams[":category_pick"] = $category;
        $statsParams[":category_stats"] = $category;
    }

    $cropWhereSql = "";
    if (!empty($crops)) {
        $cropPlaceholders = [];
        foreach ($crops as $i => $cropName) {
            $key = ":crop_and_{$i}";
            $cropPlaceholders[] = $key;
            $pickedParams[$key] = $cropName;
        }
        $pickedParams[":crop_and_count"] = count($crops);
        $cropWhereSql = " AND p_main.id IN (
            SELECT prc.pesticide_id
            FROM pesticide_rules prc
            JOIN crops cc ON cc.id = prc.crop_id
            WHERE cc.name IN (" . implode(",", $cropPlaceholders) . ")
            GROUP BY prc.pesticide_id
            HAVING COUNT(DISTINCT cc.name) = :crop_and_count
        )";
    }

// =============================================
// 検索処理（条件組み立て + 総件数取得 + 20件ページネーション）
// 条件あり時のみSQL実行し、COUNT(DISTINCT pesticide_id)で総件数を計算する
// =============================================
    $pickedFromWhereSql =
        "FROM pesticide_rules prf
        JOIN pesticides p_main
            ON p_main.id = prf.pesticide_id
        LEFT JOIN targets tf
            ON tf.id = prf.target_id
        LEFT JOIN methods mf
            ON mf.id = prf.method_id
        WHERE
            p_main.hide_in_search = 0
            $pickedCategoryWhereSql
            $keywordWhereSql
            $cropWhereSql
            AND (
                :insect1 = ''
                OR EXISTS (
                    SELECT 1
                    FROM pesticide_rules pr_i
                    JOIN targets t_i ON t_i.id = pr_i.target_id
                    WHERE pr_i.pesticide_id = prf.pesticide_id
                      AND t_i.target_type = '害虫'
                      AND t_i.name = :insect2
                )
            )
            AND (
                :disease1 = ''
                OR EXISTS (
                    SELECT 1
                    FROM pesticide_rules pr_d
                    JOIN targets t_d ON t_d.id = pr_d.target_id
                    WHERE pr_d.pesticide_id = prf.pesticide_id
                      AND t_d.target_type = '病害'
                      AND t_d.name = :disease2
                )
            )
            AND (
                :weed1 = ''
                OR EXISTS (
                    SELECT 1
                    FROM pesticide_rules pr_w
                    JOIN targets t_w ON t_w.id = pr_w.target_id
                    WHERE pr_w.pesticide_id = prf.pesticide_id
                      AND t_w.target_type = '雑草'
                      AND t_w.name = :weed2
                )
            )
            $methodWhereSql";

    $countParams = $pickedParams + $methodParams + $keywordParams;

    $countSql =
        "SELECT COUNT(DISTINCT picked.pesticide_id) AS total_count
        FROM (
            SELECT
                prf.pesticide_id
            $pickedFromWhereSql
            GROUP BY prf.pesticide_id
        ) AS picked";
    $countStmt = $pdo->prepare($countSql);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $count = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($count / $perPage);
    if ($totalPages > 0 && $page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $orderBySql = "ORDER BY
        CASE
            WHEN p.name REGEXP '^[ァ-ヶー]' THEN 1
            WHEN p.name REGEXP '^[A-Za-z]' THEN 2
            ELSE 3
        END,
        p.name ASC";
    if ($sort === "year_desc") {
        $orderBySql = "ORDER BY p.registered_on DESC, p.name ASC";
    } elseif ($sort === "score_desc") {
        $orderBySql = "ORDER BY (
            CASE
                WHEN p.registered_on IS NULL THEN 6
                WHEN YEAR(p.registered_on) >= 1990 THEN 12
                ELSE 6
            END
            + 30
            + CASE WHEN COALESCE(stats.crop_count, 0) >= 30 THEN 8 ELSE 4 END
            + 5
            + CASE WHEN COALESCE(stats.target_count, 0) >= 30 THEN 10 ELSE 5 END
        ) DESC, p.name ASC";
    }

    $queryParams = $countParams + $statsParams;

    $sql =
        "SELECT
            p.id AS pesticide_id,
            p.registration_number AS registration_number,
            p.name AS name,
            pea.shopify_id AS shopify_id,
            p.registered_on AS registered_on,
            p.mix_count AS mix_count,
            pr.magnification_text AS magnification,
            pr.timing_text AS timing,
            pr.times_text AS times,
            m.name AS method,
            NULL AS rac_code,
            pea.quickly AS quickly,
            pea.systemic AS systemic,
            pea.translaminar AS translaminar,
            COALESCE(stats.crop_count, 0) AS crop_count,
            COALESCE(stats.target_count, 0) AS target_count
        FROM (
            SELECT
                prf.pesticide_id,
                MIN(prf.id) AS pick_id
            $pickedFromWhereSql
            GROUP BY prf.pesticide_id
        ) AS picked
        JOIN pesticide_rules pr
            ON pr.id = picked.pick_id
        JOIN pesticides p
            ON p.id = pr.pesticide_id
        LEFT JOIN pesticide_extra_attributes pea
            ON pea.registration_number = p.registration_number
        LEFT JOIN methods m
            ON m.id = pr.method_id
        LEFT JOIN (
            SELECT
                pesticide_id,
                COUNT(DISTINCT crop_id) AS crop_count,
                COUNT(DISTINCT target_id) AS target_count
            FROM pesticide_rules
            WHERE 1=1
                $statsCategoryWhereSql
            GROUP BY pesticide_id
        ) AS stats
            ON stats.pesticide_id = pr.pesticide_id
        $orderBySql
        LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($queryParams as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();

    // =============================================
    // 並び替え（スコア順、名前順）
    // =============================================
    $filtered = $stmt->fetchAll();

    foreach ($filtered as &$row) {
        //カケトコスコア
        $row["_score"] = (int)kaketocoScore($row);
    }
    unset($row);
}
