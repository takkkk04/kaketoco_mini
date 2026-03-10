<?php

declare(strict_types=1);

require_once __DIR__ . "/../src/backend/db.php";
require_once __DIR__ . "/helpers_import.php";

assertCli();

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
 * - crops / targets は農水省原文辞書として保持する
 * - pesticide_rules は rule_hash による重複防止を前提にする
 * - 詳細マッピングは後続タスクで実装する
 */

function importRulesCsv(PDO $pdo, string $csvPath): void
{
    $handle = openCsv($csvPath);

    try {
        // TODO:
        // - ヘッダー定義を読み、列名ベースでマッピングできる形にする
        // - 1行ごとに crops / targets / methods を解決する
        // - rule_hash の元になる列セットを確定する
        // - pesticide_rules を UPSERT する
        // - rule_ingredient_limits を投入する
        // - スキップ件数、更新件数、エラー件数の集計を追加する
        while (($row = readCsvRow($handle)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            // TODO:
            // - 作物、病害虫、使用方法の辞書IDを解決する
            // - ルール本体の正規化データを組み立てる
            // - generateRuleHash() 用の列順を固定する
            // - pesticide_rules を重複排除付きで投入する
            // - 成分ごとの制限がある場合は rule_ingredient_limits を投入する
        }
    } finally {
        fclose($handle);
    }
}

try {
    $csvPath = resolveCsvPath($argv, 1);
    writeInfo("Start import_rules: {$csvPath}");
    importRulesCsv($pdo, $csvPath);
    writeInfo("Finished import_rules");
} catch (Throwable $e) {
    writeError("[import_rules] " . $e->getMessage());
    exit(1);
}
