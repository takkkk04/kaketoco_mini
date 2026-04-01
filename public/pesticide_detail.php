<?php
// =============================================
// 詳細ページ
// =============================================
declare(strict_types=1);

require_once __DIR__ . "/../src/backend/index_bootstrap.php";
require_once __DIR__ . "/../src/backend/pesticide_detail_fetch.php";

$displayValue = static function ($value): string {
    $text = trim((string)($value ?? ""));
    return $text === "" ? "-" : $text;
};

$placeholderImage = "./image/coming_soon.jpeg";
$shopifyId = trim((string)($pesticide["shopify_id"] ?? ""));
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>農薬詳細</title>
    <link rel="stylesheet" href="./css/reset.css">
    <link rel="stylesheet" href="./css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="./css/pesticide_detail.css">
</head>

<body>
    <?php require __DIR__ . "/parts/header.php"; ?>
    <main class="app_main detail_main">
        <section class="detail_page">
            <header class="detail_header">
                <h1 class="detail_title">農薬詳細</h1>
                <a class="detail_back" href="./index.php">一覧へ戻る</a>
            </header>

            <?php if ($pesticide === null): ?>
                <section class="detail_card">
                    <p class="detail_error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8"); ?></p>
                </section>
            <?php else: ?>
                <?php
                $ingredientItems = [];
                foreach ($ingredientRows as $ingredient) {
                    $ingredientName = trim((string)($ingredient["ingredient_name"] ?? ""));
                    $concentration = trim((string)($ingredient["concentration_text"] ?? ""));
                    $racText = trim((string)($ingredient["rac_text"] ?? ""));
                    if ($ingredientName === "") {
                        continue;
                    }

                    $ingredientItems[] = [
                        "name" => $ingredientName,
                        "concentration" => $concentration,
                        "rac" => $racText,
                    ];
                }
                ?>

                <section class="detail_top">
                    <div class="detail_image detail_card">
                        <h2 class="detail_product_name"><?php echo htmlspecialchars((string)$pesticide["name"], ENT_QUOTES, "UTF-8"); ?></h2>
                        <img
                            id="detail_product_image"
                            src="<?php echo htmlspecialchars($placeholderImage, ENT_QUOTES, "UTF-8"); ?>"
                            data-shopify-id="<?php echo htmlspecialchars($shopifyId, ENT_QUOTES, "UTF-8"); ?>"
                            alt="商品画像"
                            onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($placeholderImage, ENT_QUOTES, "UTF-8"); ?>';">
                    </div>

                    <div class="detail_summary detail_card">
                        <h2 class="detail_section_title">基本情報</h2>
                        <div class="summary_list">
                            <div class="summary_row">
                                <div class="summary_label">登録年月日</div>
                                <div class="summary_value"><?php echo htmlspecialchars($displayValue($pesticide["registered_on"] ?? null), ENT_QUOTES, "UTF-8"); ?></div>
                            </div>
                            <div class="summary_row">
                                <div class="summary_label">有効成分</div>
                                <div class="summary_value">
                                    <?php if ($ingredientItems === []): ?>
                                        成分情報はありません。
                                    <?php else: ?>
                                        <?php foreach ($ingredientItems as $item): ?>
                                            <div>
                                                <?php echo htmlspecialchars((string)$item["name"], ENT_QUOTES, "UTF-8"); ?>
                                                <?php if ((string)$item["concentration"] !== ""): ?>
                                                    （<?php echo htmlspecialchars((string)$item["concentration"], ENT_QUOTES, "UTF-8"); ?>）
                                                <?php endif; ?>
                                                <?php if ((string)$item["rac"] !== ""): ?>
                                                    <?php
                                                    $racParts = array_filter(array_map("trim", explode(" / ", (string)$item["rac"])));
                                                    foreach ($racParts as $racPart):
                                                        $pair = explode(":", $racPart, 2);
                                                        if (count($pair) === 2 && $pair[0] !== "" && $pair[1] !== ""):
                                                            $racUrl = "./rac_list.php?group=" . urlencode($pair[0]) . "&code=" . urlencode($pair[1]);
                                                    ?>
                                                            <a class="rac_code" href="<?php echo htmlspecialchars($racUrl, ENT_QUOTES, "UTF-8"); ?>">
                                                                RAC:<?php echo htmlspecialchars($racPart, ENT_QUOTES, "UTF-8"); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="rac_code">RAC:<?php echo htmlspecialchars($racPart, ENT_QUOTES, "UTF-8"); ?></span>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="summary_row">
                                <div class="summary_label">カケトコスコア</div>
                                <div class="summary_value">-</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="detail_rules detail_card">
                    <h2 class="detail_section_title">適用表</h2>
                    <?php if ($ruleRows === []): ?>
                        <p class="detail_empty">適用情報がありません。</p>
                    <?php else: ?>
                        <div class="table_wrap">
                            <table class="detail_table">
                                <thead>
                                    <tr>
                                        <th>作物名</th>
                                        <th>適用病害虫雑草名</th>
                                        <th>希釈倍数使用量</th>
                                        <th>使用時期</th>
                                        <th>本剤の使用回数</th>
                                        <th>使用方法</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ruleRows as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($displayValue($row["crop_name"] ?? null), ENT_QUOTES, "UTF-8"); ?></td>
                                            <td><?php echo htmlspecialchars($displayValue($row["target_name"] ?? null), ENT_QUOTES, "UTF-8"); ?></td>
                                            <td><?php echo htmlspecialchars($displayValue($row["magnification_text"] ?? null), ENT_QUOTES, "UTF-8"); ?></td>
                                            <td><?php echo htmlspecialchars($displayValue($row["timing_text"] ?? null), ENT_QUOTES, "UTF-8"); ?></td>
                                            <td><?php echo htmlspecialchars($displayValue($row["times_text"] ?? null), ENT_QUOTES, "UTF-8"); ?></td>
                                            <td><?php echo htmlspecialchars($displayValue($row["method_name"] ?? null), ENT_QUOTES, "UTF-8"); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </section>
    </main>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="./js/app.js"></script>
    <?php if ($shopifyId !== ""): ?>
        <script>
            (function () {
                const SHOPIFY_DOMAIN = "xn-lckmg7f.myshopify.com";
                const SHOPIFY_STOREFRONT_TOKEN = "bc25ee7aec5d0c9de8b934c6f8e0aa90";

                const img = document.getElementById("detail_product_image");
                if (!img) return;

                const productId = (img.dataset.shopifyId || "").trim();
                if (!productId) return;

                const placeholder = <?php echo json_encode($placeholderImage, JSON_UNESCAPED_UNICODE); ?>;
                img.onerror = function () {
                    img.onerror = null;
                    img.src = placeholder;
                };

                async function fetchShopifyImageUrl(id) {
                    const gid = "gid://shopify/Product/" + id;
                    const query = `
                        query ProductImage($id: ID!) {
                            product(id: $id) {
                                featuredImage {
                                    url
                                }
                            }
                        }
                    `;

                    const res = await fetch(`https://${SHOPIFY_DOMAIN}/api/2024-10/graphql.json`, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-Shopify-Storefront-Access-Token": SHOPIFY_STOREFRONT_TOKEN
                        },
                        body: JSON.stringify({
                            query: query,
                            variables: { id: gid }
                        })
                    });

                    if (!res.ok) {
                        throw new Error("shopify graphql request failed");
                    }

                    const json = await res.json();
                    const url = json?.data?.product?.featuredImage?.url || "";
                    if (!url) {
                        throw new Error("shopify image url not found");
                    }
                    return url;
                }

                fetchShopifyImageUrl(productId)
                    .then(function (url) {
                        img.src = url;
                    })
                    .catch(function () {
                        img.src = placeholder;
                    });
            })();
        </script>
    <?php endif; ?>
</body>

</html>
