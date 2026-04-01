<header class="app_header">
    <h1 class="app_title">
        <a href="./index.php">カケトコ mini</a>
    </h1>

    <?php if ($isLoggedIn): ?>
        <div class="header_user">
            <span class="user_name">
                <?= htmlspecialchars($userName, ENT_QUOTES, "UTF-8") ?>さん
            </span>
            <a href="./logout.php" class="logout_btn">ログアウト</a>
        </div>
    <?php endif; ?>

    <div class="header_menu">
        <button type="button" id="menu_btn" class="menu_btn" aria-expanded="false" aria-controls="menu_panel">
            <span class="menu_icon" aria-hidden="true"></span>
            <span class="sr_only">メニュー</span>
        </button>

        <div id="menu_panel" class="menu_panel" hidden>
            <a href="./admin/admin.php" class="menu_item">管理画面</a>
            <a href="./spray_schedule.php" class="menu_item">防除暦作成</a>
            <?php if ($isLoggedIn): ?>
                <a href="./mypage.php" class="menu_item">マイページ</a>
                <a href="./logout.php" class="menu_item">ログアウト</a>
            <?php else: ?>
                <a href="./login.php" class="menu_item">ログイン</a>
            <?php endif; ?>
        </div>
    </div>
</header>
