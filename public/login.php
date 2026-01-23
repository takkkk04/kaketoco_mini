<?php
    ini_set("display_errors", 1);
    ini_set("display_startup_errors", 1);
    error_reporting(E_ALL);
    $error = $_GET["error"] ?? "";
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <link rel="stylesheet" href="./css/reset.css">
    <link rel="stylesheet" href="./css/login.css">
</head>
<body>
    <header>
        <h1>
            <a href="./index.php">カケトコ_mini</a>
        </h1>
    </header>

    <main>
        <h2>ログイン</h2>

        <?php if ($error === "1"): ?>
            <p>メールアドレスまたはパスワードが違います。</p>
        <?php endif; ?>

        <form action="login_act.php" method="POST">
            <div>
                <label for="email">メールアドレス</label>
                <input 
                    type="text"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($_POST["email"] ?? "" ,ENT_QUOTES, "UTF-8"); ?>"
                >
            </div>

            <div>
                <label for="password">パスワード</label>
                <input type="password" name="password" id="password">
            </div>

            <div>
                <button type="submit">ログイン</button>
            </div>
        </form>

        <p>
            <a href="./user_create.php">新規会員登録はこちら</a>
        </p>
    </main>
    
</body>
</html>