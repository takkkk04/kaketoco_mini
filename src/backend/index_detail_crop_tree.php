<?php

// =============================================
// 詳細検索用 作物ツリー（v2）
// =============================================
$detailCropTree = [];
$detailCropTreeError = "";

try {
    $detailCropStmt = $pdo->prepare(
        "SELECT
            cm.id,
            cm.name,
            cm.large_category,
            cm.mid_category,
            cm.small_category,
            cm.entry_type,
            idx.match_count
        FROM crops_master_v2 cm
        INNER JOIN (
            SELECT
                selected_crop_id,
                COUNT(*) AS match_count
            FROM crop_search_index_v2
            GROUP BY selected_crop_id
        ) idx
            ON idx.selected_crop_id = cm.id
        WHERE cm.entry_type = 'crop'
        ORDER BY
            cm.large_category ASC,
            cm.mid_category ASC,
            cm.small_category ASC,
            cm.name ASC"
    );
    $detailCropStmt->execute();
    $detailCropRows = $detailCropStmt->fetchAll(PDO::FETCH_ASSOC);

    $detailCropTreeMap = [];

    $ensureBranch = static function (array &$container, string $key, string $label, int $level): void {
        if (isset($container[$key])) {
            return;
        }

        $container[$key] = [
            "id" => null,
            "name" => $label,
            "level" => $level,
            "entry_type" => "group",
            "is_selectable" => false,
            "children" => [],
        ];
    };

    foreach ($detailCropRows as $row) {
        $cropId = (int)($row["id"] ?? 0);
        $cropName = trim((string)($row["name"] ?? ""));
        $largeCategory = trim((string)($row["large_category"] ?? ""));
        $midCategory = trim((string)($row["mid_category"] ?? ""));
        $smallCategory = trim((string)($row["small_category"] ?? ""));

        if ($cropId <= 0 || $cropName === "" || $largeCategory === "") {
            continue;
        }

        $ensureBranch($detailCropTreeMap, $largeCategory, $largeCategory, 1);
        $currentChildren =& $detailCropTreeMap[$largeCategory]["children"];
        $currentLevel = 2;

        if ($midCategory === "" && $smallCategory !== "") {
            $midCategory = $smallCategory;
            $smallCategory = "";
        }

        if ($midCategory !== "") {
            $midKey = $largeCategory . "||" . $midCategory;
            $ensureBranch($currentChildren, $midKey, $midCategory, $currentLevel);
            $currentChildren =& $currentChildren[$midKey]["children"];
            $currentLevel++;
        }

        if ($smallCategory !== "") {
            $smallKey = $largeCategory . "||" . $midCategory . "||" . $smallCategory;
            $ensureBranch($currentChildren, $smallKey, $smallCategory, $currentLevel);
            $currentChildren =& $currentChildren[$smallKey]["children"];
            $currentLevel++;
        }

        $cropKey = "crop:" . (string)$cropId;
        $currentChildren[$cropKey] = [
            "id" => $cropId,
            "name" => $cropName,
            "level" => $currentLevel,
            "entry_type" => "crop",
            "is_selectable" => true,
            "children" => [],
            "match_count" => (int)($row["match_count"] ?? 0),
        ];
        unset($currentChildren);
    }

    $sortTree = static function (array $nodes) use (&$sortTree): array {
        uasort($nodes, static function (array $a, array $b): int {
            $levelCompare = ((int)($a["level"] ?? 0)) <=> ((int)($b["level"] ?? 0));
            if ($levelCompare !== 0) {
                return $levelCompare;
            }

            return strcmp((string)($a["name"] ?? ""), (string)($b["name"] ?? ""));
        });

        foreach ($nodes as $key => $node) {
            if (!empty($node["children"]) && is_array($node["children"])) {
                $nodes[$key]["children"] = $sortTree($node["children"]);
            }
        }

        return array_values($nodes);
    };

    $detailCropTree = $sortTree($detailCropTreeMap);
} catch (Throwable $e) {
    $detailCropTreeError = "作物ツリーを読み込めませんでした。";
}
