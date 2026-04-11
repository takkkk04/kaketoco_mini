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

$request = $_SERVER["REQUEST_METHOD"] === "POST" ? $_POST : $_GET;
$exportType = trim((string)($request["export"] ?? ""));
$pdfExportUnsupported = false;

// 入力値は未指定を許容し、揃ったときだけ防除暦を生成する
$targetCrop = "";
if (!empty($request['crop']) && in_array($request['crop'], $quickCropLabels, true)) {
    $targetCrop = $request['crop'];
}

$today = new DateTimeImmutable('today');
$startDateInput = trim((string)($request['start_date'] ?? ""));
$startDate = DateTimeImmutable::createFromFormat('Y-m-d', $startDateInput) ?: $today;
$startDate = $startDate->setTime(0, 0, 0);

$intervalDaysInput = trim((string)($request['interval_days'] ?? ""));
$intervalDays = (int)$intervalDaysInput;
if ($intervalDays < 1) {
    $intervalDays = 7;
}
$isScheduleGenerated = ($targetCrop !== "" && $startDateInput !== "" && $intervalDaysInput !== "");

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

$insecticideCandidates = [];
$fungicideCandidates = [];
if ($isScheduleGenerated) {
    $insecticideCandidates = $fetchCandidates($pdo, "殺虫剤", $targetCrop, $allowedFormulationNames);
    $fungicideCandidates = $fetchCandidates($pdo, "殺菌剤", $targetCrop, $allowedFormulationNames);
}

$racHistory = [
    "insecticide" => [],
    "fungicide" => [],
];
$usageHistory = [];

$scheduleRows = [];
if ($isScheduleGenerated) {
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

$buildExportRows = static function (
    array $rows,
    callable $formatPesticideName,
    callable $formatRac,
    callable $formatMagnification
): array {
    $exportRows = [];

    foreach ($rows as $row) {
        $exportRows[] = [
            "日程" => (string)($row["timing"] ?? "-"),
            "殺虫剤①" => $formatPesticideName($row["insecticide_1"] ?? null),
            "RAC①" => $formatRac($row["insecticide_1"] ?? null),
            "倍率①" => $formatMagnification($row["insecticide_1"] ?? null),
            "回数①" => (string)($row["usage_label_1"] ?? "-"),
            "殺虫剤②" => $formatPesticideName($row["insecticide_2"] ?? null),
            "RAC②" => $formatRac($row["insecticide_2"] ?? null),
            "倍率②" => $formatMagnification($row["insecticide_2"] ?? null),
            "回数②" => (string)($row["usage_label_2"] ?? "-"),
            "殺菌剤" => $formatPesticideName($row["fungicide"] ?? null),
            "RAC（殺菌剤）" => $formatRac($row["fungicide"] ?? null),
            "倍率（殺菌剤）" => $formatMagnification($row["fungicide"] ?? null),
            "回数（殺菌剤）" => (string)($row["usage_label_3"] ?? "-"),
        ];
    }

    return $exportRows;
};

$currentExportRows = $buildExportRows(
    $scheduleRows,
    $formatPesticideName,
    $formatRac,
    $formatMagnification
);
$schedulePayload = base64_encode((string)json_encode($currentExportRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$displayRows = [];
if ($isScheduleGenerated) {
    foreach ($scheduleRows as $row) {
        $displayRows[] = [
            "timing" => (string)$row["timing"],
            "insecticide_1" => $formatPesticideName($row["insecticide_1"]),
            "rac_1" => $formatRac($row["insecticide_1"]),
            "magnification_1" => $formatMagnification($row["insecticide_1"]),
            "usage_1" => (string)($row["usage_label_1"] ?? "-"),
            "insecticide_2" => $formatPesticideName($row["insecticide_2"]),
            "rac_2" => $formatRac($row["insecticide_2"]),
            "magnification_2" => $formatMagnification($row["insecticide_2"]),
            "usage_2" => (string)($row["usage_label_2"] ?? "-"),
            "fungicide" => $formatPesticideName($row["fungicide"]),
            "fungicide_rac" => $formatRac($row["fungicide"]),
            "fungicide_magnification" => $formatMagnification($row["fungicide"]),
            "fungicide_usage" => (string)($row["usage_label_3"] ?? "-"),
        ];
    }
} else {
    for ($i = 0; $i < $scheduleCount; $i++) {
        $displayRows[] = [
            "timing" => "",
            "insecticide_1" => "",
            "rac_1" => "",
            "magnification_1" => "",
            "usage_1" => "",
            "insecticide_2" => "",
            "rac_2" => "",
            "magnification_2" => "",
            "usage_2" => "",
            "fungicide" => "",
            "fungicide_rac" => "",
            "fungicide_magnification" => "",
            "fungicide_usage" => "",
        ];
    }
}

if ($exportType === "csv") {
    $payloadRows = [];
    $payloadRaw = trim((string)($request["schedule_payload"] ?? ""));
    if ($payloadRaw !== "") {
        $decoded = base64_decode($payloadRaw, true);
        if ($decoded !== false) {
            $parsed = json_decode($decoded, true);
            if (is_array($parsed)) {
                $payloadRows = $parsed;
            }
        }
    }

    $exportRows = $payloadRows !== [] ? $payloadRows : $currentExportRows;
    $filename = sprintf(
        'spray_schedule_%s_%s.csv',
        preg_replace('/[^A-Za-z0-9_-]/', '_', $targetCrop),
        $startDate->format('Ymd')
    );

    header("Content-Type: text/csv; charset=UTF-8");
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen("php://output", "wb");
    if ($output !== false) {
        fwrite($output, "\xEF\xBB\xBF");
        if ($exportRows !== []) {
            fputcsv($output, array_keys($exportRows[0]));
            foreach ($exportRows as $exportRow) {
                fputcsv($output, array_values($exportRow));
            }
        }
        fclose($output);
    }
    exit;
}

if ($exportType === "pdf") {
    $pdfExportUnsupported = true;
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>防除暦作成 | カケトコ mini</title>
    <link rel="stylesheet" href="./css/reset.css">
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/spray_schedule.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>

<body>
    <?php require __DIR__ . "/parts/header.php"; ?>

    <main class="app_main spray_schedule_main">
        <section class="result_section spray_schedule_section">
            <h2>防除暦 ver.0.2</h2>
            <form id="search_form" class="spray_schedule_form" method="GET" action="">
                <div class="form_row">
                    <label for="crop_select">作物</label>
                    <select name="crop" id="crop_select" class="js-select2 js-select2-single-chip" data-placeholder="作物を選択">
                        <option value=""></option>
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
                    <div class="spray_schedule_inline_field">
                        <input
                            type="date"
                            id="start_date"
                            name="start_date"
                            value="<?php echo htmlspecialchars($startDateInput, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="日付を選択">
                        <span class="spray_schedule_inline_text">から</span>
                    </div>
                </div>
                <div class="form_row">
                    <label for="interval_days">間隔</label>
                    <div class="spray_schedule_inline_field">
                        <input
                            type="number"
                            id="interval_days"
                            name="interval_days"
                            min="1"
                            value="<?php echo htmlspecialchars($intervalDaysInput, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="数字を入力">
                        <span class="spray_schedule_inline_text">日おき</span>
                    </div>
                </div>
                <div class="form_row_btn">
                    <button type="submit" id="search_btn">防除暦を生成</button>
                    <a href="./spray_schedule.php" id="reset_btn">リセット</a>
                </div>
            </form>

            <?php if ($pdfExportUnsupported): ?>
                <p class="spray_schedule_notice">
                    PDF出力は未対応です。現在の環境にPDFライブラリが入っていないため、CSV出力をご利用ください。
                </p>
            <?php endif; ?>

            <div class="spray_schedule_table_wrap">
                <table class="spray_schedule_table">
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
                        <?php foreach ($displayRows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$row["timing"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)$row["insecticide_1"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)$row["rac_1"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)$row["magnification_1"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)$row["usage_1"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)$row["insecticide_2"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)$row["rac_2"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)$row["magnification_2"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)$row["usage_2"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)$row["fungicide"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)$row["fungicide_rac"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)$row["fungicide_magnification"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)$row["fungicide_usage"], ENT_QUOTES, "UTF-8"); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form class="spray_schedule_export_form" method="POST" action="">
                <input type="hidden" name="crop" value="<?php echo htmlspecialchars($targetCrop, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($startDateInput, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="interval_days" value="<?php echo htmlspecialchars((string)$intervalDays, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="schedule_payload" value="<?php echo htmlspecialchars($schedulePayload, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="button" id="export_png_btn" class="sub_btn" <?php echo (!$isScheduleGenerated || $scheduleRows === []) ? 'disabled' : ''; ?>>画像で保存する</button>
                <button type="submit" name="export" value="csv" class="sub_btn" <?php echo (!$isScheduleGenerated || $scheduleRows === []) ? 'disabled' : ''; ?>>CSV出力</button>
                <button type="submit" name="export" value="pdf" class="sub_btn" <?php echo (!$isScheduleGenerated || $scheduleRows === []) ? 'disabled' : ''; ?>>PDF出力</button>
            </form>
        </section>
    </main>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="./js/app.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const exportButton = document.getElementById("export_png_btn");
            const tableWrap = document.querySelector(".spray_schedule_table_wrap");
            const scheduleTable = document.querySelector(".spray_schedule_table");

            if (!exportButton || !tableWrap || !scheduleTable || typeof html2canvas === "undefined") {
                return;
            }

            exportButton.addEventListener("click", function () {
                const cloneWrap = tableWrap.cloneNode(true);
                const cloneTable = cloneWrap.querySelector(".spray_schedule_table");
                const captureWidth = Math.max(
                    scheduleTable.scrollWidth,
                    scheduleTable.offsetWidth,
                    tableWrap.scrollWidth
                );

                cloneWrap.style.width = captureWidth + "px";
                cloneWrap.style.maxWidth = "none";
                cloneWrap.style.overflow = "visible";
                cloneWrap.style.position = "fixed";
                cloneWrap.style.left = "-99999px";
                cloneWrap.style.top = "0";
                cloneWrap.style.zIndex = "-1";

                if (cloneTable) {
                    cloneTable.style.width = captureWidth + "px";
                    cloneTable.style.minWidth = captureWidth + "px";
                }

                document.body.appendChild(cloneWrap);

                html2canvas(cloneWrap, {
                    backgroundColor: "#ffffff",
                    scale: window.devicePixelRatio > 1 ? 2 : 1,
                    width: captureWidth,
                    windowWidth: captureWidth,
                    scrollX: 0,
                    scrollY: 0
                }).then(function (canvas) {
                    const link = document.createElement("a");
                    link.href = canvas.toDataURL("image/png");
                    link.download = "spray_schedule.png";
                    link.click();
                }).finally(function () {
                    cloneWrap.remove();
                });
            });
        });
    </script>
</body>

</html>
