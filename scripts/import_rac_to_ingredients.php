<?php
// =============================================
// RACコードCSVインポート
// =============================================
// RACコードはクロップライフジャパン（https://www.croplifejapan.org/activity/mechanism.html）
// RACコード検索表をダウンロード
// 殺虫剤、殺菌剤、除草剤を切り分けCSV（UTF-8）で保存

declare(strict_types=1);

require_once __DIR__ . "/../src/backend/db.php";
require_once __DIR__ . "/helpers_import.php";

assertCli();

const RAC_DEFAULT_CSV_PATH = __DIR__ . "/../data/20260330_rac_insecticide_test20.csv";
const RAC_DEFAULT_GROUP = "I";
const RAC_SOURCE_ENCODING = "UTF-8";

const RAC_REQUIRED_HEADERS = [
    "有効成分-1",
    "RACコード-1",
    "有効成分-2",
    "RACコード-2",
    "有効成分-3",
    "RACコード-3",
    "有効成分-4",
    "RACコード-4",
    "有効成分-5",
    "RACコード-5",
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

    $value = mb_convert_kana($value, "KV", "UTF-8");
    $value = str_replace(["－", "ｰ", "―", "−", "‐", "–", "—"], "-", $value);
    $value = preg_replace('/[「」"\'`]/u', "", $value) ?? $value;
    $value = preg_replace('/\s+/u', '', $value) ?? $value;
    $value = trim($value);

    if ($value !== "" && preg_match('/^-+$/u', $value)) {
        return "-";
    }

    return $value;
}

function importRacToIngredients(PDO $pdo, string $csvPath, string $racGroup): array
{
    $handle = openCsv($csvPath, RAC_SOURCE_ENCODING);
    $counts = [
        "processed_rows" => 0,
        "processed_pairs" => 0,
        "updated" => 0,
        "unchanged" => 0,
        "empty_pairs" => 0,
        "not_found" => 0,
        "failed" => 0,
    ];
    $lineNumber = 1;

    $findStmt = $pdo->prepare(
        "SELECT id, rac_code, rac_group
         FROM ingredients
         WHERE name_normalized = :ingredient_name_normalized
         LIMIT 1"
    );
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
                    try {
                        $ingredientName = normalizeIngredientNameForRac($assoc["有効成分-{$i}"] ?? "");
                        $racCode = normalizeRacCode($assoc["RACコード-{$i}"] ?? "");

                        if ($ingredientName === "" || $racCode === "") {
                            $counts["empty_pairs"]++;
                            continue;
                        }

                        $counts["processed_pairs"]++;

                        $findStmt->execute([
                            ":ingredient_name_normalized" => $ingredientName,
                        ]);
                        $current = $findStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                        if ($current === null) {
                            $counts["not_found"]++;
                            continue;
                        }

                        $currentRacCode = normalizeRacCode((string)($current["rac_code"] ?? ""));
                        $currentRacGroup = strtoupper(trim((string)($current["rac_group"] ?? "")));
                        if ($currentRacCode === $racCode && $currentRacGroup === strtoupper($racGroup)) {
                            $counts["unchanged"]++;
                            continue;
                        }

                        $updateStmt->execute([
                            ":rac_code" => $racCode,
                            ":rac_group" => $racGroup,
                            ":ingredient_name_normalized" => $ingredientName,
                        ]);

                        if ($updateStmt->rowCount() > 0) {
                            $counts["updated"]++;
                        } else {
                            $counts["unchanged"]++;
                        }
                    } catch (Throwable $e) {
                        $counts["failed"]++;
                        writeError(
                            sprintf(
                                "[import_rac_to_ingredients] row=%d pair=%d error=%s",
                                $lineNumber,
                                $i,
                                $e->getMessage()
                            )
                        );
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
    writeInfo("unchanged: " . (string)$counts["unchanged"]);
    writeInfo("empty_pairs: " . (string)$counts["empty_pairs"]);
    writeInfo("not_found: " . (string)$counts["not_found"]);
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
