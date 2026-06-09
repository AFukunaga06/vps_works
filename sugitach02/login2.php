<?php
require_once __DIR__ . "/includes/auth.php";

$error = "";
$username = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $postedToken = $_POST["csrf_token"] ?? "";

    // CSRFチェック
    if (!verifyCsrfToken($postedToken)) {
        $error = "不正なリクエストです。もう一度お試しください。";
    } elseif ($username === "" || $password === "") {
        $error = "ユーザー名とパスワードを入力してください。";
    } elseif (isAccountLocked($username)) {
        $error = "アカウントがロックされています。しばらくしてからお試しください。";
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password_hash"])) {
            session_regenerate_id(true);
            $_SESSION["user_id"] = (int)$user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];

            resetFailedLogin($username);

            header("Location: index.php");
            exit;
        } else {
            recordFailedLogin($username);
            $error = "ユーザー名またはパスワードが違います。";
        }
    }
}

// 表示直前にCSRF生成（POST検証後に作るのが安全）
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ログイン</title>
</head>
<body>

<h2>ログイン</h2>
  <p style="color:#666;">DEBUG: login.php v2</p>

  <p>sid: <?php echo session_id(); ?></p>
  <p>sess token: <?php echo h($_SESSION['csrf_token'] ?? ''); ?></p>
  <p>post token: <?php echo h($_POST['csrf_token'] ?? ''); ?></p>

  <?php if ($error): ?>
    <p style="color:red;"><?php echo h($error); ?></p>
  <?php endif; ?>

  <form method="post" autocomplete="off">


<p style="color:#666;">DEBUG: login.php v2</p>

<?php if ($error): ?>
  <p style="color:red;"><?php echo h($error); ?></p>
<?php endif; ?>

<form method="post" autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">

  <div>
    <label>ユーザー名</label><br>
    <input type="text" name="username" required value="<?php echo h($username); ?>">
  </div>
  <br>

  <div>
    <label>パスワード</label><br>
    <input type="password" name="password" required>
  </div>
  <br>

  <button type="submit">ログイン</button>
</form>

</body>
</html>
