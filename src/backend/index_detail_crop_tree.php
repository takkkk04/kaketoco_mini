<?php

// =============================================
// 詳細検索用 作物ツリー（v2）
// =============================================
$detailCropTree = [];
$detailCropTreeError = "";

try {
    $largeCategoryOrder = [
        "野菜類" => 1,
        "果樹類" => 2,
        "穀類" => 3,
        "花き類・観葉植物" => 4,
        "樹木類" => 5,
        "芝" => 6,
        "飼料作物" => 7,
        "薬用作物" => 8,
        "きのこ類" => 9,
        "その他の食用作物" => 10,
        "その他の非食用作物" => 11,
        "適用地帯等" => 12,
    ];

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
        WHERE cm.entry_type IN ('crop', 'group')
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
            "is_branch_selectable" => false,
            "match_count" => 0,
            "children" => [],
        ];
    };

    foreach ($detailCropRows as $row) {
        $cropId = (int)($row["id"] ?? 0);
        $cropName = trim((string)($row["name"] ?? ""));
        $largeCategory = trim((string)($row["large_category"] ?? ""));
        $midCategory = trim((string)($row["mid_category"] ?? ""));
        $smallCategory = trim((string)($row["small_category"] ?? ""));
        $entryType = trim((string)($row["entry_type"] ?? ""));
        $matchCount = (int)($row["match_count"] ?? 0);

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

        if ($entryType === "group") {
            if ($smallCategory !== "" && $cropName === $smallCategory) {
                $midKey = $largeCategory . "||" . $midCategory;
                if ($midCategory !== "") {
                    $ensureBranch($currentChildren, $midKey, $midCategory, $currentLevel);
                    $currentChildren =& $currentChildren[$midKey]["children"];
                    $currentLevel++;
                }

                $smallKey = $largeCategory . "||" . $midCategory . "||" . $smallCategory;
                $ensureBranch($currentChildren, $smallKey, $smallCategory, $currentLevel);
                $currentChildren[$smallKey]["id"] = $cropId;
                $currentChildren[$smallKey]["is_selectable"] = true;
                $currentChildren[$smallKey]["is_branch_selectable"] = true;
                $currentChildren[$smallKey]["match_count"] = $matchCount;
                unset($currentChildren);
                continue;
            }

            if ($midCategory !== "" && $cropName === $midCategory) {
                $midKey = $largeCategory . "||" . $midCategory;
                $ensureBranch($currentChildren, $midKey, $midCategory, $currentLevel);
                $currentChildren[$midKey]["id"] = $cropId;
                $currentChildren[$midKey]["is_selectable"] = true;
                $currentChildren[$midKey]["is_branch_selectable"] = true;
                $currentChildren[$midKey]["match_count"] = $matchCount;
                unset($currentChildren);
                continue;
            }

            if ($cropName === $largeCategory) {
                $detailCropTreeMap[$largeCategory]["id"] = $cropId;
                $detailCropTreeMap[$largeCategory]["is_selectable"] = true;
                $detailCropTreeMap[$largeCategory]["is_branch_selectable"] = true;
                $detailCropTreeMap[$largeCategory]["match_count"] = $matchCount;
            }
            unset($currentChildren);
            continue;
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
            "is_branch_selectable" => false,
            "children" => [],
            "match_count" => $matchCount,
        ];
        unset($currentChildren);
    }

    $sortTree = static function (array $nodes) use (&$sortTree, $largeCategoryOrder): array {
        uasort($nodes, static function (array $a, array $b) use ($largeCategoryOrder): int {
            $levelA = (int)($a["level"] ?? 0);
            $levelB = (int)($b["level"] ?? 0);

            if ($levelA === 1 && $levelB === 1) {
                $orderA = $largeCategoryOrder[(string)($a["name"] ?? "")] ?? 9999;
                $orderB = $largeCategoryOrder[(string)($b["name"] ?? "")] ?? 9999;
                if ($orderA !== $orderB) {
                    return $orderA <=> $orderB;
                }
            }

            $levelCompare = $levelA <=> $levelB;
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
