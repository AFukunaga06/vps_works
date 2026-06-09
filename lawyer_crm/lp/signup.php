<?php
/**
 * 新規テナント登録（サインアップ）
 * 事務所情報＋代表者アカウントを作成し、14日間の無料トライアルを開始する。
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/verticals.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_signup'])) $_SESSION['csrf_signup'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_signup'];

$plan_qs   = strtolower($_GET['plan'] ?? 'standard');
$plan_specs = lc_plan_specs();
if (!isset($plan_specs[$plan_qs])) $plan_qs = 'standard';

$verticals = lc_all_verticals();
$vertical_qs = $_GET['vertical'] ?? 'lawyer';
if (!isset($verticals[$vertical_qs])) $vertical_qs = 'lawyer';

$errors = [];
$form = [
    'office_name'   => '',
    'contact_name'  => '',
    'email'         => '',
    'tel'           => '',
    'password'      => '',
    'password2'     => '',
    'plan'          => $plan_qs,
    'billing_cycle' => 'monthly',
    'vertical'      => $vertical_qs,
    'agree'         => '',
];

function he(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $errors[] = '不正なリクエストです。ページを再読込してください。';
    } else {
        foreach (array_keys($form) as $k) {
            $form[$k] = trim((string)($_POST[$k] ?? ''));
        }

        // --- バリデーション
        if ($form['office_name'] === '')   $errors[] = '事務所名を入力してください。';
        if ($form['contact_name'] === '')  $errors[] = '代表者氏名を入力してください。';
        if ($form['email'] === '')         $errors[] = 'メールアドレスを入力してください。';
        elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'メールアドレスの形式が正しくありません。';
        if (strlen($form['password']) < 8) $errors[] = 'パスワードは8文字以上で入力してください。';
        if ($form['password'] !== $form['password2']) $errors[] = 'パスワード（確認）が一致しません。';
        if (!isset($plan_specs[$form['plan']])) $errors[] = 'プランの選択が不正です。';
        if (!in_array($form['billing_cycle'], ['monthly','yearly'], true)) $errors[] = '支払サイクルが不正です。';
        if (!isset($verticals[$form['vertical']])) $errors[] = '業種の選択が不正です。';
        if ($form['agree'] !== '1') $errors[] = '利用規約・プライバシーポリシーへの同意が必要です。';

        // ハニーポット
        if (!empty($_POST['website'] ?? '')) $errors[] = '送信できませんでした。';

        // 既存ユーザー（同一メール）がいれば拒否（MVP方針）
        if (!$errors) {
            $stmt = get_db()->prepare("SELECT COUNT(*) FROM lc_users WHERE email=?");
            $stmt->execute([$form['email']]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'このメールアドレスは既に登録されています。ログインまたはパスワード再設定をご利用ください。';
            }
        }

        if (!$errors) {
            try {
                $pdo = get_db();
                $pdo->beginTransaction();

                // slug 生成（email + ランダム）
                $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', explode('@', $form['email'])[0]))
                      . '-' . substr(bin2hex(random_bytes(4)), 0, 6);

                $spec = $plan_specs[$form['plan']];
                $trial_ends = date('Y-m-d H:i:s', strtotime('+14 days'));

                // 1) テナント作成
                $stmt = $pdo->prepare("INSERT INTO lc_tenants
                    (name, vertical, slug, contact_email, contact_tel, contact_name,
                     plan, billing_cycle, status, trial_ends_at,
                     max_users, max_clients, max_cases, storage_quota_mb)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'trial', ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $form['office_name'], $form['vertical'], $slug, $form['email'], $form['tel'], $form['contact_name'],
                    $form['plan'], $form['billing_cycle'], $trial_ends,
                    $spec['max_users'], $spec['max_clients'], $spec['max_cases'], $spec['storage_mb'],
                ]);
                $tenant_id = (int)$pdo->lastInsertId();

                // 2) 代表者ユーザー作成（admin）
                $stmt = $pdo->prepare("INSERT INTO lc_users
                    (tenant_id, name, email, password_hash, role, is_active)
                    VALUES (?, ?, ?, ?, 'admin', 1)");
                $stmt->execute([
                    $tenant_id, $form['contact_name'], $form['email'],
                    password_hash($form['password'], PASSWORD_DEFAULT),
                ]);
                $user_id = (int)$pdo->lastInsertId();

                $pdo->commit();

                // 自動ログイン
                $_SESSION[LC_SESSION_KEY] = [
                    'id'        => $user_id,
                    'tenant_id' => $tenant_id,
                    'name'      => $form['contact_name'],
                    'email'     => $form['email'],
                    'role'      => 'admin',
                ];
                unset($_SESSION['csrf_signup']);

                // 通知メール（best effort）
                @mb_send_mail(
                    $form['email'],
                    '【LegalDesk】無料トライアル開始のお知らせ',
                    "{$form['contact_name']} 様\n\n"
                    . "LegalDeskへのご登録ありがとうございます。\n\n"
                    . "事務所名: {$form['office_name']}\n"
                    . "プラン: " . $spec['label'] . "（14日間無料トライアル中）\n"
                    . "トライアル終了日: " . date('Y年n月j日', strtotime($trial_ends)) . "\n\n"
                    . "ログインURL: " . LC_BASE_URL . "/login.php\n\n"
                    . "ご不明点はお問合せフォームよりお気軽にご連絡ください。\n",
                    "From: no-reply@legaldesk.example.com\r\n"
                );
                @mb_send_mail(
                    'afky5906@gmail.com',
                    '【LegalDesk】新規テナント登録',
                    "新規登録通知\n事務所名: {$form['office_name']}\n代表者: {$form['contact_name']}\nEmail: {$form['email']}\nプラン: {$form['plan']}/{$form['billing_cycle']}\n",
                    "From: no-reply@legaldesk.example.com\r\n"
                );

                header('Location: ' . LC_BASE_URL . '/index.php?welcome=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = '登録に失敗しました。時間をおいて再度お試しください。';
                error_log('[signup] ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>無料トライアル開始 - LegalDesk</title>
<meta name="description" content="LegalDeskの無料トライアルを開始。14日間、すべての機能をお試しいただけます。">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="_lp_styles.css">
<style>
.signup-card { max-width:560px; margin:0 auto; background:#fff; padding:2.5rem 2rem; border-radius:.75rem; box-shadow:0 2px 12px rgba(26,58,92,.08); }
.signup-card label { font-weight:600; color:var(--lc-navy); font-size:.92rem; }
.required { color:#dc3545; font-weight:700; margin-left:.25rem; font-size:.85rem; }
.plan-pill { display:inline-block; background:rgba(200,169,74,.15); color:var(--lc-navy);
  padding:.4rem 1rem; border-radius:2rem; font-weight:700; font-size:.9rem; }
.bullet { display:flex; gap:.6rem; align-items:flex-start; margin-bottom:.5rem; font-size:.9rem; }
.bullet i { color:#28a745; flex:0 0 auto; margin-top:.15rem; }
</style>
</head>
<body style="background:#f8f9fb;">

<nav class="navbar navbar-expand-md navbar-dark navbar-lp fixed-top">
  <div class="container">
    <a class="navbar-brand" href="index.html"><i class="bi bi-briefcase-fill me-2"></i>LegalDesk</a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <i class="bi bi-list fs-4"></i>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto align-items-md-center gap-md-2">
        <li class="nav-item"><a class="nav-link" href="index.html#features">機能</a></li>
        <li class="nav-item"><a class="nav-link" href="pricing.html">料金</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.php">お問合せ</a></li>
        <li class="nav-item ms-md-2"><a class="btn btn-sm btn-outline-gold" href="/lawyer_crm/login.php">ログイン</a></li>
      </ul>
    </div>
  </div>
</nav>

<section class="page-hero">
  <div class="container">
    <h1>14日間 無料トライアル</h1>
    <p>クレジットカード登録不要。今すぐ案件管理を始められます。</p>
  </div>
</section>

<section class="py-5">
  <div class="container">
    <div class="signup-card">
      <div class="text-center mb-4">
        <span class="plan-pill"><i class="bi bi-stars me-1"></i><?= he($plan_specs[$plan_qs]['label']) ?>プランで開始</span>
        <p class="small text-muted mt-2 mb-0">後からプラン変更も可能です</p>
      </div>

      <!-- 業種選択（vertical） -->
      <div class="mb-4">
        <label class="form-label fw-bold mb-2">ご利用業種を選んでください</label>
        <div class="row g-2">
          <?php foreach ($verticals as $key => $v): ?>
          <div class="col-6">
            <input type="radio" class="btn-check" name="vertical_pick" id="v_<?= he($key) ?>"
                   value="<?= he($key) ?>" <?= $form['vertical']===$key?'checked':'' ?>
                   onchange="document.getElementById('vertical_input').value=this.value">
            <label class="btn btn-outline-primary w-100 text-start" for="v_<?= he($key) ?>" style="font-size:.85rem;">
              <i class="bi bi-<?= he($v['icon']) ?> me-1"></i><?= he($v['label']) ?>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="row g-2 mb-4">
        <div class="col-12"><div class="bullet"><i class="bi bi-check-circle-fill"></i><div>14日間、すべての機能を無料で利用可能</div></div></div>
        <div class="col-12"><div class="bullet"><i class="bi bi-check-circle-fill"></i><div>クレジットカード登録は不要、トライアル終了時に課金されることはありません</div></div></div>
        <div class="col-12"><div class="bullet"><i class="bi bi-check-circle-fill"></i><div>1分で登録完了、すぐに事務所運営を始められます</div></div></div>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0 small">
            <?php foreach ($errors as $e) echo '<li>' . he($e) . '</li>'; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= he($csrf) ?>">
        <input type="hidden" name="vertical" id="vertical_input" value="<?= he($form['vertical']) ?>">
        <input type="text" name="website" tabindex="-1" autocomplete="off"
               style="position:absolute;left:-9999px;" aria-hidden="true">

        <div class="mb-3">
          <label>事務所名<span class="required">*</span></label>
          <input type="text" name="office_name" class="form-control" maxlength="150" required
                 value="<?= he($form['office_name']) ?>" placeholder="○○法律事務所">
        </div>

        <div class="mb-3">
          <label>代表者氏名<span class="required">*</span></label>
          <input type="text" name="contact_name" class="form-control" maxlength="100" required
                 value="<?= he($form['contact_name']) ?>" placeholder="山田 太郎">
        </div>

        <div class="row g-3">
          <div class="col-md-7">
            <label>メールアドレス<span class="required">*</span></label>
            <input type="email" name="email" class="form-control" maxlength="255" required
                   value="<?= he($form['email']) ?>" placeholder="example@example.com">
          </div>
          <div class="col-md-5">
            <label>電話番号</label>
            <input type="tel" name="tel" class="form-control" maxlength="30"
                   value="<?= he($form['tel']) ?>" placeholder="03-0000-0000">
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label>パスワード<span class="required">*</span></label>
            <input type="password" name="password" class="form-control" minlength="8" required
                   placeholder="8文字以上" autocomplete="new-password">
          </div>
          <div class="col-md-6">
            <label>パスワード（確認）<span class="required">*</span></label>
            <input type="password" name="password2" class="form-control" minlength="8" required
                   placeholder="同じパスワードを再入力" autocomplete="new-password">
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label>プラン<span class="required">*</span></label>
            <select name="plan" class="form-select" required>
              <?php foreach ($plan_specs as $key => $spec):
                if ($key === 'enterprise') continue; ?>
                <option value="<?= he($key) ?>" <?= $form['plan']===$key?'selected':'' ?>>
                  <?= he($spec['label']) ?>
                  <?= $spec['price_monthly'] ? '（月額' . number_format($spec['price_monthly']) . '円）' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label>支払サイクル<span class="required">*</span></label>
            <select name="billing_cycle" class="form-select" required>
              <option value="monthly" <?= $form['billing_cycle']==='monthly'?'selected':'' ?>>月払い</option>
              <option value="yearly"  <?= $form['billing_cycle']==='yearly'?'selected':'' ?>>年払い（2か月分お得）</option>
            </select>
          </div>
        </div>

        <div class="form-check my-4">
          <input type="checkbox" name="agree" value="1" id="agree" class="form-check-input" required
                 <?= $form['agree']==='1'?'checked':'' ?>>
          <label class="form-check-label small" for="agree">
            <a href="terms.html" target="_blank">利用規約</a> および
            <a href="privacy.html" target="_blank">プライバシーポリシー</a>
            に同意します<span class="required">*</span>
          </label>
        </div>

        <div class="d-grid">
          <button class="btn btn-gold btn-lg">
            <i class="bi bi-rocket-takeoff me-1"></i>14日間 無料で始める
          </button>
        </div>

        <p class="small text-muted text-center mt-3 mb-0">
          すでにアカウントをお持ちの方は <a href="<?= LC_BASE_URL ?>/login.php">こちらからログイン</a>
        </p>
      </form>
    </div>
  </div>
</section>

<footer>
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <h6><i class="bi bi-briefcase-fill me-1" style="color:var(--lc-gold);"></i>LegalDesk</h6>
        <p class="small mb-0">個人・小規模事務所のための弁護士CRM。</p>
      </div>
      <div class="col-6 col-md-2">
        <h6>サービス</h6>
        <a href="index.html#features">機能</a>
        <a href="pricing.html">料金プラン</a>
        <a href="index.html#faq">FAQ</a>
      </div>
      <div class="col-6 col-md-2">
        <h6>サポート</h6>
        <a href="contact.php">お問合せ</a>
        <a href="signup.php">無料トライアル</a>
        <a href="/lawyer_crm/login.php">ログイン</a>
      </div>
      <div class="col-md-4">
        <h6>規約・運営</h6>
        <a href="terms.html">利用規約</a>
        <a href="privacy.html">プライバシーポリシー</a>
        <a href="tokushoho.html">特定商取引法に基づく表記</a>
      </div>
    </div>
    <div class="footer-bottom">&copy; 2026 LegalDesk. All rights reserved.</div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
