<?php
require_once __DIR__ . '/gcal_helper.php';

date_default_timezone_set(TIMEZONE);

$client  = get_google_client();
$service = new Google\Service\Calendar($client);

// 日付パラメータ（デフォルト：今日）
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$startOfDay = $date . 'T00:00:00+09:00';
$endOfDay   = $date . 'T23:59:59+09:00';

$events = $service->events->listEvents(CALENDAR_ID, [
    'timeMin'      => $startOfDay,
    'timeMax'      => $endOfDay,
    'orderBy'      => 'startTime',
    'singleEvents' => true,
    'timeZone'     => TIMEZONE,
]);

$items = $events->getItems();

// 前日・翌日
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));

$displayDate = date('Y年n月j日（' . ['日','月','火','水','木','金','土'][date('w', strtotime($date))] . '）', strtotime($date));
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Googleカレンダー</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <header>
    <h1>📅 Googleカレンダー</h1>
  </header>

  <div class="date-nav">
    <a href="?date=<?= $prevDate ?>" class="nav-btn">◀ 前日</a>
    <form method="get" class="date-form">
      <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">
    </form>
    <a href="?date=<?= $nextDate ?>" class="nav-btn">翌日 ▶</a>
  </div>

  <h2 class="date-title"><?= $displayDate ?></h2>

  <div class="events-list">
    <?php if (empty($items)): ?>
      <p class="no-events">この日の予定はありません</p>
    <?php else: ?>
      <?php foreach ($items as $event): ?>
        <?php
          $start = $event->getStart();
          $end   = $event->getEnd();
          if ($start->dateTime) {
              $timeStr = date('H:i', strtotime($start->dateTime)) . ' 〜 ' . date('H:i', strtotime($end->dateTime));
          } else {
              $timeStr = '終日';
          }
        ?>
        <div class="event-card">
          <div class="event-time"><?= htmlspecialchars($timeStr) ?></div>
          <div class="event-title"><?= htmlspecialchars($event->getSummary() ?? '（タイトルなし）') ?></div>
          <?php if ($event->getDescription()): ?>
            <div class="event-desc"><?= nl2br(htmlspecialchars($event->getDescription())) ?></div>
          <?php endif; ?>
          <?php if ($event->getLocation()): ?>
            <div class="event-location">📍 <?= htmlspecialchars($event->getLocation()) ?></div>
          <?php endif; ?>
          <div class="event-actions">
            <a href="edit_event.php?id=<?= urlencode($event->getId()) ?>&date=<?= $date ?>" class="btn-edit">編集</a>
            <a href="delete_event.php?id=<?= urlencode($event->getId()) ?>&date=<?= $date ?>" class="btn-delete"
               onclick="return confirm('削除しますか？')">削除</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="add-btn-wrap">
    <a href="add_event.php?date=<?= $date ?>" class="btn-add">＋ 予定を追加</a>
  </div>

  <div style="text-align:center;margin-top:12px;">
    <a href="export.php" class="btn-cancel" style="font-size:0.9rem;">📥 CSV出力</a>
  </div>
</div>
</body>
</html>
