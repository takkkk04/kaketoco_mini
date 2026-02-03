<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../src/backend/auth.php";
require_once __DIR__ . "/../src/backend/db.php";

requireLogin("./login.php");

$userId = currentUserId();

$tab = $_GET["tab"] ?? "profile";
if (!in_array($tab, ["profile", "favorites"], true)) {
    $tab = "profile";
}

if ($userId === null) {
    header("Location: ./login.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([":id" => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: ./logout.php");
        exit();
    }
} catch (PDOException $e) {
    die("DBエラー: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8"));
}

$userName = (string)($user["name"] ?? "");
$userEmail = (string)($user["email"] ?? "");
$favorites = [];
if ($tab === "favorites") {
    $stFav = $pdo -> prepare(
        "SELECT
            f.registration_number,
            f.created_at,
            b.name,
            b.rac_code,
            b.shopify_id
        FROM favorites AS f
        LEFT JOIN pesticides_base AS b
        ON b.registration_number = f.registration_number
        WHERE f.user_id = :uid
        ORDER BY f.created_at DESC"
    );
    $stFav -> execute([":uid" => $userId]);
    $favorites = $stFav -> fetchAll(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイページ</title>
    <link rel="stylesheet" href="./css/reset.css">
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/mypage.css">
</head>

<body>
    <header class="app_header">
        <h1 class="app_title">
            <a href="./index.php">カケトコ_mini</a>
        </h1>

        <div class="header_menu">
            <button type="button" class="menu_btn" id="menu_btn" aria-expanded="false" aria-controls="menu_panel">
                <span class="menu_icon" aria-hidden="true"></span>
                <span class="sr_only">メニュー</span>
            </button>

            <div id="menu_panel" class="menu_panel" hidden>
                <a href="./mypage.php" class="menu_item">マイページ</a>
                <a href="./logout.php" class="menu_item">ログアウト</a>
            </div>
        </div>
    </header>

    <main class="app_main">
        <section class="mypage_layout">
            <nav class="mypage_menu">
                <ul>
                    <li class="<?php echo $tab === "profile" ? "active" : ""; ?>">
                        <a href="./mypage.php?tab=profile">基本情報</a>
                    </li>
                    <li class="<?php echo $tab === "favorites" ? "active" : ""; ?>">
                        <a href="./mypage.php?tab=favorites">お気に入り</a>
                    </li>
                    <li>テスト</li>
                    <li>テスト</li>
                    <li>テスト</li>
                </ul>
            </nav>

            <div class="mypage_content mypage_panel">
                <?php if ($tab === "profile"): ?>
                    <h2 class="mypage_title">基本情報</h2>
                    <h3 class="mypage_subtitle">基本情報の変更</h3>

                    <?php if (($_GET["updated"] ?? "") === "1"): ?>
                        <p class="mypage_message is_success">更新しました。</p>
                    <?php endif; ?>

                    <?php if (($_GET["error"] ?? "") !== ""): ?>
                        <p class="mypage_message is_error">
                            <?php
                            $err = (string)($_GET["error"] ?? "");
                            if ($err === "empty") echo "未入力の項目があります。";
                            elseif ($err === "email_taken") echo "そのメールアドレスはすでに使われています。";
                            else echo "更新に失敗しました。";
                            ?>
                        </p>
                    <?php endif; ?>

                    <form action="./mypage_update.php" method="POST" class="mypage_form" id="profile_form">
                        <div class="mypage_field">
                            <label for="name">ユーザー名</label>
                            <input type="text" id="name" name="name"
                                value="<?= htmlspecialchars($userName, ENT_QUOTES, "UTF-8"); ?>"
                                disabled
                                data-initial="<?= htmlspecialchars($userName, ENT_QUOTES, "UTF-8"); ?>">
                        </div>

                        <div class="mypage_field">
                            <label for="email">メールアドレス</label>
                            <input type="text" id="email" name="email" 
                                value="<?= htmlspecialchars($userEmail, ENT_QUOTES, "UTF-8"); ?>"
                                disabled
                                data-initial="<?= htmlspecialchars($userEmail, ENT_QUOTES, "UTF-8"); ?>">
                        </div>

                        <div class="mypage_actions">
                            <button type="button" class="mypage_btn" id="edit_btn">変更する</button>
                            <button type="submit" class="mypage_submit" id="save_btn" hidden>更新する</button>
                            <button type="button" class="mypage_btn is_ghost" id="cancel_btn" hidden>キャンセル</button>
                        </div>                   
                    </form>                   
                <?php else: ?>

                    <h2 class="mypage_title">お気に入り</h2>
                    <h3 class="mypage_subtitle">保存した農薬一覧</h3>

                    <?php if (empty($favorites)): ?>
                        <p class="mypage_message">お気に入りはまだありません。</p>
                    <?php else: ?>

                    <div class="fav_list">
                        <?php foreach($favorites as $f): ?>
                            <?php 
                                $reg = (int)($f["registration_number"] ?? 0);
                                $name = (string)($f["name"] ?? "");
                                $rac = (string)($f["rac_code"] ?? "")
                            ?>

                            <article class="fav_item">
                                <div class="fav_item_head">
                                    <div class="fav_item_title">
                                        <div class="fav_item_name">
                                            <?php echo htmlspecialchars($name !== "" ? $name : "登録番号: {$reg}", ENT_QUOTES, "UTF-8"); ?>
                                        </div>

                                        <?php if ($rac !== ""): ?>
                                            <span class="rac_code">
                                                RAC:<?php echo htmlspecialchars($rac, ENT_QUOTES, "UTF-8") ?>
                                            </span>
                                        <?php endif; ?>

                                        <span class="fav_reg">
                                            #<?php echo $reg; ?>
                                        </span>
                                    </div>

                                    <!-- 解除ボタン -->
                                    <button
                                        type="button"
                                        class="fav_btn is-on"
                                        aria-pressed="true"
                                        data-reg="<?php echo $reg; ?>"
                                        data-remove-on-off="1"
                                        title="お気に入り解除">
                                        <svg class="fav_svg" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                        </svg>
                                        <span class="sr_only">お気に入り解除</span>
                                    </button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="./js/app.js"></script>
    <script src="./js/mypage.js"></script>
</body>

</html>