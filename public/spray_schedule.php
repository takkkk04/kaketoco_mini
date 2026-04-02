<?php

declare(strict_types=1);

require_once __DIR__ . "/../src/backend/index_bootstrap.php";

$targetCrop = "トマト";
$scheduleCount = 10;

$fetchCandidates = static function (PDO $pdo, string $category, string $cropName): array {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT
            p.id,
            p.name,
            p.registration_number
        FROM pesticides p
        JOIN pesticide_rules pr
            ON pr.pesticide_id = p.id
        JOIN crops c
            ON c.id = pr.crop_id
        WHERE p.category = :category
          AND c.name = :crop_name
        ORDER BY p.name ASC"
    );
    $stmt->execute([
        ":category" => $category,
        ":crop_name" => $cropName,
    ]);
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

$insecticideCandidates = $fetchCandidates($pdo, "殺虫剤", $targetCrop);
$fungicideCandidates = $fetchCandidates($pdo, "殺菌剤", $targetCrop);

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
                            <th>回</th>
                            <th>散布タイミング</th>
                            <th>殺虫剤①</th>
                            <th>殺虫剤②</th>
                            <th>殺菌剤</th>
                            <th>メモ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduleRows as $row): ?>
                            <tr>
                                <td><?php echo (int)$row["round"]; ?>回</td>
                                <td><?php echo htmlspecialchars((string)$row["timing"], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatPesticideName($row["insecticide_1"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatPesticideName($row["insecticide_2"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($formatPesticideName($row["fungicide"]), ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars((string)$row["memo"], ENT_QUOTES, "UTF-8"); ?></td>
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
