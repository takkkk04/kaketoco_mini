<?php

declare(strict_types=1);

require_once __DIR__ . "/../src/backend/db.php";
require_once __DIR__ . "/helpers_import.php";

assertCli();

const RAC_DEFAULT_CSV_PATH = __DIR__ . "/../data/20260330_rac_insecticide_test20.csv";
const RAC_DEFAULT_GROUP = "I";
const RAC_SOURCE_ENCODING = "SJIS-win";

const RAC_REQUIRED_HEADERS = [
    "有効成分-1",
    "RACｺｰﾄﾞ-1",
    "有効成分-2",
    "RACｺｰﾄﾞ-2",
    "有効成分-3",
    "RACｺｰﾄﾞ-3",
    "有効成分-4",
    "RACｺｰﾄﾞ-4",
    "有効成分-5",
    "RACｺｰﾄﾞ-5",
];

function resolveRacCsvPath(array $argv): string
{
    if (!empty($argv[1])) {
        return resolveCsvPath($argv, 1);
    }

    if (!is_file(RAC_DEFAULT_CSV_PATH)) {
        throw new InvalidArgumentException("CSVが見つかりません: " . RAC_DEFAULT_CSV_PATH);
    }

    return RAC_DEFAULT_CSV_PATH;
}

function resolveRacGroup(array $argv): string
{
    $group = trim((string)($argv[2] ?? RAC_DEFAULT_GROUP));
    return $group === "" ? RAC_DEFAULT_GROUP : strtoupper($group);
}

function readRacHeader($handle): array
{
    $header = readCsvRow($handle, RAC_SOURCE_ENCODING);

    if ($header === false || $header === []) {
        throw new RuntimeException("CSVヘッダーを読み取れませんでした。");
    }

    $header = array_map(
        static fn($value): string => normalizeImportString((string)$value),
        $header
    );

    $missing = array_values(array_diff(RAC_REQUIRED_HEADERS, $header));
    if ($missing !== []) {
        throw new RuntimeException("必須ヘッダーが不足しています: " . implode(", ", $missing));
    }

    return $header;
}

function combineRacRow(array $header, array $row): array
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

function normalizeIngredientNameForRac(?string $value): string
{
    $value = trim((string)$value);
    if ($value === "") {
        return "";
    }

    $value = mb_convert_kana($value, "KV", "UTF-8");
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
}

function normalizeRacCode(?string $value): string
{
    $value = trim((string)$value);
    if ($value === "") {
        return "";
    }

    $value = str_replace(["－", "ｰ", "―", "−", "‐", "–", "—"], "-", $value);
    return trim($value);
}

function importRacToIngredients(PDO $pdo, string $csvPath, string $racGroup): array
{
    $handle = openCsv($csvPath, RAC_SOURCE_ENCODING);
    $counts = [
        "processed_rows" => 0,
        "processed_pairs" => 0,
        "updated" => 0,
        "skipped" => 0,
        "failed" => 0,
    ];
    $lineNumber = 1;

    $updateStmt = $pdo->prepare(
        "UPDATE ingredients
         SET rac_code = :rac_code,
             rac_group = :rac_group
         WHERE name_normalized = :ingredient_name_normalized"
    );

    try {
        $header = readRacHeader($handle);

        while (($row = readCsvRow($handle, RAC_SOURCE_ENCODING)) !== false) {
            $lineNumber++;

            if ($row === []) {
                continue;
            }

            $counts["processed_rows"]++;

            try {
                $assoc = combineRacRow($header, $row);

                for ($i = 1; $i <= 5; $i++) {
                    $ingredientName = normalizeIngredientNameForRac($assoc["有効成分-{$i}"] ?? "");
                    $racCode = normalizeRacCode($assoc["RACｺｰﾄﾞ-{$i}"] ?? "");

                    if ($ingredientName === "" || $racCode === "") {
                        $counts["skipped"]++;
                        continue;
                    }

                    $counts["processed_pairs"]++;
                    $updateStmt->execute([
                        ":rac_code" => $racCode,
                        ":rac_group" => $racGroup,
                        ":ingredient_name_normalized" => $ingredientName,
                    ]);

                    if ($updateStmt->rowCount() > 0) {
                        $counts["updated"]++;
                    } else {
                        $counts["skipped"]++;
                    }
                }
            } catch (Throwable $e) {
                $counts["failed"]++;
                writeError(
                    sprintf(
                        "[import_rac_to_ingredients] row=%d error=%s",
                        $lineNumber,
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

function printRacImportSummary(array $counts): void
{
    writeInfo("processed_rows: " . (string)$counts["processed_rows"]);
    writeInfo("processed_pairs: " . (string)$counts["processed_pairs"]);
    writeInfo("updated: " . (string)$counts["updated"]);
    writeInfo("skipped: " . (string)$counts["skipped"]);
    writeInfo("failed: " . (string)$counts["failed"]);
}

try {
    $csvPath = resolveRacCsvPath($argv);
    $racGroup = resolveRacGroup($argv);
    writeInfo("Start import_rac_to_ingredients: {$csvPath} group={$racGroup}");
    $counts = importRacToIngredients($pdo, $csvPath, $racGroup);
    printRacImportSummary($counts);
    writeInfo("Finished import_rac_to_ingredients");
} catch (Throwable $e) {
    writeError("[import_rac_to_ingredients] " . $e->getMessage());
    exit(1);
}
