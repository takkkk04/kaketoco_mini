<?php

declare(strict_types=1);

require_once __DIR__ . "/../src/backend/db.php";
require_once __DIR__ . "/helpers_import.php";

assertCli();

const BASIC_DEFAULT_CSV_PATH = __DIR__ . "/../data/basic.csv";
// 農水省CSVは現状 SJIS-win 前提で読み込む
const BASIC_SOURCE_ENCODING = "SJIS-win";

const BASIC_REQUIRED_HEADERS = [
    "登録番号",
    "農薬の種類",
    "農薬の名称",
    "登録を有する者の名称",
    "有効成分",
    "総使用回数における有効成分",
    "濃度",
    "混合数",
    "用途",
    "剤型名",
    "登録年月日",
];

/**
 * 登録基本部インポート
 *
 * 対象:
 * - registrants
 * - formulations
 * - ingredients
 * - pesticides
 * - pesticide_ingredients
 *
 * 方針:
 * - 既存PDO接続を流用する
 * - 入力文字コードは定数で差し替え可能にする
 * - UPSERT系SQLで辞書と基本情報を積む
 */

function resolveBasicCsvPath(array $argv): string
{
    if (!empty($argv[1])) {
        return resolveCsvPath($argv, 1);
    }

    if (!is_file(BASIC_DEFAULT_CSV_PATH)) {
        throw new InvalidArgumentException("CSVが見つかりません: " . BASIC_DEFAULT_CSV_PATH);
    }

    return BASIC_DEFAULT_CSV_PATH;
}

function readBasicHeader($handle): array
{
    $header = readCsvRow($handle, BASIC_SOURCE_ENCODING);

    if ($header === false || $header === [null] || $header === []) {
        throw new RuntimeException("CSVヘッダーを読み取れませんでした。");
    }

    $header = array_map(
        static fn($value): string => normalizeImportString((string)$value),
        $header
    );

    $missing = array_values(array_diff(BASIC_REQUIRED_HEADERS, $header));

    if ($missing !== []) {
        throw new RuntimeException("必須ヘッダーが不足しています: " . implode(", ", $missing));
    }

    return $header;
}

function combineBasicRow(array $header, array $row): array
{
    if (count($row) !== count($header)) {
        throw new RuntimeException("列数がヘッダーと一致しません。");
    }

    $assoc = array_combine($header, $row);

    if ($assoc === false) {
        throw new RuntimeException("CSV行の連想配列化に失敗しました。");
    }

    return $assoc;
}

function upsertRegistrant(PDO $pdo, string $name): ?int
{
    $name = normalizeImportString($name);

    if ($name === "") {
        return null;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO registrants (name, name_short, created_at, updated_at)
         VALUES (:name, NULL, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            updated_at = NOW()"
    );
    $stmt->execute([":name" => $name]);

    return (int)$pdo->lastInsertId();
}

function upsertFormulation(PDO $pdo, string $name): ?int
{
    $name = normalizeImportString($name);

    if ($name === "") {
        return null;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO formulations (name, created_at, updated_at)
         VALUES (:name, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            updated_at = NOW()"
    );
    $stmt->execute([":name" => $name]);

    return (int)$pdo->lastInsertId();
}

function upsertIngredient(PDO $pdo, string $name): ?int
{
    $name = normalizeImportString($name);

    if ($name === "") {
        return null;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO ingredients (name, name_normalized, created_at, updated_at)
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

function upsertPesticide(PDO $pdo, array $row, ?int $registrantId, ?int $formulationId): int
{
    $registrationNumber = (int)normalizeImportString($row["登録番号"] ?? "");

    if ($registrationNumber <= 0) {
        throw new RuntimeException("登録番号が不正です。");
    }

    $stmt = $pdo->prepare(
        "INSERT INTO pesticides (
            registration_number,
            category,
            name,
            pesticide_type,
            registrant_id,
            formulation_id,
            registered_on,
            mix_count,
            created_at,
            updated_at
        ) VALUES (
            :registration_number,
            :category,
            :name,
            :pesticide_type,
            :registrant_id,
            :formulation_id,
            :registered_on,
            :mix_count,
            NOW(),
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            category = VALUES(category),
            name = VALUES(name),
            pesticide_type = VALUES(pesticide_type),
            registrant_id = VALUES(registrant_id),
            formulation_id = VALUES(formulation_id),
            registered_on = VALUES(registered_on),
            mix_count = VALUES(mix_count),
            updated_at = NOW()"
    );
    $stmt->execute([
        ":registration_number" => $registrationNumber,
        ":category" => normalizeNullableString($row["用途"] ?? null),
        ":name" => normalizeImportString($row["農薬の名称"] ?? ""),
        ":pesticide_type" => normalizeNullableString($row["農薬の種類"] ?? null),
        ":registrant_id" => $registrantId,
        ":formulation_id" => $formulationId,
        ":registered_on" => parseJapaneseDate($row["登録年月日"] ?? null),
        ":mix_count" => (int)normalizeImportString($row["混合数"] ?? "0"),
    ]);

    return (int)$pdo->lastInsertId();
}

function upsertPesticideIngredient(PDO $pdo, int $pesticideId, int $ingredientId, array $row): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO pesticide_ingredients (
            pesticide_id,
            ingredient_id,
            concentration_text,
            as_total_usage_ingredient,
            created_at,
            updated_at
        ) VALUES (
            :pesticide_id,
            :ingredient_id,
            :concentration_text,
            :as_total_usage_ingredient,
            NOW(),
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            concentration_text = VALUES(concentration_text),
            as_total_usage_ingredient = VALUES(as_total_usage_ingredient),
            updated_at = NOW()"
    );
    $stmt->execute([
        ":pesticide_id" => $pesticideId,
        ":ingredient_id" => $ingredientId,
        ":concentration_text" => normalizeNullableString($row["濃度"] ?? null),
        ":as_total_usage_ingredient" => normalizeNullableString($row["総使用回数における有効成分"] ?? null),
    ]);
}

function importBasicCsv(PDO $pdo, string $csvPath): array
{
    $handle = openCsv($csvPath, BASIC_SOURCE_ENCODING);
    $counts = [
        "processed" => 0,
        "succeeded" => 0,
        "failed" => 0,
        "skipped" => 0,
    ];
    $lineNumber = 1;

    try {
        $header = readBasicHeader($handle);

        while (($row = readCsvRow($handle, BASIC_SOURCE_ENCODING)) !== false) {
            $lineNumber++;

            if ($row === [null] || $row === []) {
                $counts["skipped"]++;
                continue;
            }

            $counts["processed"]++;

            try {
                $assoc = combineBasicRow($header, $row);

                $pdo->beginTransaction();

                $registrantId = upsertRegistrant($pdo, $assoc["登録を有する者の名称"] ?? "");
                $formulationId = upsertFormulation($pdo, $assoc["剤型名"] ?? "");
                $ingredientId = upsertIngredient($pdo, $assoc["有効成分"] ?? "");
                $pesticideId = upsertPesticide($pdo, $assoc, $registrantId, $formulationId);

                if ($ingredientId !== null) {
                    upsertPesticideIngredient($pdo, $pesticideId, $ingredientId, $assoc);
                }

                $pdo->commit();
                $counts["succeeded"]++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $counts["failed"]++;
                writeError(
                    sprintf(
                        "[import_basic] row=%d registration_number=%s error=%s",
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
    $csvPath = resolveBasicCsvPath($argv);
    writeInfo("Start import_basic: {$csvPath}");
    $counts = importBasicCsv($pdo, $csvPath);
    printImportSummary($counts);
    writeInfo("Finished import_basic");
} catch (Throwable $e) {
    writeError("[import_basic] " . $e->getMessage());
    exit(1);
}
