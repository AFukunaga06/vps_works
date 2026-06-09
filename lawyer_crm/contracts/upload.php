<?php
/**
 * 契約書アップロード画面
 *
 *  - 既存CRM認証セッションを継承（弁護士・スタッフのみ。ログイン必須）
 *  - 対応形式: PDF / Word(.docx) / テキスト(.txt)・10MB以内
 *  - lc_contracts に行を作成し、ファイルを contracts/uploads/{id}_{元名} に保存
 *  - 非同期で api_bridge.php を起動して解析開始（nohup + & で detach）
 *  - 完了後 view.php?id=X へリダイレクト（view.php 側でポーリング自動更新）
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$lc_user    = lc_require_login();
$tid        = _tid();
$page_title = '契約書アップロード';
$page_nav   = 'contracts';

const MAX_BYTES = 10 * 1024 * 1024;            // 10MB
$UPLOAD_DIR    = __DIR__ . '/uploads/';

// 対応拡張子 => 許容MIMEタイプ（MIMEは環境差があるため拡張子を主判定に使う）
$ALLOWED_EXTS = [
    'pdf'  => ['application/pdf'],
    'docx' => [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip', 'application/octet-stream',
    ],
    'txt'  => ['text/plain', 'application/octet-stream'],
];
$ACCEPT_ATTR = '.pdf,.docx,.txt,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain';

$CONTRACT_TYPES = [
    '業務委託' => '業務委託契約',
    '秘密保持' => '秘密保持契約（NDA）',
    '売買'     => '売買契約',
    'その他'   => 'その他',
];
$PARTY_SIDES = ['甲', '乙'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = (int)($_POST['client_id'] ?? 0);
    $ctype     = trim($_POST['contract_type'] ?? '');
    $side      = $_POST['party_side'] ?? '甲';
    $file      = $_FILES['document'] ?? null;

    $ext = $file ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';

    if (!isset($CONTRACT_TYPES[$ctype])) {
        $error = '契約類型を選択してください。';
    } elseif (!in_array($side, $PARTY_SIDES, true)) {
        $error = '立場（甲／乙）を選択してください。';
    } elseif (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'ファイルを選択してください。';
    } elseif ($file['size'] > MAX_BYTES) {
        $error = 'ファイルサイズは10MB以内にしてください。';
    } elseif (!isset($ALLOWED_EXTS[$ext])) {
        $error = 'PDF・Word(.docx)・テキスト(.txt) のいずれかをアップロードしてください。';
    }

    // 依頼者の所有テナント確認（指定時のみ）
    if (!$error && $client_id > 0) {
        $cv = get_db()->prepare("SELECT id FROM lc_clients WHERE id=? AND tenant_id=?");
        $cv->execute([$client_id, $tid]);
        if (!$cv->fetchColumn()) $error = '依頼者が見つかりません。';
    }

    if (!$error) {
        if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0755, true);

        $pdo = get_db();
        $cid = 0;
        $pdo->beginTransaction();
        try {
            // 1) 仮レコード（id 採番のため stored_name / file_path は後で UPDATE）
            $stmt = $pdo->prepare(
                "INSERT INTO lc_contracts
                  (tenant_id, client_id, uploaded_by, file_name, stored_name, file_path,
                   contract_type, party_side, status)
                 VALUES (?,?,?,?,?,?,?,?,'uploaded')"
            );
            $stmt->execute([
                $tid, $client_id ?: null, (int)$lc_user['id'],
                $file['name'], '__pending__', '__pending__', $ctype, $side,
            ]);
            $cid = (int)$pdo->lastInsertId();

            // 2) ファイル名サニタイズ（英数・日本語・基本記号のみ許容）
            $safe = preg_replace('/[^A-Za-z0-9._一-龥ぁ-んァ-ヴー\-]/u', '_', $file['name']);
            if ($safe === '' || mb_strlen($safe) > 200) $safe = 'contract.' . $ext;
            // 拡張子が落ちた場合の保険（解析側は拡張子で形式を判定するため必須）
            if (strtolower(pathinfo($safe, PATHINFO_EXTENSION)) !== $ext) {
                $safe .= '.' . $ext;
            }
            $stored = $cid . '_' . $safe;
            $dest   = $UPLOAD_DIR . $stored;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                throw new RuntimeException('ファイルの保存に失敗しました。');
            }

            $u = $pdo->prepare("UPDATE lc_contracts SET stored_name=?, file_path=? WHERE id=?");
            $u->execute([$stored, $dest, $cid]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = '登録に失敗しました: ' . $e->getMessage();
            error_log('[contracts/upload] ' . $e->getMessage());
        }

        if (!$error && $cid > 0) {
            // 3) 非同期で解析ブリッジを起動（PHP-FPM/Apache 子プロセスから detach）
            $logFile = '/tmp/lc_contracts_' . $cid . '.log';
            $cmd = sprintf(
                'nohup php %s %d < /dev/null > %s 2>&1 &',
                escapeshellarg(__DIR__ . '/api_bridge.php'),
                $cid,
                escapeshellarg($logFile)
            );
            @exec($cmd);

            header('Location: ' . LC_BASE_URL . '/contracts/view.php?id=' . $cid);
            exit;
        }
    }
}

// 依頼者ドロップダウン
$clients = get_db()->prepare(
    "SELECT id, name FROM lc_clients WHERE tenant_id=? ORDER BY name"
);
$clients->execute([$tid]);
$client_list = $clients->fetchAll();
?>
<?php require __DIR__ . '/../includes/_header.php'; ?>

<div class="page-card">
  <div class="mb-3">
    <a href="<?= LC_BASE_URL ?>/contracts/list.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-list-ul me-1"></i>契約書一覧へ
    </a>
  </div>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="alert alert-warning small mb-3">
    <i class="bi bi-shield-exclamation me-1"></i>
    アップロードした契約書はAIが一次分析し、結果を表示します。
    <strong>最終判断は必ず弁護士が行ってください。</strong>
  </div>

  <form method="post" enctype="multipart/form-data" class="row g-3">
    <div class="col-md-8">
      <label class="form-label">契約書ファイル <span class="text-danger">*</span></label>
      <input type="file" name="document" accept="<?= h($ACCEPT_ATTR) ?>" required
             class="form-control form-control-sm">
      <div class="form-text">PDF・Word(.docx)・テキスト(.txt) に対応。最大 10MB。<br>
        ※スキャナ画像のみのPDFや旧形式の .doc は本文を抽出できません。</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">依頼者</label>
      <select name="client_id" class="form-select form-select-sm">
        <option value="">（指定なし）</option>
        <?php foreach ($client_list as $c): ?>
          <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label">契約類型 <span class="text-danger">*</span></label>
      <select name="contract_type" class="form-select form-select-sm" required>
        <option value="">（選択してください）</option>
        <?php foreach ($CONTRACT_TYPES as $k => $v): ?>
          <option value="<?= h($k) ?>"><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label">自社の立場 <span class="text-danger">*</span></label>
      <div>
        <?php foreach ($PARTY_SIDES as $s): ?>
          <label class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="party_side"
                   value="<?= h($s) ?>" <?= $s === '甲' ? 'checked' : '' ?>>
            <?= h($s) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="col-12">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-upload me-1"></i>アップロードして分析
      </button>
      <a href="<?= LC_BASE_URL ?>/contracts/list.php"
         class="btn btn-outline-secondary ms-2">一覧に戻る</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../includes/_footer.php'; ?>
