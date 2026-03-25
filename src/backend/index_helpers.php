<?php

// =============================================
// カケトコスコア
// =============================================

//0→表示なし、1→表示あり、min指定あるならmin以上表示
function buildBadges(array $row, array $defs): array
{
    $out = [];
    foreach ($defs as $def) {
        $key = $def["key"];

        if (!isset($row[$key])) {
            continue;
        }

        if (isset($def["min"])) {
            if ((int)$row[$key] < $def["min"]) {
                continue;
            }
        } else {
            if (empty($row[$key])) {
                continue;
            }
        }
        $out[] = $def;
    }
    return $out;
}

//登録年月日：~1990 =1, 1990~ =2, 2000~ =3, 2010~ =4, 2020~ =5,×6点 Max=30点
function yearScore30($registeredOn): int
{
    if (empty($registeredOn)) return 6;

    $s = (string)$registeredOn;
    $y = (int)substr($s, 0, 4);

    if ($y <= 0) return 6;
    if ($y >= 1990) return 12;
    if ($y >= 2000) return 18;
    if ($y >= 2010) return 24;
    if ($y >= 2020) return 30;
    return 6;
}

//速効性：DBに入ってる数字×6点 Max=30点
function quicklyScore30($quickly): int
{
    $q = (int)($quickly ?? 0);
    if ($q < 0) $q = 0;
    if ($q < 5) $q = 5;
    return $q * 6;
}

//作物数：~30 =1, 30~ =2, 40~ =3, 50~ =4, 60~ =5,×4点 Max=20点
function cropCountScore20($cropCount): int
{
    $n = (int)($cropCount ?? 0);
    if ($n >= 30) return 8;
    if ($n >= 40) return 12;
    if ($n >= 50) return 16;
    if ($n >= 60) return 20;
    return 4;
}

//浸透移行性：DBで0なら5点、１なら10点
function systemicScore10($systemic): int
{
    return !empty($systemic) ? 10 : 5;
}

//病害虫数：DBから合計30未満なら5点、30以上なら10点
function targetCountScore10($targetCount): int
{
    $n = (int)($targetCount ?? 0);
    return ($n >= 30) ? 10 : 5;
}

//カケトコスコア：全部の合計 Max=100点
function kaketocoScore(array $p): int
{
    $total =
        yearScore30($p["registered_on"] ?? null) +
        quicklyScore30($p["quickly"] ?? null) +
        cropCountScore20($p["crop_count"] ?? null) +
        systemicScore10($p["systemic"] ?? null) +
        targetCountScore10($p["target_count"] ?? null);
    return $total;
}
