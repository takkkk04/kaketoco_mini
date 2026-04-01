<?php
// =============================================
// 適用表外項目CSVインポート
// =============================================
// 登録番号、農薬の名称、速効性、浸透移行性、深達性、毒性、shopify_idでCSV作成
// ファイル名 YYYYMMDD_pesticide_master_extra.csv で作成
// SSHログイン後コード実行
// php scripts/import_pesticide_extra_attributes.php data/20260331_pesticide_master_extra.csv

declare(strict_types=1);

require_once __DIR__ . "/../src/backend/db.php";
require_once __DIR__ . "/helpers_import.php";

assertCli();

const EXTRA_ATTR_DEFAULT_CSV_PATH = __DIR__ . "/../data/20260331_pesticide_master_extra.csv";
const EXTRA_ATTR_SOURCE_ENCODING = "UTF-8";

const EXTRA_ATTR_REQUIRED_HEADERS = [
    "登録番号",
    "農薬の名称",
    "速効性",
    "浸透移行性",
    "浸達性",
    "毒性",
    "shopify_id",
];

function resolveExtraAttrCsvPath(array $argv): string
{
    if (!empty($argv[1])) {
        return resolveCsvPath($argv, 1);
    }

    if (!is_file(EXTRA_ATTR_DEFAULT_CSV_PATH)) {
        throw new InvalidArgumentException("CSVが見つかりません: " . EXTRA_ATTR_DEFAULT_CSV_PATH);
    }

    return EXTRA_ATTR_DEFAULT_CSV_PATH;
}

function readExtraAttrHeader($handle): array
{
    $header = readCsvRow($handle, EXTRA_ATTR_SOURCE_ENCODING);

    if ($header === false || $header === []) {
        throw new RuntimeException("CSVヘッダーを読み取れませんでした。");
    }

    $header = array_map(
        static fn($value): string => normalizeImportString((string)$value),
        $header
    );

    $missing = array_values(array_diff(EXTRA_ATTR_REQUIRED_HEADERS, $header));
    if ($missing !== []) {
        throw new RuntimeException("必須ヘッダーが不足しています: " . implode(", ", $missing));
    }

    return $header;
}

function combineExtraAttrRow(array $header, array $row): array
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

function normalizeNullableIntField(?string $value): ?int
{
    $value = trim((string)$value);
    if ($value === "") {
        return null;
    }

    return preg_match('/^-?\d+$/', $value) === 1 ? (int)$value : null;
}

function normalizeNullableTextField(?string $value): ?string
{
    $value = trim((string)$value);
    return $value === "" ? null : $value;
}

function importPesticideExtraAttributes(PDO $pdo, string $csvPath): array
{
    $handle = openCsv($csvPath, EXTRA_ATTR_SOURCE_ENCODING);
    $counts = [
        "processed_rows" => 0,
        "inserted" => 0,
        "updated" => 0,
        "unchanged" => 0,
        "not_found" => 0,
        "failed" => 0,
    ];
    $lineNumber = 1;

    $findPesticideStmt = $pdo->prepare(
        "SELECT 1
         FROM pesticides
         WHERE registration_number = :registration_number
         LIMIT 1"
    );
    $findExtraStmt = $pdo->prepare(
        "SELECT quickly, systemic, translaminar, toxicity, shopify_id
         FROM pesticide_extra_attributes
         WHERE registration_number = :registration_number
         LIMIT 1"
    );
    $insertExtraStmt = $pdo->prepare(
        "INSERT INTO pesticide_extra_attributes (
            registration_number,
            quickly,
            systemic,
            translaminar,
            toxicity,
            shopify_id,
            created_at,
            updated_at
        ) VALUES (
            :registration_number,
            :quickly,
            :systemic,
            :translaminar,
            :toxicity,
            :shopify_id,
            NOW(),
            NOW()
        )"
    );
    $updateExtraStmt = $pdo->prepare(
        "UPDATE pesticide_extra_attributes
         SET quickly = :quickly,
             systemic = :systemic,
             translaminar = :translaminar,
             toxicity = :toxicity,
             shopify_id = :shopify_id,
             updated_at = NOW()
         WHERE registration_number = :registration_number"
    );

    try {
        $header = readExtraAttrHeader($handle);

        while (($row = readCsvRow($handle, EXTRA_ATTR_SOURCE_ENCODING)) !== false) {
            $lineNumber++;

            if ($row === []) {
                continue;
            }

            $counts["processed_rows"]++;

            try {
                $assoc = combineExtraAttrRow($header, $row);

                $registrationNumber = normalizeNullableIntField($assoc["登録番号"] ?? null);
                if ($registrationNumber === null || $registrationNumber <= 0) {
                    throw new RuntimeException("登録番号が不正です。");
                }

                $payload = [
                    ":registration_number" => $registrationNumber,
                    ":quickly" => normalizeNullableIntField($assoc["速効性"] ?? null),
                    ":systemic" => normalizeNullableIntField($assoc["浸透移行性"] ?? null),
                    ":translaminar" => normalizeNullableIntField($assoc["浸達性"] ?? null),
                    ":toxicity" => normalizeNullableTextField($assoc["毒性"] ?? null),
                    ":shopify_id" => normalizeNullableTextField($assoc["shopify_id"] ?? null),
                ];

                $findPesticideStmt->execute([
                    ":registration_number" => $registrationNumber,
                ]);
                $existsPesticide = $findPesticideStmt->fetchColumn() !== false;

                if (!$existsPesticide) {
                    $counts["not_found"]++;
                    writeError(
                        sprintf(
                            "[not_found] row=%d registration_number=%d name=%s",
                            $lineNumber,
                            $registrationNumber,
                            (string)($assoc["農薬の名称"] ?? "")
                        )
                    );
                    continue;
                }

                $findExtraStmt->execute([
                    ":registration_number" => $registrationNumber,
                ]);
                $current = $findExtraStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($current === null) {
                    $insertExtraStmt->execute($payload);
                    $counts["inserted"]++;
                    continue;
                }

                $same =
                    (string)($current["quickly"] ?? "") === (string)($payload[":quickly"] ?? "") &&
                    (string)($current["systemic"] ?? "") === (string)($payload[":systemic"] ?? "") &&
                    (string)($current["translaminar"] ?? "") === (string)($payload[":translaminar"] ?? "") &&
                    (string)($current["toxicity"] ?? "") === (string)($payload[":toxicity"] ?? "") &&
                    (string)($current["shopify_id"] ?? "") === (string)($payload[":shopify_id"] ?? "");

                if ($same) {
                    $counts["unchanged"]++;
                    continue;
                }

                $updateExtraStmt->execute($payload);
                $counts["updated"]++;
            } catch (Throwable $e) {
                $counts["failed"]++;
                writeError(
                    sprintf(
                        "[import_pesticide_extra_attributes] row=%d error=%s",
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

function printExtraAttrImportSummary(array $counts): void
{
    writeInfo("processed_rows: " . (string)$counts["processed_rows"]);
    writeInfo("inserted: " . (string)$counts["inserted"]);
    writeInfo("updated: " . (string)$counts["updated"]);
    writeInfo("unchanged: " . (string)$counts["unchanged"]);
    writeInfo("not_found: " . (string)$counts["not_found"]);
    writeInfo("failed: " . (string)$counts["failed"]);
}

try {
    $csvPath = resolveExtraAttrCsvPath($argv);
    writeInfo("Start import_pesticide_extra_attributes: {$csvPath}");
    $counts = importPesticideExtraAttributes($pdo, $csvPath);
    printExtraAttrImportSummary($counts);
    writeInfo("Finished import_pesticide_extra_attributes");
} catch (Throwable $e) {
    writeError("[import_pesticide_extra_attributes] " . $e->getMessage());
    exit(1);
}
