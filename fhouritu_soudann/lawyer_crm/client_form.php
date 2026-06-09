<?php
require_once 'config.php';
require_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

$client = [];
if ($id) {
    $st = $db->prepare('SELECT * FROM clients WHERE id=?');
    $st->execute([$id]);
    $client = $st->fetch();
    if (!$client) { header('Location: clients.php'); exit; }
    $page_title = '依頼人編集：' . $client['name'];
} else {
    $page_title = '依頼人新規登録';
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $name_kana  = trim($_POST['name_kana'] ?? '');
    $gender     = $_POST['gender'] ?? '';
    $birth_date = $_POST['birth_date'] ?? '';
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $postal     = trim($_POST['postal_code'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if (!$name) $errors[] = '氏名は必須です。';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'メールアドレスの形式が正しくありません。';

    if (!$errors) {
        if ($id) {
            $st = $db->prepare(
                'UPDATE clients SET name=?,name_kana=?,gender=?,birth_date=?,phone=?,email=?,postal_code=?,address=?,notes=? WHERE id=?'
            );
            $st->execute([$name,$name_kana,$gender,$birth_date?:null,$phone,$email,$postal,$address,$notes,$id]);
            header('Location: client_detail.php?id=' . $id . '&saved=1');
        } else {
            $st = $db->prepare(
                'INSERT INTO clients (name,name_kana,gender,birth_date,phone,email,postal_code,address,notes) VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $st->execute([$name,$name_kana,$gender,$birth_date?:null,$phone,$email,$postal,$address,$notes]);
            header('Location: client_detail.php?id=' . $db->lastInsertId() . '&saved=1');
        }
        exit;
    }
    $client = compact('name','name_kana','gender','birth_date','phone','email','postal_code','address','notes');
    $client['postal_code'] = $postal;
}

require 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="clients.php">依頼人台帳</a></li>
    <?php if ($id): ?><li class="breadcrumb-item"><a href="client_detail.php?id=<?= $id ?>"><?= h($client['name'] ?? '') ?></a></li><?php endif; ?>
    <li class="breadcrumb-item active"><?= $id ? '編集' : '新規登録' ?></li>
  </ol>
</nav>

<?php if ($errors): ?>
<div class="alert alert-danger">
  <?php foreach ($errors as $e): ?><div><i class="bi bi-exclamation-circle me-1"></i><?= h($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" style="max-width:760px">
  <div class="card-header bg-white py-3 fw-semibold">
    <i class="bi bi-person-fill me-2 text-primary"></i><?= h($page_title) ?>
  </div>
  <div class="card-body">
    <form method="post">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">氏名 <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="山田 太郎"
                 value="<?= h($client['name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">ふりがな</label>
          <input type="text" name="name_kana" class="form-control" placeholder="やまだ たろう"
                 value="<?= h($client['name_kana'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">性別</label>
          <select name="gender" class="form-select">
            <option value="">選択してください</option>
            <?php foreach (['男','女','その他'] as $g): ?>
            <option value="<?= $g ?>" <?= ($client['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">生年月日</label>
          <input type="date" name="birth_date" class="form-control"
                 value="<?= h($client['birth_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">電話番号</label>
          <input type="tel" name="phone" class="form-control" placeholder="03-1234-5678"
                 value="<?= h($client['phone'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">メールアドレス</label>
          <input type="email" name="email" class="form-control" placeholder="example@mail.com"
                 value="<?= h($client['email'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">郵便番号</label>
          <input type="text" name="postal_code" class="form-control" placeholder="100-0001"
                 value="<?= h($client['postal_code'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">住所</label>
          <input type="text" name="address" class="form-control" placeholder="東京都千代田区千代田1-1"
                 value="<?= h($client['address'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">メモ・備考</label>
          <textarea name="notes" class="form-control" rows="4"
                    placeholder="特記事項・連絡先補足など"><?= h($client['notes'] ?? '') ?></textarea>
        </div>
      </div>
      <hr class="my-4">
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4">
          <i class="bi bi-check-circle me-1"></i><?= $id ? '更新する' : '登録する' ?>
        </button>
        <a href="<?= $id ? 'client_detail.php?id='.$id : 'clients.php' ?>" class="btn btn-outline-secondary">キャンセル</a>
      </div>
    </form>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
