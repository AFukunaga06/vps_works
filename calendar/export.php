<?php
require_once __DIR__ . '/gcal_helper.php';

date_default_timezone_set(TIMEZONE);

$client  = get_google_client();
$service = new Google\Service\Calendar($client);

$startDate = $_POST['start_date'] ?? $_GET['start_date'] ?? '';
$endDate   = $_POST['end_date']   ?? $_GET['end_date']   ?? '';
$doExport  = isset($_POST['export']) || isset($_GET['export']);

$events = [];
$error  = '';

if ($startDate && $endDate) {
    if ($startDate > $endDate) {
        $error = '終了日は開始日以降にしてください';
    } else {
        $result = $service->events->listEvents(CALENDAR_ID, [
            'timeMin'      => $startDate . 'T00:00:00+09:00',
            'timeMax'      => $endDate   . 'T23:59:59+09:00',
            'orderBy'      => 'startTime',
            'singleEvents' => true,
            'timeZone'     => TIMEZONE,
        ]);
        $events = $result->getItems();

        if ($doExport && !$error) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="calendar_' . $startDate . '_' . $endDate . '.csv"');
            // BOM（Excelで文字化けしないよう）
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, ['日付', '曜日', '開始時刻', '終了時刻', 'タイトル', '場所', 'メモ']);
            $days = ['日','月','火','水','木','金','土'];
            foreach ($events as $event) {
                $startDT = $event->getStart()->getDateTime();
                $endDT   = $event->getEnd()->getDateTime();
                if ($startDT) {
                    $date     = date('Y/m/d', strtotime($startDT));
                    $dow      = $days[date('w', strtotime($startDT))];
                    $startStr = date('H:i', strtotime($startDT));
                    $endStr   = date('H:i', strtotime($endDT));
                } else {
                    $date     = $event->getStart()->getDate();
                    $date     = date('Y/m/d', strtotime($date));
                    $dow      = $days[date('w', strtotime($event->getStart()->getDate()))];
                    $startStr = '終日';
                    $endStr   = '';
                }
                fputcsv($out, [
                    $date,
                    $dow,
                    $startStr,
                    $endStr,
                    $event->getSummary() ?? '',
                    $event->getLocation() ?? '',
                    $event->getDescription() ?? '',
                ]);
            }
            fclose($out);
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
<title>予定をCSV出力</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <header>
    <h1>📅 予定をCSV出力</h1>
  </header>

  <a href="index.php" class="btn-cancel" style="display:inline-block;margin-bottom:20px;">← カレンダーに戻る</a>

  <?php if ($error): ?>
    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" class="event-form">
    <div class="form-row">
      <div class="form-group">
        <label>開始日 <span class="required">*</span></label>
        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
      </div>
      <div class="form-group">
        <label>終了日 <span class="required">*</span></label>
        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" name="preview" class="btn-cancel" style="background:#e8f0fe;color:#1a73e8;">プレビュー</button>
      <button type="submit" name="export" value="1" class="btn-submit">CSVダウンロード</button>
    </div>
  </form>

  <?php if ($startDate && $endDate && !$error): ?>
    <h2 style="margin:24px 0 12px;font-size:1rem;color:#444;">
      <?= htmlspecialchars($startDate) ?> 〜 <?= htmlspecialchars($endDate) ?>
      （<?= count($events) ?>件）
    </h2>

    <?php if (empty($events)): ?>
      <p class="no-events">この期間に予定はありません</p>
    <?php else: ?>
      <table class="export-table">
        <thead>
          <tr><th>日付</th><th>曜日</th><th>時刻</th><th>タイトル</th><th>場所</th></tr>
        </thead>
        <tbody>
          <?php
          $days = ['日','月','火','水','木','金','土'];
          foreach ($events as $event):
            $startDT = $event->getStart()->getDateTime();
            if ($startDT) {
                $date     = date('m/d', strtotime($startDT));
                $dow      = $days[date('w', strtotime($startDT))];
                $timeStr  = date('H:i', strtotime($startDT)) . '〜' . date('H:i', strtotime($event->getEnd()->getDateTime()));
            } else {
                $d        = $event->getStart()->getDate();
                $date     = date('m/d', strtotime($d));
                $dow      = $days[date('w', strtotime($d))];
                $timeStr  = '終日';
            }
          ?>
          <tr>
            <td><?= htmlspecialchars($date) ?></td>
            <td><?= $dow ?></td>
            <td><?= htmlspecialchars($timeStr) ?></td>
            <td><?= htmlspecialchars($event->getSummary() ?? '') ?></td>
            <td><?= htmlspecialchars($event->getLocation() ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
