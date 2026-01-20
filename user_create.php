<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/db.php";

$done = (($_GET["done"] ?? "") === "1");

// POST（登録処理）
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = (string)($_POST["password"] ?? "");

    // MVPだけど最低限の空チェックはする（空だと後で困る）
    if ($name === "" || $email === "" || $password === "") {
        $error = "未入力の項目があります。";
    } else {
        // パスワードは必ずハッシュ化して保存
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $sql = "INSERT INTO users (name, email, password, created_at, updated_at)
                    VALUES (:name, :email, :password, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ":name" => $name,
                ":email" => $email,
                ":password" => $passwordHash,
            ]);

            // 登録成功 → 完了画面へ
            header("Location: user_create.php?done=1");
            exit();
        } catch (PDOException $e) {
            // email UNIQUEに引っかかった等
            $error = "登録に失敗しました（同じメールが既にある可能性があります）";
            // デバッグしたい時だけ一時的に見る：
            // $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会員登録 | カケトコ mini</title>
    <link rel="stylesheet" href="./css/reset.css">
    <link rel="stylesheet" href="./css/style.css">
</head>

<body>

    <header class="app_header">
        <h1 class="app_title">
            <a href="./index.php">カケトコ mini</a>
        </h1>
    </header>

    <main class="app_main">
        <section class="search_section">
            <h2>会員登録</h2>

            <?php if (!empty($done)): ?>
                <p>会員登録が完了しました。</p>
                <div class="form_row_btn" style="margin-top:12px;">
                    <a href="./index.php" class="register_btn">トップページへ戻る</a>
                </div>

            <?php else: ?>

                <?php if (!empty($error)): ?>
                    <p style="color:#d00; font-weight:700;"><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></p>
                <?php endif; ?>

                <form method="POST" action="./user_create.php">
                    <div class="form_row">
                        <label for="name">名前</label>
                        <input id="name" name="name" type="text" value="<?php echo htmlspecialchars($name ?? "", ENT_QUOTES, "UTF-8"); ?>" />
                    </div>

                    <div class="form_row">
                        <label for="email">メールアドレス</label>
                        <input id="email" name="email" type="text" value="<?php echo htmlspecialchars($email ?? "", ENT_QUOTES, "UTF-8"); ?>" />
                    </div>

                    <div class="form_row">
                        <label for="password">パスワード</label>
                        <input id="password" name="password" type="password" />
                    </div>

                    <div class="form_row_btn">
                        <button type="submit" id="search_btn">登録する</button>
                        <a href="./index.php" class="register_btn">戻る</a>
                    </div>
                </form>

            <?php endif; ?>

        </section>
    </main>

</body>

</html>