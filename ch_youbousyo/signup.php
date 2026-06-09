<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: mypage.php');
    exit;
}

$error   = '';
$success = '';
$values  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'password'       => trim($_POST['password'] ?? ''),
        'nickname'       => trim($_POST['nickname'] ?? ''),
        'name'           => trim($_POST['name'] ?? ''),
        'furigana'       => trim($_POST['furigana'] ?? ''),
        'gender'         => trim($_POST['gender'] ?? ''),
        'birthday_year'  => trim($_POST['birthday_year'] ?? ''),
        'birthday_month' => trim($_POST['birthday_month'] ?? ''),
        'birthday_day'   => trim($_POST['birthday_day'] ?? ''),
        'email'          => trim($_POST['email'] ?? ''),
        'phone'          => trim($_POST['phone'] ?? ''),
        'student_worker' => trim($_POST['student_worker'] ?? ''),
        'marital_status' => trim($_POST['marital_status'] ?? ''),
        'hobby'          => trim($_POST['hobby'] ?? ''),
        'sermon_opinion' => trim($_POST['sermon_opinion'] ?? ''),
        'sermon_request'  => trim($_POST['sermon_request'] ?? ''),
        'prayer_opinion'  => trim($_POST['prayer_opinion'] ?? ''),
        'prayer_request'  => trim($_POST['prayer_request'] ?? ''),
        'requests_opinion' => trim($_POST['requests_opinion'] ?? ''),
        'requests'       => trim($_POST['requests'] ?? ''),
        'consent'        => isset($_POST['consent']) ? 1 : 0,
    ];

    $db = getDB();

    // ニックネーム重複チェック
    if ($values['nickname'] !== '') {
        $dup = $db->prepare("SELECT id FROM personal_info WHERE nickname = ?");
        $dup->execute([$values['nickname']]);
        if ($dup->fetch()) {
            $error = 'このニックネームはすでに使用されています。';
        }
    }

    if (!$error) {
        if ($values['nickname'] === '') {
            $error = 'ニックネームは必須です。';
        } elseif ($values['name'] === '') {
            $error = '氏名は必須です。';
        } elseif ($values['furigana'] === '') {
            $error = 'ふりがなは必須です。';
        } elseif (!preg_match('/^[a-zA-Z0-9]{4,}$/', $values['password'])) {
            $error = 'パスワードは数字・英数で4文字以上で入力してください。';
        } elseif ($values['email'] === '' && $values['phone'] === '') {
            $error = 'メールアドレスまたは電話番号のいずれかを入力してください。';
        } elseif ($values['sermon_opinion'] === '') {
            $error = '説教についてを選択してください。';
        } elseif ($values['requests_opinion'] === '') {
            $error = '教会についてのご意見を選択してください。';
        } elseif ($values['requests_opinion'] === 'あり' && $values['requests'] === '') {
            $error = '教会についてのご意見を入力してください。';
        } elseif (!$values['consent']) {
            $error = '個人情報取扱い方針への同意が必要です。';
        } else {
            if ($values['prayer_opinion'] !== 'あり') {
                    $values['prayer_request'] = '特になし';
                }
                if ($values['requests_opinion'] !== 'あり') {
                    $values['requests'] = '特になし';
                }
                try {
                $by = $values['birthday_year'];
                $bm = $values['birthday_month'];
                $bd = $values['birthday_day'];
                $birthday = ($by !== '' && $bm !== '' && $bd !== '')
                    ? sprintf('%04d-%02d-%02d', (int)$by, (int)$bm, (int)$bd)
                    : null;
                $sql = "INSERT INTO personal_info
                        (password, nickname, name, furigana, gender, birthday, email, phone, student_worker, marital_status, hobby, sermon_opinion, sermon_request, prayer_request, requests, consent)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $values['password'], $values['nickname'], $values['name'], $values['furigana'], $values['gender'],
                    $birthday, $values['email'], $values['phone'],
                    $values['student_worker'], $values['marital_status'], $values['hobby'],
                    $values['sermon_opinion'], $values['sermon_request'], $values['prayer_request'], $values['requests'], $values['consent'],
                ]);
                $newId = $db->lastInsertId();
                $_SESSION['user_id'] = $newId;
                header('Location: mypage.php');
                exit;
            } catch (PDOException $e) {
                $error = '登録エラー：' . $e->getMessage();
            }
        }
    }
}

function v($key, $values) {
    return htmlspecialchars($values[$key] ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>新規登録</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="top-bar">
        <div>
            <h1>新規登録</h1>
            <p class="subtitle">※は必須事項　※メールアドレスと電話番号はいずれか一方以上必須</p>
        </div>
        <a href="login.php" class="btn btn-secondary btn-sm">ログイン画面へ</a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <table class="form-table">
            <tr>
                <th class="required">パスワード</th>
                <td>
                    <input type="text" name="password" maxlength="20"
                           value="<?= v('password', $values) ?>" placeholder="数字・英数4文字以上" oninput="validateForm()">
                    <p class="hint">お好きなパスワードを数字・英数で4文字以上で設定してください</p>
                </td>
            </tr>
            <tr>
                <th class="required">ニックネーム</th>
                <td>
                    <input type="text" name="nickname" maxlength="50"
                           value="<?= v('nickname', $values) ?>" oninput="validateForm()">
                    <p class="hint">ニックネームはログインIDとして使うので英字か英字と数字でお願いします。</p>
                </td>
            </tr>
            <tr>
                <th class="required">氏名</th>
                <td><input type="text" name="name" value="<?= v('name', $values) ?>" oninput="validateForm()"></td>
            </tr>
            <tr>
                <th class="required">ふりがな</th>
                <td><input type="text" name="furigana" value="<?= v('furigana', $values) ?>" oninput="validateForm()"></td>
            </tr>
            <tr>
                <th>性別</th>
                <td>
                    <label style="margin-right:16px;"><input type="radio" name="gender" value="男性" <?= (($values['gender'] ?? '') === '男性') ? 'checked' : '' ?>> 男性</label>
                    <label style="margin-right:16px;"><input type="radio" name="gender" value="女性" <?= (($values['gender'] ?? '') === '女性') ? 'checked' : '' ?>> 女性</label>
                    <label><input type="radio" name="gender" value="その他" <?= (($values['gender'] ?? '') === 'その他') ? 'checked' : '' ?>> その他</label>
                </td>
            </tr>
            <tr>
                <th>生年月日</th>
                <td>
                    <input type="number" name="birthday_year" min="1900" max="2099" style="width:5em;"
                           value="<?= htmlspecialchars($values['birthday_year'] ?? '', ENT_QUOTES, 'UTF-8') ?>">年
                    <input type="number" name="birthday_month" min="1" max="12" style="width:3.5em;"
                           value="<?= htmlspecialchars($values['birthday_month'] ?? '', ENT_QUOTES, 'UTF-8') ?>">月
                    <input type="number" name="birthday_day" min="1" max="31" style="width:3.5em;"
                           value="<?= htmlspecialchars($values['birthday_day'] ?? '', ENT_QUOTES, 'UTF-8') ?>">日
                </td>
            </tr>
            <tr>
                <th class="required">メールアドレス</th>
                <td><input type="email" name="email" value="<?= v('email', $values) ?>" oninput="validateForm()"></td>
            </tr>
            <tr>
                <th class="required">電話番号</th>
                <td><input type="tel" name="phone" value="<?= v('phone', $values) ?>" placeholder="例：090-1234-5678" oninput="validateForm()"></td>
            </tr>
            <tr>
                <th>学生／社会人</th>
                <td>
                    <label style="margin-right:16px;"><input type="radio" name="student_worker" value="学生" <?= (($values['student_worker'] ?? '') === '学生') ? 'checked' : '' ?>> 学生</label>
                    <label><input type="radio" name="student_worker" value="社会人" <?= (($values['student_worker'] ?? '') === '社会人') ? 'checked' : '' ?>> 社会人</label>
                </td>
            </tr>
            <tr>
                <th>既婚／独身</th>
                <td>
                    <select name="marital_status">
                        <option value="">-- 選択 --</option>
                        <option value="既婚" <?= (($values['marital_status'] ?? '') === '既婚') ? 'selected' : '' ?>>既婚</option>
                        <option value="独身" <?= (($values['marital_status'] ?? '') === '独身') ? 'selected' : '' ?>>独身</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>趣味について</th>
                <td><textarea name="hobby"><?= v('hobby', $values) ?></textarea></td>
            </tr>
            <tr>
                <th class="required">説教について</th>
                <td>
                    <?php $so = $values['sermon_opinion'] ?? ''; ?>
                    <label style="margin-right:16px;">
                        <input type="radio" name="sermon_opinion" value="分かりやすい" <?= ($so === '分かりやすい') ? 'checked' : '' ?> onchange="updateSermonRequest(); validateForm();"> 分かりやすい
                    </label>
                    <label style="margin-right:16px;">
                        <input type="radio" name="sermon_opinion" value="分かりにくい" <?= ($so === '分かりにくい') ? 'checked' : '' ?> onchange="updateSermonRequest(); validateForm();"> 分かりにくい
                    </label>
                    <label>
                        <input type="radio" name="sermon_opinion" value="どちらでもない" <?= ($so === 'どちらでもない') ? 'checked' : '' ?> onchange="updateSermonRequest(); validateForm();"> どちらでもない
                    </label>
                    <div id="sermon_request_row" style="margin-top:8px; <?= in_array($so, ['分かりにくい','どちらでもない']) ? '' : 'display:none;' ?>">
                        <label style="display:block; margin-bottom:4px;">メッセージについての要望</label>
                        <textarea name="sermon_request"><?= v('sermon_request', $values) ?></textarea>
                    </div>
                </td>
            </tr>
            <tr>
                <th>悩み事・祈りの課題</th>
                <td>
                    <?php $po = $values['prayer_opinion'] ?? ''; ?>
                    <label style="margin-right:16px;">
                        <input type="radio" name="prayer_opinion" value="特になし" <?= ($po === '特になし') ? 'checked' : '' ?> onchange="updatePrayerRequest();"> 特になし
                    </label>
                    <label>
                        <input type="radio" name="prayer_opinion" value="あり" <?= ($po === 'あり') ? 'checked' : '' ?> onchange="updatePrayerRequest();"> あり
                    </label>
                    <div id="prayer_request_row" style="margin-top:8px; <?= ($po === 'あり') ? '' : 'display:none;' ?>">
                        <textarea name="prayer_request"><?= v('prayer_request', $values) ?></textarea>
                    </div>
                </td>
            </tr>
            <tr>
                <th class="required">教会についてのご意見</th>
                <td>
                    <?php $ro = $values['requests_opinion'] ?? ''; ?>
                    <label style="margin-right:16px;">
                        <input type="radio" name="requests_opinion" value="特になし" <?= ($ro === '特になし') ? 'checked' : '' ?> onchange="updateRequestsOpinion(); validateForm();"> 特になし
                    </label>
                    <label>
                        <input type="radio" name="requests_opinion" value="あり" <?= ($ro === 'あり') ? 'checked' : '' ?> onchange="updateRequestsOpinion(); validateForm();"> あり
                    </label>
                    <div id="requests_text_row" style="margin-top:8px; <?= ($ro === 'あり') ? '' : 'display:none;' ?>">
                        <textarea name="requests" oninput="validateForm()"><?= v('requests', $values) ?></textarea>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="consent-box">
                        <p>今後の牧会や伝道のみに使用します。同意いただけたらチェックボックスにチェックを入れてください。</p>
                        <label>
                            <input type="checkbox" name="consent" value="1" id="consentCheck" onchange="updateConsent()"
                                <?= !empty($values['consent']) ? 'checked' : '' ?>>
                            同意する
                        </label>
                    </div>
                </td>
            </tr>
        </table>
        <div id="inputError" style="display:none; color:red; margin-bottom:8px;">記入漏れがあります</div>
        <div class="submit-row">
            <button type="submit" id="submitBtn" class="btn btn-primary" disabled style="background:#aaa;border-color:#aaa;cursor:not-allowed;">登　録</button>
        </div>
    </form>
</div>
<script>
function updatePrayerRequest() {
    var selected = document.querySelector('input[name="prayer_opinion"]:checked');
    var row = document.getElementById('prayer_request_row');
    row.style.display = (selected && selected.value === 'あり') ? '' : 'none';
}
function updateSermonRequest() {
    var selected = document.querySelector('input[name="sermon_opinion"]:checked');
    var row = document.getElementById('sermon_request_row');
    row.style.display = (selected && (selected.value === '分かりにくい' || selected.value === 'どちらでもない')) ? '' : 'none';
}
function updateRequestsOpinion() {
    var selected = document.querySelector('input[name="requests_opinion"]:checked');
    var row = document.getElementById('requests_text_row');
    row.style.display = (selected && selected.value === 'あり') ? '' : 'none';
}
function validateForm() {
    var password      = document.querySelector('input[name="password"]').value.trim();
    var nickname      = document.querySelector('input[name="nickname"]').value.trim();
    var name          = document.querySelector('input[name="name"]').value.trim();
    var furigana      = document.querySelector('input[name="furigana"]').value.trim();
    var email         = document.querySelector('input[name="email"]').value.trim();
    var phone         = document.querySelector('input[name="phone"]').value.trim();
    var sermonOpinion = document.querySelector('input[name="sermon_opinion"]:checked');
    var requestsOpinion = document.querySelector('input[name="requests_opinion"]:checked');
    var requestsText    = document.querySelector('textarea[name="requests"]') ? document.querySelector('textarea[name="requests"]').value.trim() : '';
    var consent       = document.getElementById("consentCheck").checked;
    var hasError = (password === '' || nickname === '' || name === '' || furigana === '' || (email === '' && phone === '') || !sermonOpinion || !requestsOpinion || (requestsOpinion && requestsOpinion.value === 'あり' && requestsText === '') || !consent);
    var btn      = document.getElementById("submitBtn");
    var errorDiv = document.getElementById("inputError");
    var anyInput = (password !== '' || nickname !== '' || name !== '' || furigana !== '' || email !== '' || phone !== '' || consent);
    if (hasError) {
        btn.disabled = true;
        btn.style.background = "#aaa";
        btn.style.borderColor = "#aaa";
        btn.style.cursor = "not-allowed";
        errorDiv.style.display = anyInput ? '' : 'none';
    } else {
        btn.disabled = false;
        btn.style.background = "";
        btn.style.borderColor = "";
        btn.style.cursor = "";
        errorDiv.style.display = 'none';
    }
}
function updateConsent() { validateForm(); }
document.addEventListener("DOMContentLoaded", validateForm);
</script>
</body>
</html>
