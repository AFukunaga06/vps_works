<?php
require_once __DIR__ . '/../includes/functions.php';

$tenant_id = resolve_public_tenant_id();
$settings  = get_settings($tenant_id);

// 表示月
$y = (int)($_GET['y'] ?? date('Y'));
$m = (int)($_GET['m'] ?? date('n'));
if ($m < 1)  { $m = 12; $y--; }
if ($m > 12) { $m = 1;  $y++; }

$firstDay = sprintf('%04d-%02d-01', $y, $m);
$lastDay  = date('Y-m-t', strtotime($firstDay));
$summary  = compute_month_summary($settings, $tenant_id, $y, $m);

// カレンダー描画用：月初の曜日
$firstDow = (int)date('w', strtotime($firstDay));     // 0=日
$daysInMonth = (int)date('t', strtotime($firstDay));

$prevMonth = mktime(0,0,0, $m - 1, 1, $y);
$nextMonth = mktime(0,0,0, $m + 1, 1, $y);

$today = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime("+{$settings['advance_days']} days"));

// 選択された日付があればスロットを出す
$selectedDate = $_GET['d'] ?? '';
$daySlots = null;
if ($selectedDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $daySlots = compute_day_slots($settings, $tenant_id, $selectedDate);
} else {
    $selectedDate = '';
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>ご予約｜<?= h($settings['business_name']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="public-body">
<header class="public-header">
    <h1><?= h($settings['business_name']) ?></h1>
    <p class="subtitle">ご相談予約フォーム</p>
</header>

<main class="public-main">

<?php if (trim((string)$settings['notice_message']) !== ''): ?>
<div class="notice">
    <?= nl2br(h($settings['notice_message'])) ?>
</div>
<?php endif; ?>

<section class="cal-section">
    <div class="cal-nav">
        <a href="?t=<?= (int)$tenant_id ?>&y=<?= date('Y', $prevMonth) ?>&m=<?= date('n', $prevMonth) ?>" class="btn-mini">‹ 前月</a>
        <strong><?= $y ?>年 <?= $m ?>月</strong>
        <a href="?t=<?= (int)$tenant_id ?>&y=<?= date('Y', $nextMonth) ?>&m=<?= date('n', $nextMonth) ?>" class="btn-mini">次月 ›</a>
    </div>

    <table class="calendar">
        <thead>
            <tr>
                <?php for ($d=0; $d<7; $d++): ?>
                    <th class="dow-<?= $d ?>"><?= h(dow_label($d)) ?></th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
        <?php
        $cellDay = 1 - $firstDow;       // 月初の前に空セルを並べる
        while ($cellDay <= $daysInMonth):
        ?>
            <tr>
            <?php for ($d=0; $d<7; $d++): $cur = $cellDay; $cellDay++; ?>
                <?php if ($cur < 1 || $cur > $daysInMonth): ?>
                    <td class="empty"></td>
                <?php else:
                    $dateStr = sprintf('%04d-%02d-%02d', $y, $m, $cur);
                    $st = $summary[$dateStr] ?? 'closed';
                    $clickable = ($st === 'available' || $st === 'full');
                    $cls = 'cell-' . $st;
                    if ($dateStr === $today) $cls .= ' today';
                    if ($dateStr === $selectedDate) $cls .= ' selected';
                ?>
                    <td class="<?= h($cls) ?>">
                        <?php if ($clickable): ?>
                            <a href="?t=<?= (int)$tenant_id ?>&y=<?= $y ?>&m=<?= $m ?>&d=<?= h($dateStr) ?>#slots">
                                <span class="num"><?= $cur ?></span>
                                <span class="state">
                                    <?= $st === 'available' ? '◯' : '満' ?>
                                </span>
                            </a>
                        <?php else: ?>
                            <span class="num"><?= $cur ?></span>
                            <span class="state">
                                <?php
                                $stateLabel = [
                                    'closed' => '×',
                                    'past' => '—',
                                    'out_of_range' => '—',
                                ][$st] ?? '×';
                                echo $stateLabel;
                                ?>
                            </span>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            <?php endfor; ?>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <p class="legend">
        <span class="lg lg-av">◯ 空きあり</span>
        <span class="lg lg-full">満 満員</span>
        <span class="lg lg-closed">× 休業日</span>
        <span class="lg lg-past">— 受付対象外</span>
    </p>
</section>

<?php if ($daySlots): ?>
<section class="slots-section" id="slots">
    <h2><?= h($selectedDate) ?>
        (<?= h(dow_label((int)date('w', strtotime($selectedDate)))) ?>) の時間枠</h2>

    <?php if ($daySlots['status'] !== 'available'): ?>
        <p class="muted">
        <?php
        echo [
            'closed'       => 'この日は休業日です。',
            'past'         => '過去の日付は予約できません。',
            'out_of_range' => '予約受付期間外です。',
        ][$daySlots['status']] ?? '予約できません。';
        ?>
        </p>
    <?php elseif (!$daySlots['slots']): ?>
        <p class="muted">この日には予約可能な枠がありません。</p>
    <?php else: ?>
        <div class="slot-grid">
        <?php foreach ($daySlots['slots'] as $sl): ?>
            <?php
            $label = substr($sl['start'], 0, 5) . '〜' . substr($sl['end'], 0, 5);
            if ($sl['booked']) {
                echo '<span class="slot booked">' . h($label) . '<br><small>予約済</small></span>';
            } else {
                $href = 'confirm.php?t=' . (int)$tenant_id
                      . '&date=' . h($selectedDate)
                      . '&start=' . h($sl['start']);
                echo '<a class="slot free" href="' . h($href) . '">'
                   . h($label) . '<br><small>選択</small></a>';
            }
            ?>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

</main>

<footer class="public-footer">
    <small>
        お問い合わせ:
        <?php if ($settings['contact_tel']):  ?>TEL <?= h($settings['contact_tel']) ?> / <?php endif; ?>
        <?php if ($settings['contact_email']): ?>Mail <?= h($settings['contact_email']) ?><?php endif; ?>
    </small>
</footer>

<script src="../assets/js/calendar.js"></script>
</body>
</html>
