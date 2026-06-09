<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$data = $_SESSION['reserve_data'] ?? null;
if (!$data || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/reserve/index.php');
    exit;
}

// 二重送信防止
if (!empty($_SESSION['reserve_done'])) {
    unset($_SESSION['reserve_data']);
    header('Location: ' . BASE_URL . '/reserve/index.php');
    exit;
}

$is_first = (bool)$data['is_first'];
$status   = $is_first ? 'pending' : 'pending_payment';

try {
    $db = get_db();

    // 空き確認（念のため再チェック）
    $stmt = $db->prepare(
        "SELECT id FROM reservations
         WHERE consultation_type=? AND reserve_date=? AND start_time=? AND status!='cancelled'"
    );
    $stmt->execute([$data['type'], $data['date'], $data['start_time']]);
    if ($stmt->fetch()) {
        throw new RuntimeException('selected_slot_taken');
    }

    $stmt = $db->prepare(
        "INSERT INTO reservations
         (consultation_type, reserve_date, start_time, name, kana, email, tel,
          is_first, consult_method, content, note, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        $data['type'], $data['date'], $data['start_time'],
        $data['name'], $data['kana'], $data['email'], $data['tel'],
        $data['is_first'], $data['consult_method'],
        $data['content'], $data['note'] ?? '', $status,
    ]);
    $reserve_id = $db->lastInsertId();
    $_SESSION['reserve_done'] = true;

    // 顧客管理システムに自動登録（未使用のため一時無効化）
    // require_once __DIR__ . '/../../crm/includes/functions.php';
    // upsert_customer_from_reservation(array_merge($data, ['reserve_id' => $reserve_id]));

    // 管理者にメール送信
    send_reserve_mail($data, (int)$reserve_id);
    // 申込者に自動返信
    send_auto_reply_mail($data, (int)$reserve_id);

} catch (RuntimeException $e) {
    if ($e->getMessage() === 'selected_slot_taken') {
        unset($_SESSION['reserve_data']);
        $error = '申し訳ございません。選択した時間枠は先ほど埋まりました。別の時間をお選びください。';
    } else {
        $error = 'エラーが発生しました: ' . h($e->getMessage());
    }
} catch (Exception $e) {
    $error = 'システムエラーが発生しました。しばらくしてから再度お試しください。';
}

$saved_data = $data;
unset($_SESSION['reserve_data']);
unset($_SESSION['reserve_done']);

$date_label = date('Y年n月j日（', strtotime($saved_data['date']))
    . ['日','月','火','水','木','金','土'][(int)date('w', strtotime($saved_data['date']))] . '）';
$end_time = date('H:i', strtotime($saved_data['start_time']) + 2700);
$type_color = ($saved_data['type'] === '一般相談') ? '#0d47a1' : '#4a148c';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= SITE_NAME ?> - 予約完了</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
:root { --fuku-green: #3a7d5c; }
body { background: #f8f9fa; font-family: 'Hiragino Sans', 'Meiryo', sans-serif; }
.site-header { background: var(--fuku-green); color: #fff; padding: 1rem; }
.check-circle { width: 72px; height: 72px; border-radius: 50%; background: var(--fuku-green);
  display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; }
.check-circle svg { width: 40px; height: 40px; }
</style>
</head>
<body>
<div class="site-header">
  <div class="container">
    <h1 class="h4 mb-0"><?= SITE_NAME ?></h1>
  </div>
</div>

<div class="container py-5" style="max-width: 640px;">
<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?= $error ?></div>
  <a href="index.php" class="btn btn-outline-secondary">カレンダーに戻る</a>

<?php else: ?>
  <div class="text-center mb-4">
    <div class="check-circle">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
    </div>
    <h2 class="h4">お申し込みありがとうございます</h2>
    <p class="text-muted">予約番号：<strong>#<?= str_pad($reserve_id, 5, '0', STR_PAD_LEFT) ?></strong></p>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <table class="table table-bordered mb-0">
        <tr><th style="width:35%; background:#f0f4f1">相談種別</th>
            <td><strong style="color:<?= h($type_color) ?>"><?= h($saved_data['type']) ?></strong></td></tr>
        <tr><th style="background:#f0f4f1">日時</th>
            <td><?= h($date_label) ?> <?= h($saved_data['start_time']) ?>〜<?= h($end_time) ?></td></tr>
        <tr><th style="background:#f0f4f1">相談方法</th>
            <td><?= h($saved_data['consult_method']) ?></td></tr>
        <tr><th style="background:#f0f4f1">お名前</th>
            <td><?= h($saved_data['name']) ?> 様</td></tr>
      </table>
    </div>
  </div>

  <?php if ($is_first): ?>
  <div class="alert alert-success">
    <strong>初回無料相談のお申し込みを受け付けました。</strong><br>
    担当者よりご連絡いたします。しばらくお待ちください。
  </div>
  <?php else: ?>
  <div class="alert alert-info">
    <strong>お支払い手続きのご案内</strong><br>
    担当者より、お支払い方法（振込等）をご案内するメールをお送りします。<br>
    <strong>入金確認後に予約が確定</strong>となります。
  </div>
  <?php endif; ?>

  <div class="card border-0 bg-light mb-4">
    <div class="card-body small">
      <strong>キャンセル・日程変更について</strong><br>
      前日までにご連絡ください。当日キャンセルは原則返金不可です（2回目以降の前払い分）。
    </div>
  </div>

  <div class="text-center">
    <a href="index.php" class="btn btn-outline-secondary">カレンダーに戻る</a>
  </div>
<?php endif; ?>
</div>
</body>
</html>
