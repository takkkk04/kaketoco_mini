<?php
// =============================================
// 農薬詳細ページ: 取得処理
// =============================================
declare(strict_types=1);

require_once __DIR__ . "/db.php";

$id = (int)($_GET["id"] ?? 0);
$pesticide = null;
$ruleRows = [];
$ingredientRows = [];
$errorMessage = "";

if ($id <= 0) {
    http_response_code(400);
    $errorMessage = "不正なIDです。";
    return;
}

$pesticideStmt = $pdo->prepare(
    "SELECT id, name, registration_number
     FROM pesticides
     WHERE id = :id
     LIMIT 1"
);
$pesticideStmt->bindValue(":id", $id, PDO::PARAM_INT);
$pesticideStmt->execute();
$pesticide = $pesticideStmt->fetch(PDO::FETCH_ASSOC) ?: null;

if ($pesticide === null) {
    http_response_code(404);
    $errorMessage = "該当する農薬が見つかりません。";
    return;
}

$rulesStmt = $pdo->prepare(
    "SELECT
        c.name AS crop_name,
        t.name AS target_name,
        pr.magnification_text AS magnification_text,
        pr.timing_text AS timing_text,
        pr.times_text AS times_text,
        m.name AS method_name
     FROM pesticide_rules pr
     LEFT JOIN crops c
        ON c.id = pr.crop_id
     LEFT JOIN targets t
        ON t.id = pr.target_id
     LEFT JOIN methods m
        ON m.id = pr.method_id
     WHERE pr.pesticide_id = :pesticide_id
     ORDER BY c.name, t.name, m.name, pr.id"
);
$rulesStmt->bindValue(":pesticide_id", $id, PDO::PARAM_INT);
$rulesStmt->execute();
$ruleRows = $rulesStmt->fetchAll(PDO::FETCH_ASSOC);

$ingredientsStmt = $pdo->prepare(
    "SELECT
        i.id AS ingredient_id,
        i.name AS ingredient_name,
        pi.concentration_text AS concentration_text,
        GROUP_CONCAT(
            DISTINCT CASE
                WHEN irl.rac_code IS NULL OR irl.rac_code = '' OR irl.rac_code = '-' THEN NULL
                WHEN irl.rac_group IS NULL OR irl.rac_group = '' THEN irl.rac_code
                ELSE CONCAT(irl.rac_group, ':', irl.rac_code)
            END
            ORDER BY irl.sort_order ASC, irl.id ASC
            SEPARATOR ' / '
        ) AS rac_text
     FROM pesticide_ingredients pi
     JOIN ingredients i
        ON i.id = pi.ingredient_id
     LEFT JOIN ingredient_rac_labels irl
        ON irl.ingredient_id = i.id
     WHERE pi.pesticide_id = :pesticide_id
     GROUP BY pi.id, i.id, i.name, pi.concentration_text
     ORDER BY pi.id ASC"
);
$ingredientsStmt->bindValue(":pesticide_id", $id, PDO::PARAM_INT);
$ingredientsStmt->execute();
$ingredientRows = $ingredientsStmt->fetchAll(PDO::FETCH_ASSOC);
