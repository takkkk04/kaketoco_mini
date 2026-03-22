<?php

declare(strict_types=1);

require_once __DIR__ . "/../src/backend/db.php";
require_once __DIR__ . "/helpers_import.php";

assertCli();

const RULES_DEFAULT_CSV_PATH = __DIR__ . "/../data/rules.csv";
const RULES_SOURCE_ENCODING = "UTF-8";

const RULES_REQUIRED_HEADERS = [
    "登録番号",
    "用途",
    "農薬の種類",
    "農薬の名称",
    "登録を有する者の略称",
    "作物名",
    "適用場所",
    "適用病害虫雑草名",
    "使用目的",
    "希釈倍数使用量",
    "散布液量",
    "使用時期",
    "本剤の使用回数",
    "使用方法",
    "くん蒸時間",
    "くん蒸温度",
    "適用土壌",
    "適用地帯名",
    "適用農薬名",
    "混合数",
    "有効成分①を含む農薬の総使用回数",
    "有効成分②を含む農薬の総使用回数",
    "有効成分③を含む農薬の総使用回数",
    "有効成分④を含む農薬の総使用回数",
    "有効成分⑤を含む農薬の総使用回数",
];

const RULE_INGREDIENT_LIMIT_HEADERS = [
    1 => "有効成分①を含む農薬の総使用回数",
    2 => "有効成分②を含む農薬の総使用回数",
    3 => "有効成分③を含む農薬の総使用回数",
    4 => "有効成分④を含む農薬の総使用回数",
    5 => "有効成分⑤を含む農薬の総使用回数",
];

/**
 * 登録適用部インポート
 *
 * 対象:
 * - crops
 * - targets
 * - methods
 * - pesticide_rules
 * - rule_ingredient_limits
 *
 * 方針:
 * - crops / targets / methods は農水省原文辞書として保持する
 * - pesticide_rules は rule_hash による重複防止を前提にする
 * - import_basic.php と同じ関数分割スタイルに揃える
 */

function resolveRulesCsvPath(array $argv): string
{
    if (!empty($argv[1])) {
        return resolveCsvPath($argv, 1);
    }

    if (!is_file(RULES_DEFAULT_CSV_PATH)) {
        throw new InvalidArgumentException("CSVが見つかりません: " . RULES_DEFAULT_CSV_PATH);
    }

    return RULES_DEFAULT_CSV_PATH;
}

function readRulesHeader($handle): array
{
    $header = readCsvRow($handle, RULES_SOURCE_ENCODING);

    if ($header === false || $header === []) {
        throw new RuntimeException("CSVヘッダーを読み取れませんでした。");
    }

    $header = array_map(
        static fn($value): string => normalizeImportString((string)$value),
        $header
    );

    $missing = array_values(array_diff(RULES_REQUIRED_HEADERS, $header));

    if ($missing !== []) {
        throw new RuntimeException("必須ヘッダーが不足しています: " . implode(", ", $missing));
    }

    return $header;
}

function combineRulesRow(array $header, array $row): array
{
    if (count($row) < count($header)) {
        throw new RuntimeException("列数がヘッダーより不足しています。");
    }

    if (count($row) > count($header)) {
        $row = array_slice($row, 0, count($header));
    }

    $assoc = array_combine($header, $row);

    if ($assoc === false) {
        throw new RuntimeException("CSV行の連想配列化に失敗しました。");
    }

    return $assoc;
}

function upsertCrop(PDO $pdo, string $name): ?int
{
    $name = normalizeImportString($name);

    if ($name === "") {
        return null;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO crops (name, name_normalized, created_at, updated_at)
         VALUES (:name, :name_normalized, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            name_normalized = VALUES(name_normalized),
            updated_at = NOW()"
    );
    $stmt->execute([
        ":name" => $name,
        ":name_normalized" => $name,
    ]);

    return (int)$pdo->lastInsertId();
}

function upsertTarget(PDO $pdo, string $name): ?int
{
    $name = normalizeImportString($name);

    if ($name === "") {
        return null;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO targets (name, name_normalized, created_at, updated_at)
         VALUES (:name, :name_normalized, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            name_normalized = VALUES(name_normalized),
            updated_at = NOW()"
    );
    $stmt->execute([
        ":name" => $name,
        ":name_normalized" => $name,
    ]);

    return (int)$pdo->lastInsertId();
}

function upsertMethod(PDO $pdo, string $name): ?int
{
    $name = normalizeImportString($name);

    if ($name === "") {
        return null;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO methods (name, created_at, updated_at)
         VALUES (:name, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            updated_at = NOW()"
    );
    $stmt->execute([":name" => $name]);

    return (int)$pdo->lastInsertId();
}

function findPesticideIdByRegistrationNumber(PDO $pdo, string $registrationNumber): int
{
    $registrationNumberInt = normalizeNullableInt($registrationNumber);

    if ($registrationNumberInt === null || $registrationNumberInt <= 0) {
        throw new RuntimeException("登録番号が不正です。");
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM pesticides
         WHERE registration_number = :registration_number
         LIMIT 1"
    );
    $stmt->execute([
        ":registration_number" => $registrationNumberInt,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException("pesticides に登録番号が見つかりません。");
    }

    return (int)$row["id"];
}

function updateRegistrantShortName(PDO $pdo, int $pesticideId, string $shortName): void
{
    $shortName = normalizeNullableString($shortName);

    if ($shortName === null) {
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT registrant_id
         FROM pesticides
         WHERE id = :pesticide_id
         LIMIT 1"
    );
    $stmt->execute([
        ":pesticide_id" => $pesticideId,
    ]);
    $pesticide = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pesticide) {
        return;
    }

    $registrantId = isset($pesticide["registrant_id"]) ? (int)$pesticide["registrant_id"] : 0;

    if ($registrantId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        "UPDATE registrants
         SET
            name_short = :name_short,
            updated_at = NOW()
         WHERE id = :registrant_id
           AND (name_short IS NULL OR name_short = '')"
    );
    $stmt->execute([
        ":name_short" => $shortName,
        ":registrant_id" => $registrantId,
    ]);
}

function upsertPesticideRule(
    PDO $pdo,
    int $pesticideId,
    ?int $cropId,
    ?int $targetId,
    ?int $methodId,
    array $row
): int {
    $placeText = normalizeNullableString($row["適用場所"] ?? null);
    $magnificationText = normalizeNullableString($row["希釈倍数使用量"] ?? null);
    $timingText = normalizeNullableString($row["使用時期"] ?? null);
    $timesText = normalizeNullableString($row["本剤の使用回数"] ?? null);

    $ruleHash = generateRuleHash([
        $pesticideId,
        $cropId ?? "",
        $targetId ?? "",
        $methodId ?? "",
        $magnificationText ?? "",
        $timingText ?? "",
        $timesText ?? "",
        $placeText ?? "",
    ]);

    $stmt = $pdo->prepare(
        "INSERT INTO pesticide_rules (
            pesticide_id,
            category,
            crop_id,
            target_id,
            method_id,
            place_text,
            purpose_text,
            magnification_text,
            spray_volume_text,
            timing_text,
            times_text,
            fumigation_time_text,
            fumigation_temp_text,
            soil_text,
            zone_text,
            mix_count,
            notes,
            rule_hash,
            created_at,
            updated_at
        ) VALUES (
            :pesticide_id,
            :category,
            :crop_id,
            :target_id,
            :method_id,
            :place_text,
            :purpose_text,
            :magnification_text,
            :spray_volume_text,
            :timing_text,
            :times_text,
            :fumigation_time_text,
            :fumigation_temp_text,
            :soil_text,
            :zone_text,
            :mix_count,
            NULL,
            :rule_hash,
            NOW(),
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            pesticide_id = VALUES(pesticide_id),
            category = VALUES(category),
            crop_id = VALUES(crop_id),
            target_id = VALUES(target_id),
            method_id = VALUES(method_id),
            place_text = VALUES(place_text),
            purpose_text = VALUES(purpose_text),
            magnification_text = VALUES(magnification_text),
            spray_volume_text = VALUES(spray_volume_text),
            timing_text = VALUES(timing_text),
            times_text = VALUES(times_text),
            fumigation_time_text = VALUES(fumigation_time_text),
            fumigation_temp_text = VALUES(fumigation_temp_text),
            soil_text = VALUES(soil_text),
            zone_text = VALUES(zone_text),
            mix_count = VALUES(mix_count),
            notes = VALUES(notes),
            updated_at = NOW()"
    );
    $stmt->execute([
        ":pesticide_id" => $pesticideId,
        ":category" => normalizeNullableString($row["用途"] ?? null),
        ":crop_id" => $cropId,
        ":target_id" => $targetId,
        ":method_id" => $methodId,
        ":place_text" => $placeText,
        ":purpose_text" => normalizeNullableString($row["使用目的"] ?? null),
        ":magnification_text" => $magnificationText,
        ":spray_volume_text" => normalizeNullableString($row["散布液量"] ?? null),
        ":timing_text" => $timingText,
        ":times_text" => $timesText,
        ":fumigation_time_text" => normalizeNullableString($row["くん蒸時間"] ?? null),
        ":fumigation_temp_text" => normalizeNullableString($row["くん蒸温度"] ?? null),
        ":soil_text" => normalizeNullableString($row["適用土壌"] ?? null),
        ":zone_text" => normalizeNullableString($row["適用地帯名"] ?? null),
        ":mix_count" => normalizeNullableInt($row["混合数"] ?? null),
        ":rule_hash" => $ruleHash,
    ]);

    return (int)$pdo->lastInsertId();
}

function replaceRuleIngredientLimits(PDO $pdo, int $ruleId, array $row): void
{
    $deleteStmt = $pdo->prepare(
        "DELETE FROM rule_ingredient_limits
         WHERE rule_id = :rule_id"
    );
    $deleteStmt->execute([
        ":rule_id" => $ruleId,
    ]);

    $insertStmt = $pdo->prepare(
        "INSERT INTO rule_ingredient_limits (
            rule_id,
            ingredient_order,
            limit_text,
            created_at,
            updated_at
        ) VALUES (
            :rule_id,
            :ingredient_order,
            :limit_text,
            NOW(),
            NOW()
        )"
    );

    foreach (RULE_INGREDIENT_LIMIT_HEADERS as $order => $header) {
        $limitText = normalizeNullableString($row[$header] ?? null);

        if ($limitText === null) {
            continue;
        }

        $insertStmt->execute([
            ":rule_id" => $ruleId,
            ":ingredient_order" => $order,
            ":limit_text" => $limitText,
        ]);
    }
}

function importRulesCsv(PDO $pdo, string $csvPath): array
{
    $handle = openCsv($csvPath, RULES_SOURCE_ENCODING);
    $counts = [
        "processed" => 0,
        "succeeded" => 0,
        "failed" => 0,
        "skipped" => 0,
    ];
    $lineNumber = 1;

    try {
        $header = readRulesHeader($handle);

        while (($row = readCsvRow($handle, RULES_SOURCE_ENCODING)) !== false) {
            $lineNumber++;

            if ($row === []) {
                $counts["skipped"]++;
                continue;
            }

            $counts["processed"]++;

            try {
                $assoc = combineRulesRow($header, $row);

                $pdo->beginTransaction();

                $pesticideId = findPesticideIdByRegistrationNumber($pdo, (string)($assoc["登録番号"] ?? ""));
                updateRegistrantShortName($pdo, $pesticideId, (string)($assoc["登録を有する者の略称"] ?? ($assoc["略称"] ?? "")));
                $cropId = upsertCrop($pdo, (string)($assoc["作物名"] ?? ""));
                $targetId = upsertTarget($pdo, (string)($assoc["適用病害虫雑草名"] ?? ""));
                $methodId = upsertMethod($pdo, (string)($assoc["使用方法"] ?? ""));
                $ruleId = upsertPesticideRule($pdo, $pesticideId, $cropId, $targetId, $methodId, $assoc);
                replaceRuleIngredientLimits($pdo, $ruleId, $assoc);

                $pdo->commit();
                $counts["succeeded"]++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $counts["failed"]++;
                writeError(
                    sprintf(
                        "[import_rules] row=%d registration_number=%s error=%s",
                        $lineNumber,
                        (string)($row[0] ?? ""),
                        $e->getMessage()
                    )
                );
            }
        }
    } finally {
        fclose($handle);
    }

    return $counts;
}

function printImportSummary(array $counts): void
{
    writeInfo("Processed: " . (string)$counts["processed"]);
    writeInfo("Succeeded: " . (string)$counts["succeeded"]);
    writeInfo("Failed: " . (string)$counts["failed"]);
    writeInfo("Skipped: " . (string)$counts["skipped"]);
}

try {
    $csvPath = resolveRulesCsvPath($argv);
    writeInfo("Start import_rules: {$csvPath}");
    $counts = importRulesCsv($pdo, $csvPath);
    printImportSummary($counts);
    writeInfo("Finished import_rules");
} catch (Throwable $e) {
    writeError("[import_rules] " . $e->getMessage());
    exit(1);
}
