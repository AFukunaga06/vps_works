<?php
require_once 'config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: admin.php'); exit; }

$db = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password       = trim($_POST['password'] ?? '');
    $name           = trim($_POST['name'] ?? '');
    $furigana       = trim($_POST['furigana'] ?? '');
    $gender         = trim($_POST['gender'] ?? '');
    $birthday       = trim($_POST['birthday'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $student_worker = trim($_POST['student_worker'] ?? '');
    $marital_status = trim($_POST['marital_status'] ?? '');
    $requests       = trim($_POST['requests'] ?? '');
    $consent        = isset($_POST['consent']) ? 1 : 0;

    if ($name === '') {
        $error = '氏名は必須です。';
    } elseif ($furigana === '') {
        $error = 'ふりがなは必須です。';
    } elseif ($password !== '' && !preg_match('/^[a-zA-Z0-9]{6}$/', $password)) {
        $error = 'パスワードは英数字6文字で入力してください。';
    } else {
        $stmt = $db->prepare("UPDATE personal_info SET
            password=?, name=?, furigana=?, gender=?, birthday=?, email=?, phone=?,
            address=?, student_worker=?, marital_status=?, requests=?, consent=?
            WHERE id=?");
        $stmt->execute([
            $password, $name, $furigana, $gender, $birthday ?: null,
            $email, $phone, $address, $student_worker, $marital_status,
            $requests, $consent, $id
        ]);
        header('Location: admin.php?success=1');
        exit;
    }
}

$row = $db->prepare("SELECT * FROM personal_info WHERE id=?");
$row->execute([$id]);
$row = $row->fetch();
if (!$row) { header('Location: admin.php'); exit; }

// On POST error, override row values with posted values
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['password','name','furigana','gender','birthday','email','phone','address','student_worker','marital_status','requests','consent'] as $f) {
        $row[$f] = $_POST[$f] ?? '';
    }
    $row['consent'] = isset($_POST['consent']) ? 1 : 0;
}

function sel($val, $target) {
    return $val === $target ? 'selected' : '';
}
function chk($val, $target) {
    return $val === $target ? 'checked' : '';
}
function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>編集 - ID<?= $id ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="top-bar">
        <h1>個人情報 編集（ID: <?= $id ?>）</h1>
        <a href="admin.php" class="btn btn-secondary btn-sm">← 一覧へ戻る</a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <table class="form-table">
            <tr>
                <th>作成日</th>
                <td><?= h($row['created_at']) ?></td>
            </tr>
            <tr>
                <th>更新日</th>
                <td><?= h($row['updated_at']) ?></td>
            </tr>
            <tr>
                <th class="required">パスワード</th>
                <td>
                    <input type="text" name="password" maxlength="6" value="<?= h($row['password']) ?>">
                    <p class="hint">英数字6文字</p>
                </td>
            </tr>
            <tr>
                <th class="required">氏名</th>
                <td><input type="text" name="name" value="<?= h($row['name']) ?>"></td>
            </tr>
            <tr>
                <th class="required">ふりがな</th>
                <td><input type="text" name="furigana" value="<?= h($row['furigana']) ?>"></td>
            </tr>
            <tr>
                <th>性別</th>
                <td>
                    <label style="margin-right:16px;">
                        <input type="radio" name="gender" value="男性" <?= chk($row['gender'], '男性') ?>> 男性
                    </label>
                    <label style="margin-right:16px;">
                        <input type="radio" name="gender" value="女性" <?= chk($row['gender'], '女性') ?>> 女性
                    </label>
                    <label>
                        <input type="radio" name="gender" value="その他" <?= chk($row['gender'], 'その他') ?>> その他
                    </label>
                </td>
            </tr>
            <tr>
                <th>生年月日</th>
                <td><input type="date" name="birthday" value="<?= h($row['birthday']) ?>"></td>
            </tr>
            <tr>
                <th>メールアドレス</th>
                <td><input type="email" name="email" value="<?= h($row['email']) ?>"></td>
            </tr>
            <tr>
                <th>電話番号</th>
                <td><input type="tel" name="phone" value="<?= h($row['phone']) ?>"></td>
            </tr>
            <tr>
                <th>住所</th>
                <td><input type="text" name="address" value="<?= h($row['address']) ?>"></td>
            </tr>
            <tr>
                <th>学生／社会人</th>
                <td>
                    <label style="margin-right:16px;">
                        <input type="radio" name="student_worker" value="学生" <?= chk($row['student_worker'], '学生') ?>> 学生
                    </label>
                    <label>
                        <input type="radio" name="student_worker" value="社会人" <?= chk($row['student_worker'], '社会人') ?>> 社会人
                    </label>
                </td>
            </tr>
            <tr>
                <th>既婚／独身</th>
                <td>
                    <select name="marital_status">
                        <option value="">-- 選択 --</option>
                        <option value="既婚" <?= sel($row['marital_status'], '既婚') ?>>既婚</option>
                        <option value="独身" <?= sel($row['marital_status'], '独身') ?>>独身</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>ご意見・ご要望</th>
                <td><textarea name="requests"><?= h($row['requests']) ?></textarea></td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="consent-box">
                        <p>今後の参考のためのみに使用します。同意いただけたらチェックボックスにチェックを入れてください。</p>
                        <label>
                            <input type="checkbox" name="consent" value="1" <?= $row['consent'] ? 'checked' : '' ?>>
                            同意する
                        </label>
                    </div>
                </td>
            </tr>
        </table>
        <div class="submit-row">
            <button type="submit" class="btn btn-primary">更　新</button>
            &nbsp;
            <a href="admin.php" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
</div>
</body>
</html>
