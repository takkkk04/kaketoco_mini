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
