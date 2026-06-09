<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../includes/crm_functions.php';
require_once __DIR__ . '/../includes/gcal_functions.php';
require_admin();

$nav        = 'dash';
$page_title = 'Googleカレンダー連携設定';
$msg = '';
$err = '';

// 認証情報保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_a'] ?? '') === 'save_creds') {
    $cid    = trim($_POST['client_id'] ?? '');
    $csec   = trim($_POST['client_secret'] ?? '');
    if (!$cid || !$csec) {
        $err = 'クライアントIDとシークレットを入力してください。';
    } else {
        file_put_contents(GCAL_CREDENTIALS_FILE, json_encode([
            'client_id'     => $cid,
            'client_secret' => $csec,
        ]));
        // トークンをリセット
        if (file_exists(GCAL_TOKEN_FILE)) unlink(GCAL_TOKEN_FILE);
        $msg = '認証情報を保存しました。次にGoogleアカウントで認証してください。';
    }
}

// 連携解除（認証情報は残してトークンのみ削除 → 再連携可能）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_a'] ?? '') === 'disconnect') {
    if (file_exists(GCAL_TOKEN_FILE)) unlink(GCAL_TOKEN_FILE);
    $msg = '連携を解除しました。「Googleアカウントで認証する」から再連携できます。';
}

$creds = file_exists(GCAL_CREDENTIALS_FILE) ? json_decode(file_get_contents(GCAL_CREDENTIALS_FILE), true) : [];
$has_token = file_exists(GCAL_TOKEN_FILE);
$is_configured = gcal_is_configured();

// 認証URLを生成
$auth_url = '';
if (!empty($creds['client_id']) && !empty($creds['client_secret'])) {
    $client = new Google\Client();
    $client->setClientId($creds['client_id']);
    $client->setClientSecret($creds['client_secret']);
    $client->setRedirectUri(GCAL_REDIRECT_URI);
    $client->addScope(Google\Service\Calendar::CALENDAR_EVENTS);
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    $auth_url = $client->createAuthUrl();
}
?>
<?php require __DIR__ . '/../includes/_header.php'; ?>

<div class="page-card" style="max-width:660px">
  <?php if ($msg): ?><div class="alert alert-success"><?= crm_h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= crm_h($err) ?></div><?php endif; ?>

  <!-- 現在の状態 -->
  <div class="mb-4 p-3 rounded" style="background:#f8f9fa;border:1px solid #dee2e6">
    <div class="fw-bold mb-2">連携状態</div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge <?= !empty($creds) ? 'bg-success' : 'bg-secondary' ?>">
        <?= !empty($creds) ? '認証情報: 設定済み' : '認証情報: 未設定' ?>
      </span>
      <span class="badge <?= $has_token ? 'bg-success' : 'bg-secondary' ?>">
        <?= $has_token ? 'Googleアカウント: 認証済み' : 'Googleアカウント: 未認証' ?>
      </span>
    </div>
    <?php if ($is_configured): ?>
    <div class="text-success small mt-1"><i class="bi bi-check-circle-fill me-1"></i>Googleカレンダー連携が有効です。期日の自動同期が動作します。</div>
    <?php else: ?>
    <div class="text-muted small mt-1">以下の手順で設定してください。</div>
    <?php endif; ?>
  </div>

  <!-- STEP 1: Google Cloud Console -->
  <h2 class="h6 fw-bold mb-2"><span class="badge rounded-pill text-bg-secondary me-2">STEP 1</span>Google Cloud Consoleで準備</h2>
  <ol class="small mb-3 ps-4">
    <li><a href="https://console.cloud.google.com" target="_blank">console.cloud.google.com</a> でプロジェクト作成</li>
    <li>「APIとサービス」→「ライブラリ」→「Google Calendar API」を有効化</li>
    <li>「認証情報」→「認証情報を作成」→「OAuthクライアントID」</li>
    <li>種類: <strong>ウェブアプリケーション</strong></li>
    <li>承認済みリダイレクトURIに以下を追加:<br>
      <code class="bg-light px-2 py-1 d-inline-block mt-1"><?= BASE_URL ?>/crm/gcal/callback.php</code>
    </li>
    <li>クライアントIDとクライアントシークレットをコピー</li>
  </ol>

  <hr>

  <!-- STEP 2: 認証情報入力 -->
  <h2 class="h6 fw-bold mb-2 mt-3"><span class="badge rounded-pill text-bg-secondary me-2">STEP 2</span>認証情報を入力</h2>
  <form method="post" class="mb-3">
    <input type="hidden" name="_a" value="save_creds">
    <div class="mb-2">
      <label class="form-label small">クライアントID</label>
      <input type="text" name="client_id" class="form-control form-control-sm"
             value="<?= crm_h($creds['client_id'] ?? '') ?>" placeholder="xxxxx.apps.googleusercontent.com" required>
    </div>
    <div class="mb-3">
      <label class="form-label small">クライアントシークレット</label>
      <input type="text" name="client_secret" class="form-control form-control-sm"
             value="<?= crm_h($creds['client_secret'] ?? '') ?>" placeholder="GOCSPX-..." required>
    </div>
    <button class="btn btn-sm text-white" style="background:var(--g)">認証情報を保存</button>
  </form>

  <hr>

  <!-- STEP 3: Google認証 -->
  <h2 class="h6 fw-bold mb-2 mt-3"><span class="badge rounded-pill text-bg-secondary me-2">STEP 3</span>Googleアカウントで認証</h2>
  <?php if ($auth_url): ?>
  <a href="<?= crm_h($auth_url) ?>" class="btn btn-sm btn-outline-primary">
    <i class="bi bi-google me-1"></i>Googleアカウントで認証する
  </a>
  <?php else: ?>
  <p class="text-muted small">STEP 2の認証情報を先に入力・保存してください。</p>
  <?php endif; ?>

  <?php if ($is_configured): ?>
  <hr>
  <form method="post" onsubmit="return confirm('連携を解除しますか？')">
    <input type="hidden" name="_a" value="disconnect">
    <button class="btn btn-sm btn-outline-danger">連携を解除する</button>
  </form>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/_footer.php'; ?>
