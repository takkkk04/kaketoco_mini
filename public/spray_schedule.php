<?php
// =============================================
// 防除暦作成
// =============================================
// ■ 防除暦 ver.0.1 ルール

// ・作物：GETパラメータで選択（デフォルト：トマト）
// ・カテゴリ：殺虫剤 / 殺菌剤のみ
// ・使用方法：散布のみ
// ・剤型：乳剤 / 水溶剤 / 水和剤 / 液剤のみ
// ・pesticidesテーブル hide_in_search = 1 の農薬は除外
// ・使用回数が NULL / 空 / '-' のものは除外
// ・倍率に「原液」を含むものは除外
// ・倍率は最小値のみ表示（例：2000~4000倍 → 2000倍）
// ・RACコードが NULL / '-' のものは除外
// ・RAC表示：殺虫剤は I:◯◯、殺菌剤は F:◯◯
// ・同じRACは使用後3回空ける（次に使えるのは4回後）
// ・各回：殺虫剤2種 + 殺菌剤1種をランダム選択
// ・同一回で殺虫剤①と②は同じ農薬を使わない
// ・同一回で殺虫剤①と②は同じRACを使わない
// ・使用回数は「現在回数/上限回数回」で表示する（例：1/3回）
// ・上限回数に達した農薬は以後の候補から除外する

declare(strict_types=1);

require_once __DIR__ . "/../src/backend/index_bootstrap.php";

// 作物候補を index_options.php から流用
// index_options.php が要求する $category / $crops をスタブで定義
$category = '';
$crops = [];
require_once __DIR__ . "/../src/backend/index_options.php";

// GET から作物を受け取る（デフォルト：トマト）
$targetCrop = "トマト";
if (!empty($_GET['crop']) && in_array($_GET['crop'], $quickCropLabels, true)) {
    $targetCrop = $_GET['crop'];
}

$today = new DateTimeImmutable('today');
$startDateInput = trim((string)($_GET['start_date'] ?? $today->format('Y-m-d')));
$startDate = DateTimeImmutable::createFromFormat('Y-m-d', $startDateInput) ?: $today;
$startDate = $startDate->setTime(0, 0, 0);
$startDateInput = $startDate->format('Y-m-d');

$intervalDays = (int)($_GET['interval_days'] ?? 7);
if ($intervalDays < 1) {
    $intervalDays = 7;
}

$scheduleCount = 10;
$allowedFormulationNames = ["乳剤", "水溶剤", "水和剤", "液剤"];
$weekdayMap = [
    'Sun' => '日',
    'Mon' => '月',
    'Tue' => '火',
    'Wed' => '水',
    'Thu' => '木',
    'Fri' => '金',
    'Sat' => '土',
];

$fetchCandidates = static function (PDO $pdo, string $category, string $cropName, array $formulationNames): array {
    $params = [
        ":category" => $category,
        ":crop_name" => $cropName,
        ":category_pick" => $category,
        ":crop_name_pick" => $cropName,
    ];
    $formulationPickPlaceholders = [];
    $formulationOuterPlaceholders = [];
    foreach (array_values($formulationNames) as $i => $formulationName) {
        $pickKey = ":formulation_pick_{$i}";
        $outerKey = ":formulation_outer_{$i}";
        $formulationPickPlaceholders[] = $pickKey;
        $formulationOuterPlaceholders[] = $outerKey;
        $params[$pickKey] = $formulationName;
        $params[$outerKey] = $formulationName;
    }

    $stmt = $pdo->prepare(
        "SELECT
            p.id,
            p.name,
            p.registration_number,
            rac.rac_label,
            pr.magnification_text,
            pr.times_text
        FROM pesticides p
        JOIN pesticide_rules pr
            ON pr.pesticide_id = p.id
        JOIN crops c
            ON c.id = pr.crop_id
        LEFT JOIN formulations f
            ON f.id = p.formulation_id
        LEFT JOIN methods m
            ON m.id = pr.method_id
        LEFT JOIN (
            SELECT
                pi2.pesticide_id,
                MIN(
                    CASE
                        WHEN irl2.rac_code IS NULL OR irl2.rac_code = '' OR irl2.rac_code = '-' THEN NULL
                        WHEN irl2.rac_group IS NULL OR irl2.rac_group = '' THEN irl2.rac_code
                        ELSE CONCAT(irl2.rac_group, ':', irl2.rac_code)
                    END
                ) AS rac_label
            FROM pesticide_ingredients pi2
            LEFT JOIN ingredient_rac_labels irl2
                ON irl2.ingredient_id = pi2.ingredient_id
            GROUP BY pi2.pesticide_id
        ) rac
            ON rac.pesticide_id = p.id
        JOIN (
            SELECT
                p2.id AS pesticide_id,
                MIN(pr2.id) AS rule_id
            FROM pesticides p2
            JOIN pesticide_rules pr2
                ON pr2.pesticide_id = p2.id
            JOIN crops c2
                ON c2.id = pr2.crop_id
            LEFT JOIN formulations f2
                ON f2.id = p2.formulation_id
            LEFT JOIN methods m2
                ON m2.id = pr2.method_id
            WHERE p2.category = :category_pick
              AND p2.hide_in_search = 0
              AND c2.name = :crop_name_pick
              AND m2.name = '散布'
              AND pr2.magnification_text IS NOT NULL
              AND pr2.magnification_text NOT LIKE '%原液%'
              AND pr2.times_text IS NOT NULL
              AND pr2.times_text <> ''
              AND pr2.times_text <> '-'
              AND f2.name IN (" . implode(",", $formulationPickPlaceholders) . ")
              AND EXISTS (
                    SELECT 1
                    FROM pesticide_ingredients pi3
                    JOIN ingredient_rac_labels irl3
                        ON irl3.ingredient_id = pi3.ingredient_id
                    WHERE pi3.pesticide_id = p2.id
                      AND irl3.rac_code IS NOT NULL
                      AND irl3.rac_code <> ''
                      AND irl3.rac_code <> '-'
              )
            GROUP BY p2.id
        ) picked
            ON picked.pesticide_id = p.id
           AND picked.rule_id = pr.id
        WHERE p.category = :category
          AND p.hide_in_search = 0
          AND c.name = :crop_name
          AND m.name = '散布'
          AND pr.magnification_text IS NOT NULL
          AND pr.magnification_text NOT LIKE '%原液%'
          AND pr.times_text IS NOT NULL
          AND pr.times_text <> ''
          AND pr.times_text <> '-'
          AND rac.rac_label IS NOT NULL
          AND rac.rac_label <> ''
          AND rac.rac_label <> '-'
          AND f.name IN (" . implode(",", $formulationOuterPlaceholders) . ")
        ORDER BY p.name ASC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};

$pickRandomOne = static function (array $candidates): ?array {
    if ($candidates === []) {
        return null;
    }
    $index = array_rand($candidates);
    return $candidates[$index];
};

$pickRandomTwoDistinct = static function (array $candidates): array {
    if ($candidates === []) {
        return [null, null];
    }

    if (count($candidates) === 1) {
        return [$candidates[0], null];
    }

    $indexes = array_rand($candidates, 2);
    return [$candidates[$indexes[0]], $candidates[$indexes[1]]];
};

$getRacKey = static function (?array $candidate): ?string {
    if ($candidate === null) {
        return null;
    }

    $racKey = trim((string)($candidate["rac_label"] ?? ""));
    return ($racKey === "" || $racKey === "-") ? null : $racKey;
};

$filterByRacRotation = static function (
    array $candidates,
    array $history,
    int $currentRound
) use ($getRacKey): array {
    $filtered = [];

    foreach ($candidates as $candidate) {
        $racKey = $getRacKey($candidate);
        if ($racKey === null) {
            continue;
        }

        $lastUsedRound = $history[$racKey] ?? null;
        if ($lastUsedRound === null || ($currentRound - (int)$lastUsedRound) >= 4) {
            $filtered[] = $candidate;
        }
    }

    return $filtered;
};

$parseMaxUsageCount = static function (?string $value): ?int {
    $text = trim((string)$value);
    if ($text === "") {
        return null;
    }

    if (preg_match('/(\d+)/u', $text, $matches) !== 1) {
        return null;
    }

    return (int)$matches[1];
};

$filterByUsageLimit = static function (
    array $candidates,
    array $usageHistory
) use ($parseMaxUsageCount): array {
    $filtered = [];

    foreach ($candidates as $candidate) {
        $pesticideId = (int)($candidate["id"] ?? 0);
        if ($pesticideId <= 0) {
            continue;
        }

        $maxCount = $parseMaxUsageCount((string)($candidate["times_text"] ?? ""));
        if ($maxCount === null) {
            $filtered[] = $candidate;
            continue;
        }

        $usedCount = (int)($usageHistory[$pesticideId] ?? 0);
        if ($usedCount < $maxCount) {
            $filtered[] = $candidate;
        }
    }

    return $filtered;
};

$filterByDifferentRac = static function (
    array $candidates,
    string $excludeRacKey
) use ($getRacKey): array {
    $filtered = [];

    foreach ($candidates as $candidate) {
        $racKey = $getRacKey($candidate);
        if ($racKey === null || $racKey === $excludeRacKey) {
            continue;
        }
        $filtered[] = $candidate;
    }

    return $filtered;
};

$filterByDifferentPesticide = static function (array $candidates, int $excludePesticideId): array {
    $filtered = [];

    foreach ($candidates as $candidate) {
        $pesticideId = (int)($candidate["id"] ?? 0);
        if ($pesticideId <= 0 || $pesticideId === $excludePesticideId) {
            continue;
        }
        $filtered[] = $candidate;
    }

    return $filtered;
};

$buildUsageLabel = static function (
    ?array $candidate,
    array $usageHistory
) use ($parseMaxUsageCount): string {
    if ($candidate === null) {
        return "-";
    }

    $pesticideId = (int)($candidate["id"] ?? 0);
    $maxCount = $parseMaxUsageCount((string)($candidate["times_text"] ?? ""));
    $usedCount = (int)($usageHistory[$pesticideId] ?? 0);

    if ($pesticideId <= 0 || $maxCount === null || $usedCount <= 0) {
        return "-";
    }

    return $usedCount . "/" . $maxCount . "回";
};

$insecticideCandidates = $fetchCandidates($pdo, "殺虫剤", $targetCrop, $allowedFormulationNames);
$fungicideCandidates = $fetchCandidates($pdo, "殺菌剤", $targetCrop, $allowedFormulationNames);

$racHistory = [
    "insecticide" => [],
    "fungicide" => [],
];
$usageHistory = [];

$scheduleRows = [];
for ($i = 1; $i <= $scheduleCount; $i++) {
    $availableInsecticides = $filterByRacRotation($insecticideCandidates, $racHistory["insecticide"], $i);
    if ($availableInsecticides === []) {
        $availableInsecticides = $insecticideCandidates;
    }
    $availableInsecticides = $filterByUsageLimit($availableInsecticides, $usageHistory);
    if ($availableInsecticides === []) {
        $availableInsecticides = $filterByUsageLimit($insecticideCandidates, $usageHistory);
    }

    $availableFungicides = $filterByRacRotation($fungicideCandidates, $racHistory["fungicide"], $i);
    if ($availableFungicides === []) {
        $availableFungicides = $fungicideCandidates;
    }
    $availableFungicides = $filterByUsageLimit($availableFungicides, $usageHistory);
    if ($availableFungicides === []) {
        $availableFungicides = $filterByUsageLimit($fungicideCandidates, $usageHistory);
    }

    $insecticide1 = $pickRandomOne($availableInsecticides);
    $insecticide1Id = (int)($insecticide1["id"] ?? 0);
    $insecticide1Rac = $getRacKey($insecticide1);

    $availableInsecticide2 = $availableInsecticides;
    if ($insecticide1Id > 0) {
        $availableInsecticide2 = $filterByDifferentPesticide($availableInsecticide2, $insecticide1Id);
    }
    if ($insecticide1Rac !== null) {
        $differentRacCandidates = $filterByDifferentRac($availableInsecticide2, $insecticide1Rac);
        if ($differentRacCandidates !== []) {
            $availableInsecticide2 = $differentRacCandidates;
        }
    }
    if ($availableInsecticide2 === [] && $insecticide1Id > 0) {
        $availableInsecticide2 = $filterByDifferentPesticide($availableInsecticides, $insecticide1Id);
    }

    $insecticide2 = $pickRandomOne($availableInsecticide2);
    $fungicide = $pickRandomOne($availableFungicides);

    $selectedPesticides = [$insecticide1, $insecticide2, $fungicide];
    foreach ($selectedPesticides as $selectedPesticide) {
        $pesticideId = (int)($selectedPesticide["id"] ?? 0);
        if ($pesticideId <= 0) {
            continue;
        }
        $usageHistory[$pesticideId] = (int)($usageHistory[$pesticideId] ?? 0) + 1;
    }

    $insecticideRac1 = $getRacKey($insecticide1);
    if ($insecticideRac1 !== null) {
        $racHistory["insecticide"][$insecticideRac1] = $i;
    }

    $insecticideRac2 = $getRacKey($insecticide2);
    if ($insecticideRac2 !== null) {
        $racHistory["insecticide"][$insecticideRac2] = $i;
    }

    $fungicideRac = $getRacKey($fungicide);
    if ($fungicideRac !== null) {
        $racHistory["fungicide"][$fungicideRac] = $i;
    }

    $timingDate = $startDate->modify('+' . (($i - 1) * $intervalDays) . ' days');

    $weekdayEn = $timingDate->format('D');
    $weekdayJa = $weekdayMap[$weekdayEn] ?? $weekdayEn;

    $scheduleRows[] = [
        "round" => $i,
        "timing" => $timingDate->format('n/j') . '(' . $weekdayJa . ')',
        "insecticide_1" => $insecticide1,
        "insecticide_2" => $insecticide2,
        "fungicide" => $fungicide,
        "usage_label_1" => $buildUsageLabel($insecticide1, $usageHistory),
        "usage_label_2" => $buildUsageLabel($insecticide2, $usageHistory),
        "usage_label_3" => $buildUsageLabel($fungicide, $usageHistory),
    ];
}

$formatPesticideName = static function (?array $pesticide): string {
    if ($pesticide === null) {
        return "-";
    }
    $name = trim((string)($pesticide["name"] ?? ""));
    return $name === "" ? "-" : $name;
};

$formatRuleValue = static function (?array $pesticide, string $key): string {
    if ($pesticide === null) {
        return "-";
    }
    $value = trim((string)($pesticide[$key] ?? ""));
    return $value === "" ? "-" : $value;
};

$formatTimes = static function (?array $pesticide): string {
    if ($pesticide === null) {
        return "-";
    }

    $value = trim((string)($pesticide["times_text"] ?? ""));
    if ($value === "") {
        return "-";
    }

    return trim(str_replace("以内", "", $value));
};

$formatRac = static function (?array $pesticide): string {
    if ($pesticide === null) {
        return "-";
    }

    $racLabel = trim((string)($pesticide["rac_label"] ?? ""));
    if ($racLabel === "" || $racLabel === "-") {
        return "-";
    }

    return $racLabel;
};

$formatMagnification = static function (?array $pesticide): string {
    if ($pesticide === null) {
        return "-";
    }

    $value = trim((string)($pesticide["magnification_text"] ?? ""));
    if ($value === "") {
        return "-";
    }

    $normalized = str_replace("〜", "~", $value);
    $leftPart = trim(explode("~", $normalized, 2)[0]);

    if (preg_match('/([0-9]+(?:\.[0-9]+)?)/u', $leftPart, $matches) !== 1) {
        return $value;
    }

    return $matches[1] . "倍";
};
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>防除暦作成 | カケトコ mini</title>
    <link rel="stylesheet" href="./css/reset.css">
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/pesticide_detail.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>

<body>
    <?php require __DIR__ . "/parts/header.php"; ?>

    <main class="app_main">
        <section class="result_section">
            <form id="search_form" method="GET" action="">
                <div class="form_row">
                    <label for="crop_select">作物</label>
                    <select name="crop" id="crop_select" class="js-select2 js-select2-single-chip">
                        <?php foreach ($quickCropLabels as $label): ?>
                            <option value="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo ($label === $targetCrop) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form_row">
                    <label for="start_date">開始日</label>
                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        value="<?php echo htmlspecialchars($startDateInput, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form_row">
                    <label for="interval_days">何日おき</label>
                    <input
                        type="number"
                        id="interval_days"
                        name="interval_days"
                        min="1"
                        value="<?php echo htmlspecialchars((string)$intervalDays, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form_row_btn">
                    <button type="submit" id="search_btn">防除暦を生成</button>
                </div>
            </form>

            <h2>防除暦 ver.0.1</h2>
            <p><?php echo htmlspecialchars($targetCrop, ENT_QUOTES, "UTF-8"); ?> / <?php echo $scheduleCount; ?>回分 / 仮生成</p>

            <div class="table_wrap">
                <table class="detail_table">
                    <thead>
                        <tr>
                            <th>日程</th>
                            <th>殺虫剤①</th>
                            <th>RAC</th>
                            <th>倍率</th>
                            <th>回数</th>
                            <th>殺虫剤②</th>
                            <th>RAC</th>
                            <th>倍率</th>
                            <th>回数</th>
                            <th>殺菌剤</th>
                            <th>RAC</th>
                            <th>倍率</th>
                            <th>回数</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduleRows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$row["timing"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatPesticideName($row["insecticide_1"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatRac($row["insecticide_1"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatMagnification($row["insecticide_1"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)($row["usage_label_1"] ?? "-"), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatPesticideName($row["insecticide_2"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatRac($row["insecticide_2"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatMagnification($row["insecticide_2"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)($row["usage_label_2"] ?? "-"), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatPesticideName($row["fungicide"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatRac($row["fungicide"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatMagnification($row["fungicide"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)($row["usage_label_3"] ?? "-"), ENT_QUOTES, "UTF-8"); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="./js/app.js"></script>
</body>

</html>
