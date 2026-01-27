<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../src/backend/auth.php";
require_once __DIR__ . "/../src/backend/db.php";

requireLogin("./login.php");

$userId = currentUserId();

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
                    <li class="active">基本情報</li>
                    <li>テスト</li>
                    <li>テスト</li>
                    <li>テスト</li>
                    <li>テスト</li>
                </ul>
            </nav>

            <div class="mypage_content mypage_panel">
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

                <form action="./mypage_update.php" method="POST" class="mypage_form" >
                    <div class="mypage_field">
                        <label for="name">ユーザー名</label>
                        <input type="text" id="name" name="name"
                            value="<?= htmlspecialchars($userName, ENT_QUOTES, "UTF-8"); ?>">
                    </div>

                    <div class="mypage_field">
                        <label for="email">メールアドレス</label>
                        <input type="text" id="email" name="email" 
                            value="<?= htmlspecialchars($userEmail, ENT_QUOTES, "UTF-8"); ?>">
                    </div>

                    <button type="submit" class="mypage_submit">更新する</button>
                </form>
            </div>
        </section>
    </main>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="./js/app.js"></script>
</body>

</html>