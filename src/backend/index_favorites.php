<?php

// =============================================
// カード内お気に入り情報取得
// =============================================
$favMap = [];

if ($userId !== null && $count > 0) {
    $regs = [];
    foreach ($filtered as $row) {
        $r = (int)($row["registration_number"] ?? 0);
        if ($r > 0) $regs[] = $r;
    }

    $regs = array_values(array_unique($regs));

    if (!empty($regs)) {
        $placeholders = [];
        $params = [":uid" => $userId];
        foreach ($regs as $i => $r) {
            $k = ":r{$i}";
            $placeholders[] = $k;
            $params[$k] = $r;
        }

        $sqlFav =
            "SELECT registration_number
            FROM favorites
            WHERE user_id = :uid
            AND registration_number
            IN (" . implode(",", $placeholders) . ")";

        $stFav = $pdo->prepare($sqlFav);
        $stFav->execute($params);
        $favRegs = $stFav->fetchAll(PDO::FETCH_COLUMN);

        foreach ($favRegs as $fr) {
            $favMap[(int)$fr] = true;
        }
    }
}
