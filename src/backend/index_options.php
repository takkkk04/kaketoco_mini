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

//病害虫プルダウン(DB自動取得)
$stmt = $pdo->prepare(
    "SELECT DISTINCT t.name
    FROM pesticide_rules pr
    JOIN targets t ON pr.target_id = t.id
    WHERE (:category_any = '' OR pr.category = :category_filter)
    ORDER BY t.name ASC"
);
$stmt->execute([
    ":category_any" => $category,
    ":category_filter" => $category,
]);
$targetOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
