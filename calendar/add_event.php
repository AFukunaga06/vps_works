<?php
require_once __DIR__ . '/gcal_helper.php';

date_default_timezone_set(TIMEZONE);

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $allday   = isset($_POST['allday']);
    $startStr = $_POST['start_time'] ?? '';
    $endStr   = $_POST['end_time'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $postDate = $_POST['date'] ?? $date;

    if ($title === '') {
        $error = 'タイトルを入力してください';
    } else {
        $client  = get_google_client();
        $service = new Google\Service\Calendar($client);

        $event = new Google\Service\Calendar\Event();
        $event->setSummary($title);
        if ($location) $event->setLocation($location);
        if ($desc)     $event->setDescription($desc);

        if ($allday) {
            $start = new Google\Service\Calendar\EventDateTime();
            $start->setDate($postDate);
            $end = new Google\Service\Calendar\EventDateTime();
            $end->setDate($postDate);
            $event->setStart($start);
            $event->setEnd($end);
        } else {
            if (!$startStr || !$endStr) {
                $error = '開始・終了時刻を入力してください';
            } elseif ($startStr >= $endStr) {
                $error = '終了時刻は開始時刻より後にしてください';
            } else {
                $start = new Google\Service\Calendar\EventDateTime();
                $start->setDateTime($postDate . 'T' . $startStr . ':00+09:00');
                $start->setTimeZone(TIMEZONE);
                $end = new Google\Service\Calendar\EventDateTime();
                $end->setDateTime($postDate . 'T' . $endStr . ':00+09:00');
                $end->setTimeZone(TIMEZONE);
                $event->setStart($start);
                $event->setEnd($end);
            }
        }

        if (!$error) {
            $service->events->insert(CALENDAR_ID, $event);
            header('Location: index.php?date=' . urlencode($postDate));
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
<title>予定を追加</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <header>
    <h1>📅 予定を追加</h1>
  </header>

  <?php if ($error): ?>
    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" class="event-form">
    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">

    <div class="form-group">
      <label>タイトル <span class="required">*</span></label>
      <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
    </div>

    <div class="form-group">
      <label>日付</label>
      <input type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    </div>

    <div class="form-group checkbox-group">
      <label>
        <input type="checkbox" name="allday" id="allday" <?= isset($_POST['allday']) ? 'checked' : '' ?>>
        終日
      </label>
    </div>

    <div id="time-fields" class="form-row">
      <div class="form-group">
        <label>開始時刻</label>
        <input type="time" name="start_time" value="<?= htmlspecialchars($_POST['start_time'] ?? '09:00') ?>">
      </div>
      <div class="form-group">
        <label>終了時刻</label>
        <input type="time" name="end_time" value="<?= htmlspecialchars($_POST['end_time'] ?? '10:00') ?>">
      </div>
    </div>

    <div class="form-group">
      <label>場所</label>
      <input type="text" name="location" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label>メモ</label>
      <textarea name="description" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn-submit">追加する</button>
      <a href="index.php?date=<?= htmlspecialchars($date) ?>" class="btn-cancel">キャンセル</a>
    </div>
  </form>
</div>

<script>
const allday = document.getElementById('allday');
const timeFields = document.getElementById('time-fields');
function toggleTime() {
    timeFields.style.display = allday.checked ? 'none' : 'flex';
}
allday.addEventListener('change', toggleTime);
toggleTime();
</script>
</body>
</html>
