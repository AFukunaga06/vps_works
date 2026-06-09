<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = (int)$_SESSION['user_id'];
$db = getDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action             = trim($_POST['action'] ?? 'update');
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
    $prayer_request     = trim($_POST['prayer_request'] ?? '');
    $sermon_expectation = trim($_POST['sermon_expectation'] ?? '');
    $requests           = trim($_POST['requests'] ?? '');
    $consent            = isset($_POST['consent']) ? 1 : 0;

    if ($name === '') {
        $error = '氏名は必須です。';
    } elseif ($furigana === '') {
        $error = 'ふりがなは必須です。';
    } elseif ($nickname === '') {
        $error = 'ニックネームは必須です。';
    } elseif ($password !== '' && !preg_match('/^[a-zA-Z0-9]{4,}$/', $password)) {
        $error = 'パスワードは数字・英数で4文字以上で入力してください。';
    } else {
        // ニックネーム重複チェック（自分以外）
        $dupCheck = $db->prepare("SELECT id FROM personal_info WHERE nickname = ? AND id != ?");
        $dupCheck->execute([$nickname, $id]);
        if ($dupCheck->fetch()) {
            $error = 'このニックネームはすでに使用されています。';
        } elseif ($action === 'new') {
            $stmt = $db->prepare("INSERT INTO personal_info
                (password, nickname, name, furigana, gender, birthday, email, phone,
                 address, student_worker, marital_status, hobby, sermon_opinion, sermon_request, prayer_request, sermon_expectation, requests, consent)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $password, $nickname, $name, $furigana, $gender, $birthday ?: null,
                $email, $phone, $address, $student_worker, $marital_status,
                $hobby, $sermon_opinion, $sermon_request, $prayer_request, $sermon_expectation, $requests, $consent
            ]);
            $newId = (int)$db->lastInsertId();
            $_SESSION['user_id'] = $newId;
            $id = $newId;
            $success = '新規内容として登録しました。';
        } else {
            $stmt = $db->prepare("UPDATE personal_info SET
                password=?, nickname=?, name=?, furigana=?, gender=?, birthday=?, email=?, phone=?,
                address=?, student_worker=?, marital_status=?, hobby=?, sermon_opinion=?, sermon_request=?, prayer_request=?, sermon_expectation=?, requests=?, consent=?
                WHERE id=?");
            $stmt->execute([
                $password, $nickname, $name, $furigana, $gender, $birthday ?: null,
                $email, $phone, $address, $student_worker, $marital_status,
                $hobby, $sermon_opinion, $sermon_request, $prayer_request, $sermon_expectation, $requests, $consent, $id
            ]);
            $success = '情報を更新しました。';
        }
    }
}

$row = $db->prepare("SELECT * FROM personal_info WHERE id=?");
$row->execute([$id]);
$row = $row->fetch();
if (!$row) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error) {
    foreach (['password','nickname','name','furigana','gender','birthday','email','phone','address','student_worker','marital_status','sermon_opinion','sermon_request','prayer_request','sermon_expectation','requests','consent'] as $f) {
        $row[$f] = $_POST[$f] ?? '';
    }
    $row['consent'] = isset($_POST['consent']) ? 1 : 0;
}

function sel($val, $target) { return $val === $target ? 'selected' : ''; }
function chk($val, $target) { return $val === $target ? 'checked' : ''; }
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>マイページ</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="top-bar">
        <h1>マイページ</h1>
        <a href="logout.php" class="btn btn-secondary btn-sm">ログアウト</a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="update" id="formAction">
        <table class="form-table">
            <tr>
                <th class="required">パスワード</th>
                <td>
                    <input type="text" name="password" maxlength="20" value="<?= h($row['password']) ?>">
                    <p class="hint">数字・英数4文字以上</p>
                </td>
            </tr>
            <tr>
                <th class="required">ニックネーム</th>
                <td>
                    <input type="text" name="nickname" maxlength="50" value="<?= h($row['nickname']) ?>" oninput="validateForm()">
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
                    <label style="margin-right:16px;"><input type="radio" name="gender" value="男性" <?= chk($row['gender'], '男性') ?>> 男性</label>
                    <label style="margin-right:16px;"><input type="radio" name="gender" value="女性" <?= chk($row['gender'], '女性') ?>> 女性</label>
                    <label><input type="radio" name="gender" value="その他" <?= chk($row['gender'], 'その他') ?>> その他</label>
                </td>
            </tr>
            <tr>
                <th>生年月日</th>
                <td><input type="date" name="birthday" value="<?= h($row['birthday']) ?>"></td>
            </tr>
            <tr>
                <th class="required">メールアドレス</th>
                <td><input type="email" name="email" value="<?= h($row['email']) ?>"></td>
            </tr>
            <tr>
                <th class="required">電話番号</th>
                <td><input type="tel" name="phone" value="<?= h($row['phone']) ?>" placeholder="例：090-1234-5678"></td>
            </tr>
            <tr>
                <th>住所</th>
                <td><input type="text" name="address" value="<?= h($row['address']) ?>"></td>
            </tr>
            <tr>
                <th>学生／社会人</th>
                <td>
                    <label style="margin-right:16px;"><input type="radio" name="student_worker" value="学生" <?= chk($row['student_worker'], '学生') ?>> 学生</label>
                    <label><input type="radio" name="student_worker" value="社会人" <?= chk($row['student_worker'], '社会人') ?>> 社会人</label>
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
                <td><textarea name="hobby" oninput="validateForm()"><?= h($row['hobby'] ?? '') ?></textarea></td>
            </tr>
            <tr>
                <th class="required">説教について</th>
                <td>
                    <?php $so = $row['sermon_opinion'] ?? ''; ?>
                    <label style="margin-right:16px;">
                        <input type="radio" name="sermon_opinion" value="分かりやすい" <?= chk($so, '分かりやすい') ?> onchange="updateSermonRequest(); validateForm();"> 分かりやすい
                    </label>
                    <label style="margin-right:16px;">
                        <input type="radio" name="sermon_opinion" value="分かりにくい" <?= chk($so, '分かりにくい') ?> onchange="updateSermonRequest(); validateForm();"> 分かりにくい
                    </label>
                    <label>
                        <input type="radio" name="sermon_opinion" value="どちらでもない" <?= chk($so, 'どちらでもない') ?> onchange="updateSermonRequest(); validateForm();"> どちらでもない
                    </label>
                    <div id="sermon_request_row" style="margin-top:8px; <?= in_array($so, ['分かりにくい','どちらでもない']) ? '' : 'display:none;' ?>">
                        <label style="display:block; margin-bottom:4px;">メッセージについての要望</label>
                        <textarea name="sermon_request"><?= h($row['sermon_request'] ?? '') ?></textarea>
                    </div>
                </td>
            </tr>
            <tr>
                <th>悩み事・祈りの課題</th>
                <td><textarea name="prayer_request"><?= h($row['prayer_request'] ?? '') ?></textarea></td>
            </tr>
            <tr>
                <th class="required">教会についてのご意見</th>
                <td><textarea name="requests" oninput="validateForm()"><?= h($row['requests']) ?></textarea></td>
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
        <div id="inputError" style="display:none; color:red; margin-bottom:8px;">記入漏れがあります</div>
        <div class="submit-row">
            <button type="submit" id="submitBtn" class="btn btn-primary" onclick="document.getElementById('formAction').value='update'">更　新</button>
            <button type="submit" id="submitBtnNew" class="btn btn-secondary" onclick="document.getElementById('formAction').value='new'">新規内容</button>
        </div>
    </form>
</div>
<script>
function updateSermonRequest() {
    var selected = document.querySelector('input[name="sermon_opinion"]:checked');
    var row = document.getElementById('sermon_request_row');
    if (selected && (selected.value === '分かりにくい' || selected.value === 'どちらでもない')) {
        row.style.display = '';
    } else {
        row.style.display = 'none';
    }
}
function validateForm() {
    var password      = document.querySelector('input[name="password"]').value.trim();
    var nickname      = document.querySelector('input[name="nickname"]').value.trim();
    var name          = document.querySelector('input[name="name"]').value.trim();
    var furigana      = document.querySelector('input[name="furigana"]').value.trim();
    var email         = document.querySelector('input[name="email"]').value.trim();
    var phone         = document.querySelector('input[name="phone"]').value.trim();
    var sermonOpinion = document.querySelector('input[name="sermon_opinion"]:checked');
    var requests      = document.querySelector('textarea[name="requests"]').value.trim();
    var consent       = document.getElementById("consentCheck").checked;
    var hasError = (password === '' || nickname === '' || name === '' || furigana === '' || (email === '' && phone === '') || !sermonOpinion || requests === '' || !consent);
    var errorDiv = document.getElementById("inputError");
    var btn      = document.getElementById("submitBtn");
    var btnNew   = document.getElementById("submitBtnNew");
    if (hasError) {
        btn.disabled = true;
        btn.style.background = "#aaa";
        btn.style.borderColor = "#aaa";
        btn.style.cursor = "not-allowed";
        btnNew.disabled = true;
        btnNew.style.background = "#aaa";
        btnNew.style.borderColor = "#aaa";
        btnNew.style.cursor = "not-allowed";
        errorDiv.style.display = '';
    } else {
        btn.disabled = false;
        btn.style.background = "";
        btn.style.borderColor = "";
        btn.style.cursor = "";
        btnNew.disabled = false;
        btnNew.style.background = "";
        btnNew.style.borderColor = "";
        btnNew.style.cursor = "";
        errorDiv.style.display = 'none';
    }
}
function updateConsent() { validateForm(); }
document.addEventListener("DOMContentLoaded", validateForm);
</script>
</body>
</html>
