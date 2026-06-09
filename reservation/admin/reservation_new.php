<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$me = require_admin();
$tenant_id = admin_tenant_id();
$settings  = get_settings($tenant_id);

$types = array_filter(array_map('trim', explode(',', $settings['consultation_types'])), 'strlen');

// ===== 表示月（カレンダー描画用データを生成） =====================
$y = (int)($_GET['y'] ?? date('Y'));
$m = (int)($_GET['m'] ?? date('n'));
if ($m < 1)  { $m = 12; $y--; }
if ($m > 12) { $m = 1;  $y++; }

$firstDay    = sprintf('%04d-%02d-01', $y, $m);
$daysInMonth = (int)date('t', strtotime($firstDay));
$prevMonth   = mktime(0,0,0, $m - 1, 1, $y);
$nextMonth   = mktime(0,0,0, $m + 1, 1, $y);
$today       = date('Y-m-d');

// 各日の状態と空き枠（時間ドロップダウン用）をまとめて JSON 化
$calData = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr = sprintf('%04d-%02d-%02d', $y, $m, $d);
    $info = compute_day_slots($settings, $tenant_id, $dateStr);
    if ($info['status'] !== 'available') {
        $calData[$dateStr] = ['state' => $info['status'], 'slots' => []];
        continue;
    }
    $free = [];
    foreach ($info['slots'] as $sl) {
        if (!$sl['booked']) {
            $free[] = [
                's'     => substr($sl['start'], 0, 5),
                'e'     => substr($sl['end'], 0, 5),
                'start' => $sl['start'],
                'end'   => $sl['end'],
            ];
        }
    }
    $calData[$dateStr] = ['state' => $free ? 'available' : 'full', 'slots' => $free];
}

// ===== 登録処理 (POST) ============================================
$err = '';
$values = [
    'customer_name'     => '',
    'customer_kana'     => '',
    'customer_tel'      => '',
    'customer_email'    => '',
    'consultation_type' => '',
    'memo'              => '',
    'admin_memo'        => '',
    'status'            => 'confirmed',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'セッションが無効です。最初からやり直してください。';
    } else {
        $postDate  = $_POST['date']  ?? '';
        $postStart = $_POST['start'] ?? '';
        if (strlen($postStart) === 5) $postStart .= ':00';

        foreach ($values as $k => $_) {
            $values[$k] = trim((string)($_POST[$k] ?? $values[$k]));
        }

        // 日時の再検証（サーバ側が真）
        $okDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $postDate)
               && preg_match('/^\d{2}:\d{2}:\d{2}$/', $postStart);
        $postEnd = '';
        $postFree = false;
        if ($okDate) {
            $info = compute_day_slots($settings, $tenant_id, $postDate);
            foreach ($info['slots'] as $sl) {
                if ($sl['start'] === $postStart) {
                    $postEnd = $sl['end'];
                    if (!$sl['booked']) $postFree = true;
                    break;
                }
            }
        }

        if (!in_array($values['status'], ['pending','confirmed','canceled','noshow'], true)) {
            $err = 'ステータスが不正です。';
        } elseif (!$okDate || $postEnd === '') {
            $err = '日時が正しく選択されていません。カレンダーから選び直してください。';
        } elseif (!$postFree && in_array($values['status'], ['pending','confirmed'], true)) {
            $err = 'その時間枠はすでに予約が入っています。別の枠をお選びください。';
        } elseif ($values['customer_name'] === '') {
            $err = 'お名前は必須です。';
        } elseif (strlen($values['customer_name']) > 100) {
            $err = 'お名前が長すぎます。';
        } elseif ($values['customer_email'] !== '' &&
                  !filter_var($values['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $err = 'メールアドレスの形式が不正です。';
        } else {
            try {
                $stmt = db()->prepare(
                    'INSERT INTO lc_appointments
                       (tenant_id, reservation_date, start_time, end_time,
                        status, customer_name, customer_kana, customer_tel,
                        customer_email, consultation_type, memo, admin_memo,
                        handled_by, ip, user_agent)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $tenant_id, $postDate, $postStart, $postEnd,
                    $values['status'],
                    $values['customer_name'],
                    $values['customer_kana'],
                    $values['customer_tel'],
                    $values['customer_email'],
                    $values['consultation_type'],
                    $values['memo'],
                    $values['admin_memo'],
                    $me['id'],
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    substr('admin:' . ($me['login_id'] ?? ''), 0, 255),
                ]);
                $newId = (int)db()->lastInsertId();
                redirect('reservation_edit.php?id=' . $newId);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $err = 'その時間枠はすでに予約が入っています（二重予約）。別の枠をお選びください。';
                } else {
                    throw $e;
                }
            }
        }
    }
}

$csrf = csrf_token();
$page_title = '新規予約';
include __DIR__ . '/_header.php';
?>
<style>
/* 寺子屋の予約ページを参考にしたカレンダー＋フォームの横並びレイアウト */
.rsv-wrap { display:flex; gap:24px; flex-wrap:wrap; align-items:flex-start; }
.rsv-cal  { flex:1 1 320px; min-width:300px; }
.rsv-form { flex:1 1 320px; min-width:300px; background:#fff; border:1px solid #e2e2e2;
            border-radius:10px; padding:18px 20px; }
.cal-box  { background:#fff; border:1px solid #e2e2e2; border-radius:10px; padding:14px 16px; }
.cal-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.cal-head strong { font-size:1.1em; }
.cal-nav-btn { border:1px solid #ccc; background:#f7f7f7; border-radius:8px; cursor:pointer;
               width:34px; height:34px; font-size:1.1em; line-height:1; text-decoration:none;
               color:#333; display:inline-flex; align-items:center; justify-content:center; }
.cal-nav-btn:hover { background:#eee; }
.cal-g { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
.cal-w { text-align:center; font-size:.85em; font-weight:700; color:#333; padding:5px 0; }
.cal-w.sun { color:#c0392b; } .cal-w.sat { color:#2c6cc4; }
.cal-d { aspect-ratio:1/1; border:1px solid #e0e0e0; border-radius:8px; background:#fafafa;
         display:flex; flex-direction:column; align-items:center; justify-content:center;
         font-size:1em; font-weight:600; color:#555; cursor:default; padding:2px; }
.cal-d .num { font-size:1.05em; }
.cal-d.empty { border:none; background:transparent; }
.cal-d.avail { background:#eef7ef; color:#2e7d32; border-color:#bfe3c2; cursor:pointer; }
.cal-d.avail:hover { background:#d8eeda; }
.cal-d.full  { background:#fff3e0; color:#b26a00; border-color:#ffd699; cursor:pointer; }
.cal-d.full:hover { background:#ffe9c7; }
.cal-d.closed, .cal-d.past, .cal-d.out_of_range { background:#eee; color:#999; }
.cal-d.today { box-shadow:0 0 0 2px #4a7c59 inset; }
.cal-d.selected { background:#4a7c59 !important; color:#fff !important; border-color:#4a7c59; }
.cal-d .st { font-size:.7em; margin-top:1px; }
.cal-legend { margin-top:10px; font-size:.78em; color:#777; display:flex; gap:12px; flex-wrap:wrap; }
.cal-legend .lg::before { content:"■ "; }
.cal-legend .av::before { color:#2e7d32; } .cal-legend .fu::before { color:#b26a00; }
.cal-legend .cl::before { color:#ccc; }
.rsv-form h3 { margin-top:0; color:#222; }
/* フォームの項目名・入力欄を濃く見やすく */
.rsv-form .form-vert label { color:#222; font-weight:600; font-size:.95rem; }
.rsv-form .form-vert input,
.rsv-form .form-vert select,
.rsv-form .form-vert textarea { color:#222; border-color:#bbb; }
.rsv-form .form-vert input::placeholder,
.rsv-form .form-vert textarea::placeholder { color:#999; }
.rsv-form .form-vert select#fTime { font-weight:700; background:#fffdf5; border-color:#d4a574; }
.rsv-form .form-vert select#fTime:disabled { font-weight:400; background:#f3f3f3; color:#999; border-color:#ccc; }
.sel-date { background:#eef7ef; border:1px solid #bfe3c2; border-radius:8px; padding:8px 12px;
            margin-bottom:14px; font-weight:bold; color:#2e7d32; }
.sel-date.none { background:#f5f5f5; border-color:#e2e2e2; color:#999; font-weight:normal; }
</style>

<h2>新規予約の登録</h2>
<p class="muted">カレンダーから日付を選び、時間枠とお客様情報を入力して登録してください。</p>

<?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>

<div class="rsv-wrap">
    <!-- カレンダー -->
    <div class="rsv-cal">
        <div class="cal-box">
            <div class="cal-head">
                <a class="cal-nav-btn" href="?y=<?= date('Y',$prevMonth) ?>&m=<?= date('n',$prevMonth) ?>" aria-label="前月">‹</a>
                <strong><?= $y ?>年 <?= $m ?>月</strong>
                <a class="cal-nav-btn" href="?y=<?= date('Y',$nextMonth) ?>&m=<?= date('n',$nextMonth) ?>" aria-label="次月">›</a>
            </div>
            <div class="cal-g">
                <?php foreach (['日','月','火','水','木','金','土'] as $i => $w): ?>
                    <div class="cal-w <?= $i===0?'sun':($i===6?'sat':'') ?>"><?= $w ?></div>
                <?php endforeach; ?>
                <?php
                $firstDow = (int)date('w', strtotime($firstDay));
                for ($i = 0; $i < $firstDow; $i++) echo '<div class="cal-d empty"></div>';
                for ($d = 1; $d <= $daysInMonth; $d++):
                    $dateStr = sprintf('%04d-%02d-%02d', $y, $m, $d);
                    $state = $calData[$dateStr]['state'] ?? 'closed';
                    $cls = $state;
                    if ($dateStr === $today) $cls .= ' today';
                    $mark = ['available'=>'◯','full'=>'満','closed'=>'×','past'=>'—','out_of_range'=>'—'][$state] ?? '×';
                ?>
                    <div class="cal-d <?= h($cls) ?>" data-date="<?= h($dateStr) ?>" data-state="<?= h($state) ?>">
                        <span class="num"><?= $d ?></span>
                        <span class="st"><?= $mark ?></span>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="cal-legend">
                <span class="lg av">空きあり</span>
                <span class="lg fu">満員</span>
                <span class="lg cl">休業/対象外</span>
            </div>
        </div>
    </div>

    <!-- フォーム -->
    <div class="rsv-form">
        <h3>予約フォーム</h3>
        <div class="sel-date none" id="selDate">日付を選択してください</div>

        <form method="post" class="form-vert" id="rsvForm">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="date"  id="fDate"  value="">
            <input type="hidden" name="start" id="fStart" value="">

            <label>希望時間 <span class="required">必須</span>
                <select name="_time_display" id="fTime" required disabled>
                    <option value="">まず日付を選択してください</option>
                </select>
            </label>

            <div class="row2">
                <label>お名前 <span class="required">必須</span>
                    <input type="text" name="customer_name" required maxlength="100"
                           placeholder="山田 太郎" value="<?= h($values['customer_name']) ?>">
                </label>
                <label>ふりがな
                    <input type="text" name="customer_kana" maxlength="100"
                           value="<?= h($values['customer_kana']) ?>">
                </label>
            </div>

            <div class="row2">
                <label>電話番号
                    <input type="tel" name="customer_tel" maxlength="30"
                           placeholder="090-1234-5678" value="<?= h($values['customer_tel']) ?>">
                </label>
                <label>メールアドレス
                    <input type="email" name="customer_email" maxlength="255"
                           placeholder="example@email.com" value="<?= h($values['customer_email']) ?>">
                </label>
            </div>

            <div class="row2">
                <label>相談類型
                    <select name="consultation_type">
                        <option value="">—</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= h($t) ?>" <?= $t === $values['consultation_type'] ? 'selected' : '' ?>><?= h($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>ステータス
                    <select name="status">
                        <?php foreach (['confirmed','pending'] as $s): ?>
                            <option value="<?= h($s) ?>" <?= $s === $values['status'] ? 'selected' : '' ?>><?= h(status_label($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <label>備考（相談の概要など）
                <textarea name="memo" rows="3"><?= h($values['memo']) ?></textarea>
            </label>

            <label>管理者メモ（顧客には見えません）
                <textarea name="admin_memo" rows="2"><?= h($values['admin_memo']) ?></textarea>
            </label>

            <button type="submit" class="btn-primary btn-large" id="submitBtn" disabled>この内容で予約を登録</button>
            <p class="muted" style="margin-top:8px;">
                ※ 空き枠から日時を選ぶと送信できます。
            </p>
        </form>
    </div>
</div>

<script>
const CAL_DATA = <?= json_encode($calData, JSON_UNESCAPED_UNICODE) ?>;
const dowJa = ['日','月','火','水','木','金','土'];
// 入力エラーで戻ってきたときに選択状態を復元するための値
const PRESELECT = {
    date:  <?= json_encode($_SERVER['REQUEST_METHOD']==='POST' ? ($_POST['date'] ?? '') : '') ?>,
    start: <?= json_encode($_SERVER['REQUEST_METHOD']==='POST' ? (strlen($_POST['start']??'')===5 ? $_POST['start'].':00' : ($_POST['start']??'')) : '') ?>,
};

const selDateBox = document.getElementById('selDate');
const fDate   = document.getElementById('fDate');
const fStart  = document.getElementById('fStart');
const fTime   = document.getElementById('fTime');
const submitBtn = document.getElementById('submitBtn');

function refreshSubmit() {
    submitBtn.disabled = !(fDate.value && fStart.value);
}

document.querySelectorAll('.cal-d').forEach(cell => {
    const state = cell.dataset.state;
    if (state !== 'available' && state !== 'full') return;
    cell.addEventListener('click', () => {
        document.querySelectorAll('.cal-d.selected').forEach(c => c.classList.remove('selected'));
        cell.classList.add('selected');

        const date = cell.dataset.date;
        const info = CAL_DATA[date] || {slots: []};
        fDate.value = date;
        fStart.value = '';

        const dt = new Date(date + 'T00:00:00');
        const label = date + '（' + dowJa[dt.getDay()] + '）';
        selDateBox.textContent = label + ' を選択中';
        selDateBox.classList.remove('none');

        // 時間ドロップダウンを構築
        fTime.innerHTML = '';
        if (!info.slots.length) {
            fTime.innerHTML = '<option value="">空き枠がありません</option>';
            fTime.disabled = true;
        } else {
            const ph = document.createElement('option');
            ph.value = ''; ph.textContent = '選択してください';
            fTime.appendChild(ph);
            info.slots.forEach(sl => {
                const o = document.createElement('option');
                o.value = sl.start;
                o.dataset.end = sl.end;
                o.textContent = sl.s + '〜' + sl.e;
                fTime.appendChild(o);
            });
            fTime.disabled = false;
        }
        refreshSubmit();
    });
});

fTime.addEventListener('change', () => {
    fStart.value = fTime.value;
    refreshSubmit();
});

// エラー再表示時：選択していた日付と時間を復元
if (PRESELECT.date) {
    const cell = document.querySelector('.cal-d[data-date="' + PRESELECT.date + '"]');
    if (cell && (cell.dataset.state === 'available' || cell.dataset.state === 'full')) {
        cell.click();
        if (PRESELECT.start) {
            fTime.value = PRESELECT.start;
            fStart.value = PRESELECT.start;
            refreshSubmit();
        }
    }
}

// バリデーション
document.getElementById('rsvForm').addEventListener('submit', e => {
    if (!fDate.value || !fStart.value) {
        e.preventDefault();
        alert('カレンダーから日付と時間を選択してください。');
    }
});
</script>

<?php include __DIR__ . '/_footer.php'; ?>
