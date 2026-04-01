<?php

// =============================================
// カード内作物、病害虫一覧取得
// =============================================
$cropListStmt = $pdo->prepare(
    "SELECT DISTINCT c.name
    FROM pesticide_rules pr
    JOIN pesticides p ON p.id = pr.pesticide_id
    JOIN crops c ON c.id = pr.crop_id
    WHERE p.registration_number = :reg
      AND pr.category = :category
    ORDER BY c.name ASC"
);

$targetListStmt = $pdo->prepare(
    "SELECT DISTINCT t.name
    FROM pesticide_rules pr
    JOIN pesticides p ON p.id = pr.pesticide_id
    JOIN targets t ON t.id = pr.target_id
    WHERE p.registration_number = :reg
      AND pr.category = :category
    ORDER BY t.name ASC"
);

// =============================================
// カード内RAC一覧（新DB構造）
// =============================================
$racMap = [];

if (!empty($filtered)) {
    $pesticideIds = array_values(array_unique(array_map(
        static fn($row): int => (int)($row["pesticide_id"] ?? 0),
        $filtered
    )));
    $pesticideIds = array_values(array_filter($pesticideIds, static fn($id): bool => $id > 0));

    if ($pesticideIds !== []) {
        $placeholders = [];
        $params = [];
        foreach ($pesticideIds as $i => $id) {
            $key = ":pid{$i}";
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $racSql = "
            SELECT
                pi.pesticide_id,
                irl.rac_group,
                irl.rac_code
            FROM pesticide_ingredients pi
            JOIN ingredient_rac_labels irl
                ON irl.ingredient_id = pi.ingredient_id
            WHERE pi.pesticide_id IN (" . implode(",", $placeholders) . ")
            ORDER BY pi.pesticide_id ASC, irl.sort_order ASC, irl.id ASC
        ";

        $racStmt = $pdo->prepare($racSql);
        foreach ($params as $key => $value) {
            $racStmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $racStmt->execute();
        $racRows = $racStmt->fetchAll(PDO::FETCH_ASSOC);

        $racSetMap = [];
        foreach ($racRows as $row) {
            $pesticideId = (int)($row["pesticide_id"] ?? 0);
            $racCode = trim((string)($row["rac_code"] ?? ""));
            $racCode = str_replace(["－", "ｰ", "―", "−", "‐", "–", "—"], "-", $racCode);

            if ($pesticideId <= 0 || $racCode === "" || $racCode === "-") {
                continue;
            }

            $racGroup = trim((string)($row["rac_group"] ?? ""));
            $label = ($racGroup === "") ? $racCode : ($racGroup . ":" . $racCode);
            $racSetMap[$pesticideId][$label] = true;
        }

        foreach ($racSetMap as $pesticideId => $labels) {
            $racMap[(int)$pesticideId] = implode(" / ", array_keys($labels));
        }
    }
}
