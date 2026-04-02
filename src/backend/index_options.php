<?php

// =============================================
// ザックリ検索,作物プルダウン
// =============================================
//作物プルダウン（DB自動取得ver）
// $stmt = $pdo->prepare(
//     "SELECT DISTINCT crop
//     FROM pesticides_rules
//     WHERE category = :category AND crop <> ''
//     ORDER BY crop ASC
//     ");
// $stmt->execute([":category" => $category]);
// $cropOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

//ザックリ作物決め打ちプルダウン
$quickCropLabels = [
    "アスパラガス",
    "いちご",
    "えだまめ",
    "おうとう",
    "オクラ",
    "かき",
    "かぶ",
    "かぼちゃ",
    "カリフラワー",
    "かんきつ",
    "かんしょ",
    "きく",
    "キャベツ",
    "きゅうり",
    "ごぼう",
    "こまつな",
    "さといも",
    "さやいんげん",
    "さやえんどう",
    "ししとう",
    "しそ",
    "しゅんぎく",
    "しょうが",
    "すいか",
    "ズッキーニ",
    "セルリー",
    "だいこん",
    "だいず",
    "たまねぎ",
    "てんさい",
    "とうがらし類",
    "トマト",
    "なし",
    "なす",
    "にがうり",
    "にら",
    "にんじん",
    "にんにく",
    "ねぎ",
    "はくさい",
    "ばれいしょ",
    "ピーマン",
    "ぶどう",
    "ブロッコリー",
    "ほうれんそう",
    "ミニトマト",
    "メロン",
    "もも",
    "やまのいも",
    "りんご",
    "レタス",
    "未成熟とうもろこし",
    "茶",
    "野菜類",
    "非結球あぶらな科葉菜類",
    "非結球レタス",
];

$cropOptions = [];
foreach ($quickCropLabels as $label) {
    $dbValue = mb_convert_kana($label, "k", "UTF-8"); //カタカナ半角化
    $cropOptions[$label] = $dbValue;
}

// 害虫・病害・雑草プルダウン(DB自動取得)
$targetTypeStmt = $pdo->prepare(
    "SELECT DISTINCT t.name
    FROM pesticide_rules pr
    JOIN targets t ON pr.target_id = t.id
    LEFT JOIN crops c ON pr.crop_id = c.id
    WHERE t.target_type = :target_type
      AND t.name <> '-'
      AND (:category_any = '' OR pr.category = :category_filter)
      AND (:crop_any = '' OR c.name = :crop_filter)
    ORDER BY t.name ASC"
);

$loadTargetOptions = static function (PDOStatement $stmt, string $targetType, string $category, string $crop): array {
    $stmt->execute([
        ":target_type" => $targetType,
        ":category_any" => $category,
        ":category_filter" => $category,
        ":crop_any" => $crop,
        ":crop_filter" => $crop,
    ]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
};

$insectOptions = $loadTargetOptions($targetTypeStmt, "害虫", $category, $crop);
$diseaseOptions = $loadTargetOptions($targetTypeStmt, "病害", $category, $crop);
$weedOptions = $loadTargetOptions($targetTypeStmt, "雑草", $category, $crop);
