<?php
require_once __DIR__ . '/../includes/functions.php';

session_name(RESV_SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

$tenant_id = resolve_public_tenant_id();
$settings  = get_settings($tenant_id);

$types = array_filter(array_map('trim', explode(',', (string)$settings['consultation_types'])), 'strlen');

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
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'セッションが無効です。最初からやり直してください。';
    } else {
        // Honeypot — 隠しフィールドに値が入っていたら拒否
        if (!empty($_POST['website'])) {
            redirect('thanks.php?error=spam&t=' . (int)$tenant_id);
        }

        $postDate  = $_POST['date']  ?? '';
        $postStart = $_POST['start'] ?? '';
        $postDur   = (int)($_POST['duration'] ?? 0);   // 30=初回 / 60=初回以降
        if (strlen($postStart) === 5) $postStart .= ':00';

        foreach ($values as $k => $_) {
            $values[$k] = trim((string)($_POST[$k] ?? $values[$k]));
        }

        // 日時の再検証（サーバ側が真）
        $okDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $postDate)
               && preg_match('/^\d{2}:\d{2}:\d{2}$/', $postStart)
               && in_array($postDur, [30, 60], true);
        $postEnd = '';
        $postFree = false;
        if ($okDate) {
            $info  = compute_day_slots($settings, $tenant_id, $postDate);
            $slots = $info['slots'];
            $idx = -1;
            foreach ($slots as $i => $sl) {
                if ($sl['start'] === $postStart) { $idx = $i; break; }
            }
            if ($idx >= 0) {
                if ($postDur === 30) {
                    $postEnd  = $slots[$idx]['end'];
                    $postFree = !$slots[$idx]['booked'];
                } else { // 60分 = 連続する2枠が両方空いていること
                    if (isset($slots[$idx + 1])
                        && $slots[$idx]['end'] === $slots[$idx + 1]['start']) {
                        $postEnd  = $slots[$idx + 1]['end'];
                        $postFree = !$slots[$idx]['booked'] && !$slots[$idx + 1]['booked'];
                    }
                }
            }
        }

        if (!$okDate || $postEnd === '') {
            $err = '日時が正しく選択されていません。カレンダーから選び直してください。';
        } elseif (!$postFree) {
            $err = 'その時間枠はすでに予約が入っています。別の枠をお選びください。';
        } elseif ($values['customer_name'] === '') {
            $err = '氏名は必須です。';
        } elseif (strlen($values['customer_name']) > 100) {
            $err = '氏名が長すぎます。';
        } elseif ($values['customer_email'] !== '' &&
                  !filter_var($values['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $err = 'メールアドレスの形式が不正です。';
        } else {
            try {
                $stmt = db()->prepare(
                    'INSERT INTO lc_appointments
                       (tenant_id, reservation_date, start_time, end_time,
                        status, customer_name, customer_kana, customer_tel,
                        customer_email, consultation_type, memo, ip, user_agent)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $tenant_id, $postDate, $postStart, $postEnd,
                    'pending',
                    $values['customer_name'],
                    $values['customer_kana'],
                    $values['customer_tel'],
                    $values['customer_email'],
                    $values['consultation_type'],
                    $values['memo'],
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ]);
                $newId = (int)db()->lastInsertId();

                // ===== 予約通知メール送信（失敗しても予約は確定） =========
                require_once __DIR__ . '/../includes/mailer.php';
                $bizName   = trim((string)$settings['business_name']) ?: 'ご相談予約';
                $adminTo   = defined('RESV_ADMIN_EMAIL') ? RESV_ADMIN_EMAIL : 'REDACTED_EMAIL';
                $dowJp     = dow_label((int)date('w', strtotime($postDate)));
                $dateLine  = "{$postDate}（{$dowJp}） "
                           . substr($postStart,0,5) . '〜' . substr($postEnd,0,5);
                $catLabel  = $postDur === 60 ? '初回以降（60分）' : '初回（30分）';
                $typeLine  = $values['consultation_type'] !== '' ? $values['consultation_type'] : '（未選択）';

                $detail =
                    "ご予約日時 : {$dateLine}\n"
                  . "相談時間   : {$catLabel}\n"
                  . "お名前     : {$values['customer_name']}\n"
                  . "ふりがな   : " . ($values['customer_kana'] !== '' ? $values['customer_kana'] : '（未入力）') . "\n"
                  . "電話番号   : " . ($values['customer_tel']  !== '' ? $values['customer_tel']  : '（未入力）') . "\n"
                  . "メール     : " . ($values['customer_email']!== '' ? $values['customer_email']: '（未入力）') . "\n"
                  . "ご相談内容 : {$typeLine}\n"
                  . "備考       : " . ($values['memo'] !== '' ? $values['memo'] : '（なし）') . "\n";

                // 管理者あて
                $adminBody =
                    "{$bizName} に新しいご予約（仮予約）が入りました。\n\n"
                  . $detail
                  . "\n----------------------------------------\n"
                  . "管理画面で内容の確認・確定を行ってください。\n"
                  . "予約ID: {$newId}\n";
                resv_send_mail(
                    $adminTo, '予約管理',
                    "【予約受付】{$values['customer_name']} 様 / {$dateLine}",
                    $adminBody,
                    $bizName,
                    ($values['customer_email'] !== '' ? $values['customer_email'] : null),
                    $values['customer_name']
                );

                // 予約者あて（メール入力がある場合のみ）
                if ($values['customer_email'] !== '') {
                    $userBody =
                        "{$values['customer_name']} 様\n\n"
                      . "この度は {$bizName} へご予約いただき、誠にありがとうございます。\n"
                      . "以下の内容で『仮予約』として承りました。\n\n"
                      . $detail
                      . "\n担当者が内容を確認のうえ、確定のご連絡を改めてお送りいたします。\n"
                      . "※本メールは送信専用です。ご不明点は事務所までお問い合わせください。\n\n"
                      . "{$bizName}\n";
                    resv_send_mail(
                        $values['customer_email'], $values['customer_name'],
                        "【仮予約を受け付けました】{$bizName}",
                        $userBody,
                        $bizName,
                        $adminTo, $bizName
                    );
                }

                redirect('thanks.php?id=' . $newId . '&t=' . (int)$tenant_id);
            } catch (PDOException $e) {
                // UNIQUE 制約違反 = 二重予約
                if ($e->getCode() === '23000') {
                    redirect('thanks.php?error=conflict&t=' . (int)$tenant_id);
                }
                throw $e;
            }
        }
    }
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>ご予約｜<?= h($settings['business_name']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
/* カレンダー＋フォームの横並びレイアウト（管理画面の新規予約と同形式） */
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
.rsv-form .form-vert label { color:#222; font-weight:600; font-size:.95rem; }
.rsv-form .form-vert input,
.rsv-form .form-vert select,
.rsv-form .form-vert textarea { color:#222; border-color:#bbb;
  width:100%; min-width:0; max-width:100%; box-sizing:border-box; }
/* 2カラム行・各ラベルがグリッド幅を超えてはみ出さないようにする */
.rsv-form .form-vert .row2 { min-width:0; align-items:end; }
.rsv-form .form-vert .row2 > label { min-width:0; }
.rsv-form .form-vert textarea { resize:vertical; }
/* 電話番号｜メールアドレス行：メール欄を少し広げる */
.rsv-form .form-vert .row2-mail { grid-template-columns: 0.85fr 1.3fr; }
.rsv-form .form-vert input::placeholder,
.rsv-form .form-vert textarea::placeholder { color:#999; }
.rsv-form .form-vert select#fTime { font-weight:700; background:#fffdf5; border-color:#d4a574; }
.rsv-form .form-vert select#fTime:disabled { font-weight:400; background:#f3f3f3; color:#999; border-color:#ccc; }
.sel-date { background:#eef7ef; border:1px solid #bfe3c2; border-radius:8px; padding:8px 12px;
            margin-bottom:14px; font-weight:bold; color:#2e7d32; }
.sel-date.none { background:#f5f5f5; border-color:#e2e2e2; color:#999; font-weight:normal; }
.time-pick { margin-bottom:.85rem; }
.time-pick-head { color:#222; font-weight:600; font-size:.95rem; margin-bottom:.35rem; }
</style>
</head>
<body class="public-body">
<header class="public-header">
    <h1><?= h($settings['business_name']) ?></h1>
    <p class="subtitle">ご相談予約フォーム</p>
</header>

<main class="public-main">

<p style="margin:0 0 1rem;">
    <a href="home.php?t=<?= (int)$tenant_id ?>">← トップ（事務所紹介）へ戻る</a>
</p>

<?php if (trim((string)$settings['notice_message']) !== ''): ?>
<div class="notice"><?= nl2br(h($settings['notice_message'])) ?></div>
<?php endif; ?>

<p class="muted">カレンダーから日付を選び、時間枠とお客様情報を入力してご予約ください。</p>

<?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>

<div class="rsv-wrap">
    <!-- カレンダー -->
    <div class="rsv-cal">
        <div class="cal-box">
            <div class="cal-head">
                <a class="cal-nav-btn" href="?t=<?= (int)$tenant_id ?>&y=<?= date('Y',$prevMonth) ?>&m=<?= date('n',$prevMonth) ?>" aria-label="前月">‹</a>
                <strong><?= $y ?>年 <?= $m ?>月</strong>
                <a class="cal-nav-btn" href="?t=<?= (int)$tenant_id ?>&y=<?= date('Y',$nextMonth) ?>&m=<?= date('n',$nextMonth) ?>" aria-label="次月">›</a>
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
                    $cls = ($state === 'available') ? 'avail' : $state;
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
            <input type="hidden" name="date"     id="fDate"     value="">
            <input type="hidden" name="start"    id="fStart"    value="">
            <input type="hidden" name="duration" id="fDuration" value="">

            <!-- Honeypot -->
            <div style="position:absolute;left:-9999px;">
                <label>Website
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </label>
            </div>

            <div class="time-pick">
                <div class="time-pick-head">ご希望の時間 <span class="required">必須</span>
                    <span class="muted" style="font-weight:400;font-size:.8rem;">（どちらか一方を選択）</span>
                </div>
                <div class="row2">
                    <label>初回30分
                        <select id="fTime30" disabled>
                            <option value="">まず日付を選択してください</option>
                        </select>
                    </label>
                    <label>初回以降60分
                        <select id="fTime60" disabled>
                            <option value="">まず日付を選択してください</option>
                        </select>
                    </label>
                </div>
            </div>

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

            <div class="row2 row2-mail">
                <label>電話番号
                    <input type="tel" name="customer_tel" maxlength="30"
                           placeholder="090-1234-5678" value="<?= h($values['customer_tel']) ?>">
                </label>
                <label>メールアドレス
                    <input type="email" name="customer_email" maxlength="255"
                           placeholder="example@email.com" value="<?= h($values['customer_email']) ?>">
                </label>
            </div>

            <label>ご相談内容
                <select name="consultation_type">
                    <option value="">—（選択してください）</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= h($t) ?>" <?= $t === $values['consultation_type'] ? 'selected' : '' ?>><?= h($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>備考（ご相談の概要など）
                <textarea name="memo" rows="3"><?= h($values['memo']) ?></textarea>
            </label>

            <button type="submit" class="btn-primary btn-large" id="submitBtn" disabled>この内容で予約する</button>
            <p class="muted" style="margin-top:8px;">
                ※ 空き枠から日時を選ぶと送信できます。送信後は<strong>仮予約</strong>として承り、担当者より確定のご連絡をお送りします。
            </p>
        </form>
    </div>
</div>

</main>

<footer class="public-footer">
    <small>
        お問い合わせ:
        <?php if ($settings['contact_tel']):  ?>TEL <?= h($settings['contact_tel']) ?> / <?php endif; ?>
        <?php if ($settings['contact_email']): ?>Mail <?= h($settings['contact_email']) ?><?php endif; ?>
    </small>
</footer>

<script>
const CAL_DATA = <?= json_encode($calData, JSON_UNESCAPED_UNICODE) ?>;
const dowJa = ['日','月','火','水','木','金','土'];
// 入力エラーで戻ってきたときに選択状態を復元するための値
const PRESELECT = {
    date:     <?= json_encode($_SERVER['REQUEST_METHOD']==='POST' ? ($_POST['date'] ?? '') : '') ?>,
    start:    <?= json_encode($_SERVER['REQUEST_METHOD']==='POST' ? (strlen($_POST['start']??'')===5 ? $_POST['start'].':00' : ($_POST['start']??'')) : '') ?>,
    duration: <?= json_encode($_SERVER['REQUEST_METHOD']==='POST' ? (string)($_POST['duration'] ?? '') : '') ?>,
};

const selDateBox = document.getElementById('selDate');
const fDate     = document.getElementById('fDate');
const fStart    = document.getElementById('fStart');
const fDuration = document.getElementById('fDuration');
const fTime30   = document.getElementById('fTime30');
const fTime60   = document.getElementById('fTime60');
const submitBtn = document.getElementById('submitBtn');

function refreshSubmit() {
    submitBtn.disabled = !(fDate.value && fStart.value && fDuration.value);
}

function clearChoice() {
    fStart.value = '';
    fDuration.value = '';
}

document.querySelectorAll('.cal-d').forEach(cell => {
    const state = cell.dataset.state;
    if (state !== 'available' && state !== 'full') return;
    cell.addEventListener('click', () => {
        document.querySelectorAll('.cal-d.selected').forEach(c => c.classList.remove('selected'));
        cell.classList.add('selected');

        const date = cell.dataset.date;
        const info = CAL_DATA[date] || {slots: []};
        const slots = info.slots || [];
        fDate.value = date;
        clearChoice();

        const dt = new Date(date + 'T00:00:00');
        const label = date + '（' + dowJa[dt.getDay()] + '）';
        selDateBox.textContent = label + ' を選択中';
        selDateBox.classList.remove('none');

        // --- 初回30分（既存の空き枠） ---
        fTime30.innerHTML = '';
        // --- 初回以降60分（連続する2枠） ---
        fTime60.innerHTML = '';

        if (!slots.length) {
            fTime30.innerHTML = '<option value="">空き枠がありません</option>'; fTime30.disabled = true;
            fTime60.innerHTML = '<option value="">空き枠がありません</option>'; fTime60.disabled = true;
            refreshSubmit();
            return;
        }

        fTime30.appendChild(new Option('選択してください', ''));
        slots.forEach(sl => fTime30.appendChild(new Option(sl.s + '〜' + sl.e, sl.start)));
        fTime30.disabled = false;

        fTime60.appendChild(new Option('選択してください', ''));
        let any60 = false;
        for (let i = 0; i + 1 < slots.length; i++) {
            if (slots[i].end === slots[i + 1].start) {       // 連続＝間に予約済みが無い
                fTime60.appendChild(new Option(slots[i].s + '〜' + slots[i + 1].e, slots[i].start));
                any60 = true;
            }
        }
        if (!any60) fTime60.appendChild(new Option('連続した空き枠がありません', ''));
        fTime60.disabled = false;

        refreshSubmit();
    });
});

// 一方を選んだら他方をリセット（排他選択）
fTime30.addEventListener('change', () => {
    if (fTime30.value) {
        fStart.value = fTime30.value; fDuration.value = '30';
        fTime60.value = '';
    } else { clearChoice(); }
    refreshSubmit();
});
fTime60.addEventListener('change', () => {
    if (fTime60.value) {
        fStart.value = fTime60.value; fDuration.value = '60';
        fTime30.value = '';
    } else { clearChoice(); }
    refreshSubmit();
});

// エラー再表示時：選択していた日付・時間・区分を復元
if (PRESELECT.date) {
    const cell = document.querySelector('.cal-d[data-date="' + PRESELECT.date + '"]');
    if (cell && (cell.dataset.state === 'available' || cell.dataset.state === 'full')) {
        cell.click();
        if (PRESELECT.start && PRESELECT.duration === '60') {
            fTime60.value = PRESELECT.start; fTime60.dispatchEvent(new Event('change'));
        } else if (PRESELECT.start) {
            fTime30.value = PRESELECT.start; fTime30.dispatchEvent(new Event('change'));
        }
    }
}

// バリデーション
document.getElementById('rsvForm').addEventListener('submit', e => {
    if (!fDate.value || !fStart.value || !fDuration.value) {
        e.preventDefault();
        alert('カレンダーから日付を選び、「初回30分」または「初回以降60分」のいずれかで時間を選択してください。');
    }
});
</script>
</body>
</html>
