<?php

declare(strict_types=1);

/**
 * 農水省CSVインポート共通ヘルパー
 *
 * 方針:
 * - import_basic.php / import_rules.php から読み込む
 * - ここでは共通I/O、文字列整形、辞書参照、rule_hash生成を担当する
 * - 詳細ロジックは後続タスクで実装する
 */

function assertCli(): void
{
    if (PHP_SAPI !== "cli") {
        fwrite(STDERR, "CLI専用スクリプトです。\n");
        exit(1);
    }
}

function resolveCsvPath(array $argv, int $index = 1): string
{
    $path = $argv[$index] ?? "";

    if ($path === "") {
        throw new InvalidArgumentException("CSVパスを指定してください。");
    }

    if (!is_file($path)) {
        throw new InvalidArgumentException("CSVが見つかりません: {$path}");
    }

    return $path;
}

function openCsv(string $path, string $sourceEncoding = "UTF-8")
{
    $handle = fopen($path, "r");

    if ($handle === false) {
        throw new RuntimeException("CSVを開けませんでした: {$path}");
    }

    return $handle;
}

function readCsvRow($handle, string $sourceEncoding = "UTF-8"): array|false
{
    $line = fgets($handle);

    if ($line === false) {
        return false;
    }

    $line = mb_convert_encoding($line, "UTF-8", $sourceEncoding);
    $row = str_getcsv($line);

    if ($row === [null]) {
        return [];
    }

    return $row;
}

function normalizeImportString(?string $value): string
{
    $value = trim((string)$value);

    if ($value === "") {
        return "";
    }

    $value = mb_convert_kana($value, "KVas", "UTF-8");
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

function normalizeNullableString(?string $value): ?string
{
    $value = normalizeImportString($value);
    return $value === "" ? null : $value;
}

function findOrCreateDictionaryId(
    PDO $pdo,
    string $table,
    string $name,
    array $extraUniqueColumns = []
): ?int {
    $name = normalizeImportString($name);

    if ($name === "") {
        return null;
    }

    // TODO:
    // - テーブルごとの一意制約に合わせて SQL を実装する
    // - INSERT IGNORE または ON DUPLICATE KEY UPDATE 方針をここで統一する
    // - $extraUniqueColumns が必要な辞書テーブルの扱いを決める
    return null;
}

function generateRuleHash(array $parts): string
{
    $normalized = [];

    foreach ($parts as $part) {
        $normalized[] = normalizeImportString((string)$part);
    }

    // TODO:
    // - rule_hash の対象列順を import_rules.php 側で確定させる
    // - 農水省原文を保持する列と、重複判定用の正規化列を切り分ける
    return md5(implode("|", $normalized));
}

function writeInfo(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function writeError(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
}

function parseJapaneseDate(?string $value): ?string
{
    $value = normalizeImportString($value);

    if ($value === "") {
        return null;
    }

    $dt = DateTimeImmutable::createFromFormat("Y/m/d", $value);

    if ($dt === false) {
        return null;
    }

    return $dt->format("Y-m-d");
}
