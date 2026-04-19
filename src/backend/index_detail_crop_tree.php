<?php

// =============================================
// 詳細検索用 作物ツリー
// =============================================
$detailCropTree = [];
$detailCropTreeError = "";

try {
    $detailCropStmt = $pdo->prepare(
        "SELECT
            new_id,
            original_id,
            name,
            level,
            parent_id,
            category_name,
            mid_category_name,
            entry_type,
            display
        FROM crops_master
        ORDER BY
            level ASC,
            category_name ASC,
            mid_category_name ASC,
            name ASC"
    );
    $detailCropStmt->execute();
    $detailCropRows = $detailCropStmt->fetchAll(PDO::FETCH_ASSOC);

    $nodeMap = [];
    foreach ($detailCropRows as $row) {
        $nodeId = trim((string)($row["new_id"] ?? ""));
        if ($nodeId === "") {
            continue;
        }

        $nodeMap[$nodeId] = [
            "id" => $nodeId,
            "parent_id" => trim((string)($row["parent_id"] ?? "")),
            "name" => trim((string)($row["name"] ?? "")),
            "level" => (int)($row["level"] ?? 0),
            "entry_type" => trim((string)($row["entry_type"] ?? "")),
            "category_name" => trim((string)($row["category_name"] ?? "")),
            "mid_category_name" => trim((string)($row["mid_category_name"] ?? "")),
            "display" => (int)($row["display"] ?? 0),
            "children" => [],
        ];
    }

    foreach ($nodeMap as $nodeId => $node) {
        $parentId = $node["parent_id"];
        if ($parentId !== "" && isset($nodeMap[$parentId])) {
            $nodeMap[$parentId]["children"][] = $nodeId;
        }
    }

    $sortNodeIds = static function (array &$ids) use (&$nodeMap): void {
        usort($ids, static function (string $a, string $b) use (&$nodeMap): int {
            $nodeA = $nodeMap[$a] ?? null;
            $nodeB = $nodeMap[$b] ?? null;
            if ($nodeA === null || $nodeB === null) {
                return strcmp($a, $b);
            }

            $levelCompare = ((int)$nodeA["level"]) <=> ((int)$nodeB["level"]);
            if ($levelCompare !== 0) {
                return $levelCompare;
            }

            $categoryCompare = strcmp((string)$nodeA["category_name"], (string)$nodeB["category_name"]);
            if ($categoryCompare !== 0) {
                return $categoryCompare;
            }

            $midCompare = strcmp((string)$nodeA["mid_category_name"], (string)$nodeB["mid_category_name"]);
            if ($midCompare !== 0) {
                return $midCompare;
            }

            return strcmp((string)$nodeA["name"], (string)$nodeB["name"]);
        });
    };

    foreach (array_keys($nodeMap) as $nodeId) {
        $sortNodeIds($nodeMap[$nodeId]["children"]);
    }

    $categoryEntryTypes = [
        "large_category" => true,
        "mid_category" => true,
    ];

    $includeMap = [];
    $markWithAncestors = static function (string $nodeId) use (&$includeMap, &$nodeMap, &$markWithAncestors): void {
        if (isset($includeMap[$nodeId]) || !isset($nodeMap[$nodeId])) {
            return;
        }

        $includeMap[$nodeId] = true;
        $parentId = (string)$nodeMap[$nodeId]["parent_id"];
        if ($parentId !== "") {
            $markWithAncestors($parentId);
        }
    };

    foreach ($nodeMap as $nodeId => $node) {
        $entryType = (string)$node["entry_type"];
        $isCategory = isset($categoryEntryTypes[$entryType]);
        $isVisibleTerminal = !$isCategory && (int)$node["display"] === 1;

        if ($isVisibleTerminal) {
            $markWithAncestors($nodeId);
        }
    }

    $buildTreeNode = static function (string $nodeId) use (&$nodeMap, &$includeMap, &$buildTreeNode, $categoryEntryTypes): ?array {
        if (!isset($includeMap[$nodeId], $nodeMap[$nodeId])) {
            return null;
        }

        $node = $nodeMap[$nodeId];
        $children = [];
        foreach ($node["children"] as $childId) {
            $childNode = $buildTreeNode($childId);
            if ($childNode !== null) {
                $children[] = $childNode;
            }
        }

        $entryType = (string)$node["entry_type"];
        $isCategory = isset($categoryEntryTypes[$entryType]);
        $isSelectable = !$isCategory && $children === [];

        return [
            "id" => (string)$node["id"],
            "name" => (string)$node["name"],
            "level" => (int)$node["level"],
            "entry_type" => $entryType,
            "is_selectable" => $isSelectable,
            "children" => $children,
        ];
    };

    $rootIds = [];
    foreach ($includeMap as $nodeId => $_) {
        $parentId = (string)($nodeMap[$nodeId]["parent_id"] ?? "");
        if ($parentId === "" || !isset($includeMap[$parentId])) {
            $rootIds[] = $nodeId;
        }
    }

    $sortNodeIds($rootIds);
    foreach ($rootIds as $rootId) {
        $treeNode = $buildTreeNode($rootId);
        if ($treeNode !== null) {
            $detailCropTree[] = $treeNode;
        }
    }
} catch (Throwable $e) {
    $detailCropTreeError = "作物ツリーを読み込めませんでした。";
}
