<?php
require_once __DIR__ . '/gcal_helper.php';

date_default_timezone_set(TIMEZONE);

$month  = isset($_POST['month']) ? (int)$_POST['month'] : (int)date('n');
$day    = isset($_POST['day'])   ? (int)$_POST['day']   : (int)date('j');
$year   = (int)date('Y');
$events = [];
$error  = '';

$days_jp = ['日','月','火','水','木','金','土'];

// フォーム送信時
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['month'], $_POST['day'])) {
    if (!checkdate($month, $day, $year)) {
        $error = '無効な日付です。';
    } else {
        $targetDate = sprintf('%04d-%02d-%02d', $year, $month, $day);

        $client  = get_google_client();
        $service = new Google\Service\Calendar($client);

        $result = $service->events->listEvents(CALENDAR_ID, [
            'timeMin'      => $targetDate . 'T00:00:00+09:00',
            'timeMax'      => $targetDate . 'T23:59:59+09:00',
            'orderBy'      => 'startTime',
            'singleEvents' => true,
            'timeZone'     => TIMEZONE,
        ]);
        $events = $result->getItems();

        // テキストダウンロード
        if (isset($_POST['download'])) {
            $dow = $days_jp[date('w', strtotime($targetDate))];
            $displayDate = date('Y年n月j日', strtotime($targetDate)) . "（{$dow}）";

            header('Content-Type: text/plain; charset=UTF-8');
            header('Content-Disposition: attachment; filename="schedule_' . $targetDate . '.txt"');

            echo "【予定一覧】 {$displayDate}\n";
            echo str_repeat('=', 40) . "\n\n";

            if (empty($events)) {
                echo "この日の予定はありません\n";
            } else {
                foreach ($events as $i => $event) {
                    $n = $i + 1;
                    $start = $event->getStart();
                    $end   = $event->getEnd();
                    if ($start->dateTime) {
                        $timeStr = date('H:i', strtotime($start->dateTime))
                                 . '〜'
                                 . date('H:i', strtotime($end->dateTime));
                    } else {
                        $timeStr = '終日';
                    }
                    echo "{$n}. [{$timeStr}] " . ($event->getSummary() ?? '（タイトルなし）') . "\n";
                    if ($event->getLocation()) {
                        echo "   場所: " . $event->getLocation() . "\n";
                    }
                    if ($event->getDescription()) {
                        echo "   メモ: " . trim($event->getDescription()) . "\n";
                    }
                    echo "\n";
                }
            }
            echo str_repeat('-', 40) . "\n";
            echo "出力日時: " . date('Y/m/d H:i') . "\n";
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>予定テキスト出力</title>
<link rel="stylesheet" href="style.css">
<style>
.month-day-form {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.month-day-form select {
    padding: 10px 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 1.1rem;
    background: #fff;
}
.month-day-form label { font-size: 1rem; color: #555; }
.btn-preview {
    background: #e8f0fe;
    color: #1a73e8;
    border: 1px solid #1a73e8;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 1rem;
    cursor: pointer;
}
.btn-download {
    background: #1a73e8;
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 1rem;
    cursor: pointer;
}
.btn-preview:hover { background: #d2e3fc; }
.btn-download:hover { background: #1558b0; }
.event-list { margin-top: 20px; }
.event-item {
    background: #fff;
    border-left: 4px solid #1a73e8;
    border-radius: 0 8px 8px 0;
    padding: 12px 16px;
    margin-bottom: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,.1);
}
.event-time { font-size: 0.85rem; color: #888; margin-bottom: 4px; }
.event-title { font-size: 1rem; font-weight: bold; }
.event-sub { font-size: 0.88rem; color: #666; margin-top: 4px; }
.alert-error {
    background: #fce8e6;
    color: #c5221f;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 16px;
}
.result-header {
    font-size: 1rem;
    color: #444;
    margin-bottom: 12px;
    padding: 10px;
    background: #e8f0fe;
    border-radius: 6px;
}
</style>
</head>
<body>
<div class="container">
  <header>
    <h1>📄 予定テキスト出力</h1>
  </header>

  <a href="index.php" style="display:inline-block;margin-bottom:20px;color:#1a73e8;font-size:0.9rem;">← カレンダーに戻る</a>

  <?php if ($error): ?>
    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="month-day-form">
      <select name="month">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= $m ?>月</option>
        <?php endfor; ?>
      </select>
      <select name="day">
        <?php for ($d = 1; $d <= 31; $d++): ?>
          <option value="<?= $d ?>" <?= $d === $day ? 'selected' : '' ?>><?= $d ?>日</option>
        <?php endfor; ?>
      </select>
      <button type="submit" name="preview" class="btn-preview">プレビュー</button>
      <button type="submit" name="download" value="1" class="btn-download">📥 テキスト保存</button>
    </div>
  </form>

  <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error): ?>
    <?php
      $targetDate  = sprintf('%04d-%02d-%02d', $year, $month, $day);
      $dow         = $days_jp[date('w', strtotime($targetDate))];
      $displayDate = date('Y年n月j日', strtotime($targetDate)) . "（{$dow}）";
    ?>
    <div class="result-header">
      📅 <?= htmlspecialchars($displayDate) ?> ／ <?= count($events) ?>件
    </div>

    <?php if (empty($events)): ?>
      <p class="no-events">この日の予定はありません</p>
    <?php else: ?>
      <div class="event-list">
        <?php foreach ($events as $event):
          $start = $event->getStart();
          $end   = $event->getEnd();
          if ($start->dateTime) {
              $timeStr = date('H:i', strtotime($start->dateTime))
                       . '〜'
                       . date('H:i', strtotime($end->dateTime));
          } else {
              $timeStr = '終日';
          }
        ?>
        <div class="event-item">
          <div class="event-time"><?= htmlspecialchars($timeStr) ?></div>
          <div class="event-title"><?= htmlspecialchars($event->getSummary() ?? '（タイトルなし）') ?></div>
          <?php if ($event->getLocation()): ?>
            <div class="event-sub">📍 <?= htmlspecialchars($event->getLocation()) ?></div>
          <?php endif; ?>
          <?php if ($event->getDescription()): ?>
            <div class="event-sub">📝 <?= nl2br(htmlspecialchars(trim($event->getDescription()))) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
