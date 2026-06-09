<?php
require_once 'config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: admin.php'); exit; }

$db = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password           = trim($_POST['password'] ?? '');
    $nickname           = trim($_POST['nickname'] ?? '');
    $name               = trim($_POST['name'] ?? '');
    $furigana           = trim($_POST['furigana'] ?? '');
    $gender             = trim($_POST['gender'] ?? '');
    $birthday           = trim($_POST['birthday'] ?? '');
    $email              = trim($_POST['email'] ?? '');
    $phone              = trim($_POST['phone'] ?? '');
    $address            = trim($_POST['address'] ?? '');
    $student_worker     = trim($_POST['student_worker'] ?? '');
    $marital_status     = trim($_POST['marital_status'] ?? '');
    $hobby              = trim($_POST['hobby'] ?? '');
    $sermon_opinion     = trim($_POST['sermon_opinion'] ?? '');
    $sermon_request     = trim($_POST['sermon_request'] ?? '');
    $sermon_expectation = trim($_POST['sermon_expectation'] ?? '');
    $requests           = trim($_POST['requests'] ?? '');
    $consent            = isset($_POST['consent']) ? 1 : 0;

    if ($name === '') {
        $error = '氏名は必須です。';
    } elseif ($furigana === '') {
        $error = 'ふりがなは必須です。';
    } elseif ($password !== '' && !preg_match('/^[a-zA-Z0-9]{4,}$/', $password)) {
        $error = 'パスワードは数字・英数で4文字以上で入力してください。';
    } elseif ($nickname !== '') {
        // ニックネーム重複チェック（自分以外）
        $dupCheck = $db->prepare("SELECT id FROM personal_info WHERE nickname = ? AND id != ?");
        $dupCheck->execute([$nickname, $id]);
        if ($dupCheck->fetch()) {
            $error = 'このニックネームはすでに使用されています。';
        }
    }

    if (!$error) {
        $stmt = $db->prepare("UPDATE personal_info SET
            password=?, nickname=?, name=?, furigana=?, gender=?, birthday=?, email=?, phone=?,
            address=?, student_worker=?, marital_status=?, hobby=?, sermon_opinion=?, sermon_request=?, sermon_expectation=?, requests=?, consent=?
            WHERE id=?");
        $stmt->execute([
            $password, $nickname, $name, $furigana, $gender, $birthday ?: null,
            $email, $phone, $address, $student_worker, $marital_status,
            $hobby, $sermon_opinion, $sermon_request, $sermon_expectation, $requests, $consent, $id
        ]);
        header('Location: admin.php?success=1');
        exit;
    }
}

$row = $db->prepare("SELECT * FROM personal_info WHERE id=?");
$row->execute([$id]);
$row = $row->fetch();
if (!$row) { header('Location: admin.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['password','nickname','name','furigana','gender','birthday','email','phone','address','student_worker','marital_status','hobby','sermon_opinion','sermon_request','sermon_expectation','requests','consent'] as $f) {
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
                    <input type="text" name="password" maxlength="20" value="<?= h($row['password']) ?>">
                    <p class="hint">数字・英数4文字以上</p>
                </td>
            </tr>
            <tr>
                <th>ニックネーム</th>
                <td>
                    <input type="text" name="nickname" maxlength="50" value="<?= h($row['nickname']) ?>">
                    <p class="hint">ログインIDとして使用します</p>
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
                <th>趣味について</th>
                <td><textarea name="hobby"><?= h($row['hobby'] ?? '') ?></textarea></td>
            </tr>
            <tr>
                <th>説教について</th>
                <td>
                    <?php $so = $row['sermon_opinion'] ?? ''; ?>
                    <label style="margin-right:16px;">
                        <input type="radio" name="sermon_opinion" value="分かりやすい"
                            <?= chk($so, '分かりやすい') ?>
                            onchange="updateSermonRequest()"> 分かりやすい
                    </label>
                    <label style="margin-right:16px;">
                        <input type="radio" name="sermon_opinion" value="分かりにくい"
                            <?= chk($so, '分かりにくい') ?>
                            onchange="updateSermonRequest()"> 分かりにくい
                    </label>
                    <label>
                        <input type="radio" name="sermon_opinion" value="どちらでもない"
                            <?= chk($so, 'どちらでもない') ?>
                            onchange="updateSermonRequest()"> どちらでもない
                    </label>
                    <div id="sermon_request_row" style="margin-top:8px; <?= in_array($so, ['分かりにくい','どちらでもない']) ? '' : 'display:none;' ?>">
                        <label style="display:block; margin-bottom:4px;">メッセージについての要望</label>
                        <textarea name="sermon_request"><?= h($row['sermon_request'] ?? '') ?></textarea>
                    </div>
                </td>
            </tr>
            <tr>
                <th>教会についてのご意見</th>
                <td><textarea name="requests"><?= h($row['requests']) ?></textarea></td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="consent-box">
                        <p>今後の牧会や伝道のみに使用します。同意いただけたらチェックボックスにチェックを入れてください。</p>
                        <label>
                            <input type="checkbox" name="consent" value="1" id="consentCheck" onchange="updateConsent()" <?= $row['consent'] ? 'checked' : '' ?>>
                            同意する
                        </label>
                    </div>
                </td>
            </tr>
        </table>
        <div class="submit-row">
            <button type="submit" id="submitBtn" class="btn btn-primary">更　新</button>
            &nbsp;
            <a href="admin.php" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
</div>
<script>
function updateConsent() {
    var cb = document.getElementById("consentCheck");
    var btn = document.getElementById("submitBtn");
    if (cb.checked) {
        btn.disabled = false;
        btn.style.background = "";
        btn.style.borderColor = "";
        btn.style.cursor = "";
    } else {
        btn.disabled = true;
        btn.style.background = "#aaa";
        btn.style.borderColor = "#aaa";
        btn.style.cursor = "not-allowed";
    }
}
function updateSermonRequest() {
    var selected = document.querySelector('input[name="sermon_opinion"]:checked');
    var row = document.getElementById('sermon_request_row');
    if (selected && (selected.value === '分かりにくい' || selected.value === 'どちらでもない')) {
        row.style.display = '';
    } else {
        row.style.display = 'none';
    }
}
document.addEventListener("DOMContentLoaded", updateConsent);
</script>
</body>
</html>
