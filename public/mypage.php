<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../src/backend/auth.php";

requireLogin("./login.php");

$userName = currentUserName();
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

            <div class="mypage_content">
                <h2>基本情報</h2>
                <p><?= htmlspecialchars($userName, ENT_QUOTES, "UTF-8"); ?> さん</p>
            </div>            
        </section>
    </main>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="./js/app.js"></script>
</body>
</html>