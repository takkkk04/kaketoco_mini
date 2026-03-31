<?php
// =============================================
// 農薬詳細ページ: 取得処理
// =============================================
declare(strict_types=1);

require_once __DIR__ . "/db.php";

$id = (int)($_GET["id"] ?? 0);
$pesticide = null;
$ruleRows = [];
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
