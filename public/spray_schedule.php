<?php

declare(strict_types=1);

require_once __DIR__ . "/../src/backend/index_bootstrap.php";

$targetCrop = "トマト";
$scheduleCount = 10;
$allowedFormulationNames = ["乳剤", "水溶剤", "水和剤", "液剤"];

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
              AND c2.name = :crop_name_pick
              AND m2.name = '散布'
              AND f2.name IN (" . implode(",", $formulationPickPlaceholders) . ")
            GROUP BY p2.id
        ) picked
            ON picked.pesticide_id = p.id
           AND picked.rule_id = pr.id
        WHERE p.category = :category
          AND c.name = :crop_name
          AND m.name = '散布'
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

$insecticideCandidates = $fetchCandidates($pdo, "殺虫剤", $targetCrop, $allowedFormulationNames);
$fungicideCandidates = $fetchCandidates($pdo, "殺菌剤", $targetCrop, $allowedFormulationNames);

$scheduleRows = [];
for ($i = 1; $i <= $scheduleCount; $i++) {
    [$insecticide1, $insecticide2] = $pickRandomTwoDistinct($insecticideCandidates);
    $fungicide = $pickRandomOne($fungicideCandidates);

    $scheduleRows[] = [
        "round" => $i,
        "timing" => $i . "週目",
        "insecticide_1" => $insecticide1,
        "insecticide_2" => $insecticide2,
        "fungicide" => $fungicide,
        "memo" => "",
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
            <h2>防除暦 ver.0.1</h2>
            <p><?php echo htmlspecialchars($targetCrop, ENT_QUOTES, "UTF-8"); ?> / <?php echo $scheduleCount; ?>回分 / 仮生成</p>

            <div class="table_wrap">
                <table class="detail_table">
                    <thead>
                        <tr>
                            <th>日程</th>
                            <th>殺虫剤①</th>
                            <th>RAC①</th>
                            <th>倍率①</th>
                            <th>回数①</th>
                            <th>殺虫剤②</th>
                            <th>RAC②</th>
                            <th>倍率②</th>
                            <th>回数②</th>
                            <th>殺菌剤</th>
                            <th>RAC（殺菌剤）</th>
                            <th>倍率（殺菌剤）</th>
                            <th>回数（殺菌剤）</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduleRows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$row["timing"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatPesticideName($row["insecticide_1"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatRac($row["insecticide_1"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatMagnification($row["insecticide_1"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatRuleValue($row["insecticide_1"], "times_text"), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatPesticideName($row["insecticide_2"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatRac($row["insecticide_2"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatMagnification($row["insecticide_2"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatRuleValue($row["insecticide_2"], "times_text"), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatPesticideName($row["fungicide"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatRac($row["fungicide"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatMagnification($row["fungicide"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatRuleValue($row["fungicide"], "times_text"), ENT_QUOTES, "UTF-8"); ?></td>
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
