<?php
// =============================================
// RACコードCSVインポート
// =============================================
// RACコードはクロップライフジャパン（https://www.croplifejapan.org/activity/mechanism.html）
// RACコード検索表をダウンロード
// 殺虫剤、殺菌剤、除草剤、殺虫殺菌剤等カテゴリ全てを切り分けCSV（UTF-8）で保存
declare(strict_types=1);

require_once __DIR__ . "/../src/backend/db.php";
require_once __DIR__ . "/helpers_import.php";

assertCli();

const RAC_LABEL_DEFAULT_CSV_PATH = __DIR__ . "/../data/20260330_rac_insecticide_test20.csv";
const RAC_LABEL_DEFAULT_GROUP = "I";
const RAC_LABEL_SOURCE_ENCODING = "UTF-8";

const RAC_LABEL_REQUIRED_HEADERS = [
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

function resolveRacLabelCsvPath(array $argv): string
{
    if (!empty($argv[1])) {
        return resolveCsvPath($argv, 1);
    }

    if (!is_file(RAC_LABEL_DEFAULT_CSV_PATH)) {
        throw new InvalidArgumentException("CSVが見つかりません: " . RAC_LABEL_DEFAULT_CSV_PATH);
    }

    return RAC_LABEL_DEFAULT_CSV_PATH;
}

function resolveRacLabelDefaultGroup(array $argv): string
{
    $group = trim((string)($argv[2] ?? RAC_LABEL_DEFAULT_GROUP));
    return $group === "" ? RAC_LABEL_DEFAULT_GROUP : strtoupper($group);
}

function readRacLabelHeader($handle): array
{
    $header = readCsvRow($handle, RAC_LABEL_SOURCE_ENCODING);

    if ($header === false || $header === []) {
        throw new RuntimeException("CSVヘッダーを読み取れませんでした。");
    }

    $header = array_map(
        static fn($value): string => normalizeImportString((string)$value),
        $header
    );

    $missing = array_values(array_diff(RAC_LABEL_REQUIRED_HEADERS, $header));
    if ($missing !== []) {
        throw new RuntimeException("必須ヘッダーが不足しています: " . implode(", ", $missing));
    }

    return $header;
}

function combineRacLabelRow(array $header, array $row): array
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

function normalizeIngredientNameForRacLabel(?string $value): string
{
    $value = trim((string)$value);
    if ($value === "") {
        return "";
    }

    $value = mb_convert_kana($value, "KV", "UTF-8");
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
}

function normalizeRacCodeToken(string $value): string
{
    $value = trim($value);
    if ($value === "") {
        return "";
    }

    $value = mb_convert_kana($value, "KV", "UTF-8");
    $value = str_replace(["（", "）"], ["(", ")"], $value);
    $value = str_replace(["－", "ｰ", "―", "−", "‐", "–", "—"], "-", $value);
    $value = preg_replace('/[「」"\'`]/u', "", $value) ?? $value;
    $value = preg_replace('/\s+/u', '', $value) ?? $value;
    $value = trim($value);

    if ($value !== "" && preg_match('/^-+$/u', $value)) {
        return "-";
    }

    return $value;
}

/**
 * 例: 8F(I*),「-」(F*),「-」(H*)
 * => [
 *   ['rac_code' => '8F', 'rac_group' => 'I'],
 *   ['rac_code' => '-', 'rac_group' => 'F'],
 *   ['rac_code' => '-', 'rac_group' => 'H'],
 * ]
 */
function parseRacLabels(string $raw, string $defaultGroup): array
{
    $raw = trim($raw);
    if ($raw === "") {
        return [];
    }

    $raw = mb_convert_kana($raw, "KV", "UTF-8");
    $raw = str_replace(["（", "）"], ["(", ")"], $raw);

    $parts = preg_split('/[,，]/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $labels = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === "") {
            continue;
        }

        if (preg_match('/^\s*(.*?)\s*\(\s*([A-Za-z]+)\s*\*\s*\)\s*$/u', $part, $m) === 1) {
            $racCode = normalizeRacCodeToken((string)$m[1]);
            $racGroup = strtoupper(trim((string)$m[2]));
        } else {
            $racCode = normalizeRacCodeToken($part);
            $racGroup = strtoupper(trim($defaultGroup));
        }

        if ($racCode === "" || $racGroup === "") {
            continue;
        }

        $labels[] = [
            "rac_code" => $racCode,
            "rac_group" => $racGroup,
        ];
    }

    return $labels;
}

function importRacToIngredientRacLabels(PDO $pdo, string $csvPath, string $defaultGroup): array
{
    $handle = openCsv($csvPath, RAC_LABEL_SOURCE_ENCODING);
    $counts = [
        "processed_rows" => 0,
        "processed_pairs" => 0,
        "parsed_labels" => 0,
        "updated" => 0,
        "unchanged" => 0,
        "empty_pairs" => 0,
        "not_found" => 0,
        "existing_reused" => 0,
        "failed" => 0,
    ];
    $lineNumber = 1;

    $findIngredientStmt = $pdo->prepare(
        "SELECT id
         FROM ingredients
         WHERE name_normalized = :ingredient_name_normalized
         LIMIT 1"
    );
    $findLabelStmt = $pdo->prepare(
        "SELECT id, rac_group, rac_code
         FROM ingredient_rac_labels
         WHERE ingredient_id = :ingredient_id
           AND sort_order = :sort_order
         LIMIT 1"
    );
    $existsLabelStmt = $pdo->prepare(
        "SELECT 1
         FROM ingredient_rac_labels
         WHERE ingredient_id = :ingredient_id
         LIMIT 1"
    );
    $insertLabelStmt = $pdo->prepare(
        "INSERT INTO ingredient_rac_labels (
            ingredient_id,
            sort_order,
            rac_group,
            rac_code,
            created_at,
            updated_at
        ) VALUES (
            :ingredient_id,
            :sort_order,
            :rac_group,
            :rac_code,
            NOW(),
            NOW()
        )"
    );
    $updateLabelStmt = $pdo->prepare(
        "UPDATE ingredient_rac_labels
         SET rac_group = :rac_group,
             rac_code = :rac_code,
             updated_at = NOW()
         WHERE id = :id"
    );

    try {
        $header = readRacLabelHeader($handle);

        while (($row = readCsvRow($handle, RAC_LABEL_SOURCE_ENCODING)) !== false) {
            $lineNumber++;

            if ($row === []) {
                continue;
            }

            $counts["processed_rows"]++;

            try {
                $assoc = combineRacLabelRow($header, $row);

                for ($i = 1; $i <= 5; $i++) {
                    try {
                        $ingredientName = normalizeIngredientNameForRacLabel($assoc["有効成分-{$i}"] ?? "");
                        $racRaw = trim((string)($assoc["RACコード-{$i}"] ?? ""));

                        if ($ingredientName === "" || $racRaw === "") {
                            $counts["empty_pairs"]++;
                            continue;
                        }

                        $counts["processed_pairs"]++;

                        $findIngredientStmt->execute([
                            ":ingredient_name_normalized" => $ingredientName,
                        ]);
                        $ingredient = $findIngredientStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                        if ($ingredient === null) {
                            $counts["not_found"]++;
                            writeError("[not_found] row={$lineNumber} ingredient={$ingredientName} rac={$racRaw} group={$defaultGroup}");
                            continue;
                        }

                        $ingredientId = (int)$ingredient["id"];
                        $existsLabelStmt->execute([
                            ":ingredient_id" => $ingredientId,
                        ]);
                        $hasExistingLabel = $existsLabelStmt->fetchColumn() !== false;
                        if ($hasExistingLabel) {
                            $counts["existing_reused"]++;
                            continue;
                        }

                        $labels = parseRacLabels($racRaw, $defaultGroup);

                        if ($labels === []) {
                            $counts["empty_pairs"]++;
                            continue;
                        }

                        $counts["parsed_labels"] += count($labels);

                        foreach ($labels as $idx => $label) {
                            $sortOrder = $idx + 1;

                            $findLabelStmt->execute([
                                ":ingredient_id" => $ingredientId,
                                ":sort_order" => $sortOrder,
                            ]);
                            $current = $findLabelStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                            if ($current === null) {
                                $insertLabelStmt->execute([
                                    ":ingredient_id" => $ingredientId,
                                    ":sort_order" => $sortOrder,
                                    ":rac_group" => $label["rac_group"],
                                    ":rac_code" => $label["rac_code"],
                                ]);
                                $counts["updated"]++;
                                continue;
                            }

                            $currentGroup = strtoupper(trim((string)($current["rac_group"] ?? "")));
                            $currentCode = normalizeRacCodeToken((string)($current["rac_code"] ?? ""));
                            if ($currentGroup === $label["rac_group"] && $currentCode === $label["rac_code"]) {
                                $counts["unchanged"]++;
                                continue;
                            }

                            $updateLabelStmt->execute([
                                ":id" => (int)$current["id"],
                                ":rac_group" => $label["rac_group"],
                                ":rac_code" => $label["rac_code"],
                            ]);
                            $counts["updated"]++;
                        }
                    } catch (Throwable $e) {
                        $counts["failed"]++;
                        writeError(
                            sprintf(
                                "[import_rac_to_ingredient_rac_labels] row=%d pair=%d error=%s",
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
                        "[import_rac_to_ingredient_rac_labels] row=%d error=%s",
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

function printRacLabelImportSummary(array $counts): void
{
    writeInfo("processed_rows: " . (string)$counts["processed_rows"]);
    writeInfo("processed_pairs: " . (string)$counts["processed_pairs"]);
    writeInfo("parsed_labels: " . (string)$counts["parsed_labels"]);
    writeInfo("updated: " . (string)$counts["updated"]);
    writeInfo("unchanged: " . (string)$counts["unchanged"]);
    writeInfo("empty_pairs: " . (string)$counts["empty_pairs"]);
    writeInfo("not_found: " . (string)$counts["not_found"]);
    writeInfo("existing_reused: " . (string)$counts["existing_reused"]);
    writeInfo("failed: " . (string)$counts["failed"]);
}

try {
    $csvPath = resolveRacLabelCsvPath($argv);
    $defaultGroup = resolveRacLabelDefaultGroup($argv);
    writeInfo("Start import_rac_to_ingredient_rac_labels: {$csvPath} group={$defaultGroup}");
    $counts = importRacToIngredientRacLabels($pdo, $csvPath, $defaultGroup);
    printRacLabelImportSummary($counts);
    writeInfo("Finished import_rac_to_ingredient_rac_labels");
} catch (Throwable $e) {
    writeError("[import_rac_to_ingredient_rac_labels] " . $e->getMessage());
    exit(1);
}
