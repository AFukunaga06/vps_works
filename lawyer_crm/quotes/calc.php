<?php
/**
 * 見積算出（新規／編集）
 *
 *   - ?id=X で既存見積の編集モード
 *   - GET で入力値があれば内訳をリアルタイムで再計算して表示
 *   - POST 'save' で lc_quotes に INSERT または UPDATE → view.php?id=X へ
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quote_calc.php';

$lc_user    = lc_require_login();
$tid        = _tid();
$page_title = '見積算出';
$page_nav   = 'quotes';

$id = (int)($_GET['id'] ?? 0);
$existing = null;
if ($id > 0) {
    $stmt = get_db()->prepare("SELECT * FROM lc_quotes WHERE id=? AND tenant_id=?");
    $stmt->execute([$id, $tid]);
    $existing = $stmt->fetch();
    if (!$existing) { http_response_code(404); exit('見積が見つかりません。'); }
    $page_title = '見積編集 / ' . ($existing['quote_number'] ?: ('#' . $id));
}

// --- 入力値の解決（POST > GET > 既存 > デフォルト） ---
$src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
function _v($src, $existing, $key, $default = '') {
    if (isset($src[$key]) && $src[$key] !== '') return $src[$key];
    if ($existing && isset($existing[$key])) return $existing[$key];
    return $default;
}

$benefit    = (int)preg_replace('/[^0-9]/', '', (string)_v($src, $existing, 'economic_benefit_yen', 0));
$consult    = (int)preg_replace('/[^0-9]/', '', (string)_v($src, $existing, 'consultation_yen', 5500));
$expense    = (int)preg_replace('/[^0-9]/', '', (string)_v($src, $existing, 'expense_yen', 0));
$title      = (string)_v($src, $existing, 'title', '');
$case_type  = (string)_v($src, $existing, 'case_type', '');
$client_id  = (int)_v($src, $existing, 'client_id', 0);
$case_id    = (int)_v($src, $existing, 'case_id', 0);
$notes      = (string)_v($src, $existing, 'notes', '');
$valid_until = (string)_v($src, $existing, 'valid_until', '');
$status     = (string)_v($src, $existing, 'status', 'draft');

$breakdown = lc_calc_quote($benefit, $consult, $expense);

$error = '';

// --- 保存処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if ($client_id > 0) {
        $cv = get_db()->prepare("SELECT id FROM lc_clients WHERE id=? AND tenant_id=?");
        $cv->execute([$client_id, $tid]);
        if (!$cv->fetchColumn()) $error = '依頼者が見つかりません。';
    }
    if (!$error && $case_id > 0) {
        $cv = get_db()->prepare("SELECT id FROM lc_cases WHERE id=? AND tenant_id=?");
        $cv->execute([$case_id, $tid]);
        if (!$cv->fetchColumn()) $error = '案件が見つかりません。';
    }
    if (!$error && $benefit < 0) $error = '経済的利益は0以上で入力してください。';

    if (!$error) {
        $pdo = get_db();
        $fields = [
            'tenant_id'              => $tid,
            'client_id'              => $client_id ?: null,
            'case_id'                => $case_id   ?: null,
            'created_by'             => (int)$lc_user['id'],
            'title'                  => $title,
            'case_type'              => $case_type ?: null,
            'economic_benefit_yen'   => $benefit,
            'retainer_yen'           => $breakdown['retainer_yen'],
            'retainer_rate_text'     => $breakdown['retainer_rate_text'],
            'success_yen'            => $breakdown['success_yen'],
            'success_rate_text'      => $breakdown['success_rate_text'],
            'consultation_yen'       => $breakdown['consultation_yen'],
            'expense_yen'            => $breakdown['expense_yen'],
            'subtotal_yen'           => $breakdown['subtotal_yen'],
            'tax_yen'                => $breakdown['tax_yen'],
            'total_yen'              => $breakdown['total_yen'],
            'notes'                  => $notes,
            'valid_until'            => $valid_until ?: null,
            'status'                 => in_array($status, ['draft','sent','accepted','rejected','canceled'], true) ? $status : 'draft',
        ];

        if ($id > 0) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
            $stmt = $pdo->prepare("UPDATE lc_quotes SET $set WHERE id=:id AND tenant_id=:tid");
            $stmt->execute(array_merge($fields, ['id' => $id, 'tid' => $tid]));
            $newId = $id;
        } else {
            $cols  = implode(', ', array_keys($fields));
            $place = implode(', ', array_map(fn($k) => ":$k", array_keys($fields)));
            $stmt = $pdo->prepare("INSERT INTO lc_quotes ($cols) VALUES ($place)");
            $stmt->execute($fields);
            $newId = (int)$pdo->lastInsertId();
            // 見積番号を自動採番（Q-YYYYMMDD-XXXX）
            $pdo->prepare("UPDATE lc_quotes SET quote_number=? WHERE id=?")
                ->execute([lc_quote_number($newId), $newId]);
        }
        header('Location: ' . LC_BASE_URL . '/quotes/view.php?id=' . $newId);
        exit;
    }
}

// 依頼者・案件ドロップダウン
$clients = get_db()->prepare("SELECT id, name FROM lc_clients WHERE tenant_id=? ORDER BY name");
$clients->execute([$tid]);
$client_list = $clients->fetchAll();

$cases_q = get_db()->prepare(
    "SELECT id, case_name, client_id FROM lc_cases WHERE tenant_id=? ORDER BY id DESC LIMIT 200"
);
$cases_q->execute([$tid]);
$case_list = $cases_q->fetchAll();

$CASE_TYPES = array_keys(CASE_TYPE_MAP);
?>
<?php require __DIR__ . '/../includes/_header.php'; ?>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" class="row g-3">
  <!-- 左：入力 -->
  <div class="col-md-6">
    <div class="page-card">
      <h2 class="h6 mb-3"><i class="bi bi-pencil-square"></i> 計算条件</h2>

      <div class="mb-3">
        <label class="form-label">件名</label>
        <input type="text" name="title" class="form-control form-control-sm"
               value="<?= h($title) ?>" placeholder="例: ◯◯損害賠償請求事件 見積">
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-6">
          <label class="form-label">依頼者</label>
          <select name="client_id" class="form-select form-select-sm">
            <option value="">（指定なし）</option>
            <?php foreach ($client_list as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $client_id===(int)$c['id']?'selected':'' ?>>
                <?= h($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">案件種別</label>
          <select name="case_type" class="form-select form-select-sm">
            <option value="">（指定なし）</option>
            <?php foreach (CASE_TYPE_MAP as $k => $v): ?>
              <option value="<?= h($k) ?>" <?= $case_type===$k?'selected':'' ?>><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">経済的利益（請求額・想定回収額）</label>
        <div class="input-group input-group-sm">
          <input type="number" name="economic_benefit_yen" class="form-control"
                 value="<?= $benefit ?>" min="0" step="10000">
          <span class="input-group-text">円</span>
        </div>
        <div class="form-text">この金額をもとに着手金・報酬金を旧日弁連基準で算出します。</div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-6">
          <label class="form-label">相談料</label>
          <div class="input-group input-group-sm">
            <input type="number" name="consultation_yen" class="form-control"
                   value="<?= $consult ?>" min="0" step="100">
            <span class="input-group-text">円</span>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">実費（印紙・郵券等）</label>
          <div class="input-group input-group-sm">
            <input type="number" name="expense_yen" class="form-control"
                   value="<?= $expense ?>" min="0" step="100">
            <span class="input-group-text">円</span>
          </div>
        </div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-6">
          <label class="form-label">有効期限</label>
          <input type="date" name="valid_until" class="form-control form-control-sm"
                 value="<?= h($valid_until) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">ステータス</label>
          <select name="status" class="form-select form-select-sm">
            <?php foreach (['draft','sent','accepted','rejected','canceled'] as $s): ?>
              <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>>
                <?= h(lc_quote_status_label($s)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">備考</label>
        <textarea name="notes" class="form-control form-control-sm" rows="3"
                  placeholder="特約事項・追加説明など"><?= h($notes) ?></textarea>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-arrow-repeat me-1"></i>再計算
        </button>
        <button type="submit" name="save" value="1" class="btn btn-primary btn-sm">
          <i class="bi bi-save me-1"></i><?= $id > 0 ? '更新して保存' : '見積として保存' ?>
        </button>
        <a href="<?= LC_BASE_URL ?>/quotes/index.php" class="btn btn-outline-secondary btn-sm ms-auto">一覧へ</a>
      </div>
    </div>
  </div>

  <!-- 右：計算結果 -->
  <div class="col-md-6">
    <div class="page-card">
      <h2 class="h6 mb-3"><i class="bi bi-calculator"></i> 計算結果（旧日弁連基準）</h2>

      <div class="alert alert-info small">
        本計算は2004年廃止の旧日弁連報酬基準を参考にした目安値です。
        実際の料金は事務所方針に応じて調整してください。
      </div>

      <table class="table table-sm">
        <tbody>
          <tr>
            <th style="width:55%">経済的利益</th>
            <td class="text-end fw-bold"><?= fmt_money($benefit) ?></td>
          </tr>
          <tr>
            <th>着手金 <small class="text-muted">(<?= h($breakdown['retainer_rate_text']) ?>)</small></th>
            <td class="text-end"><?= fmt_money($breakdown['retainer_yen']) ?></td>
          </tr>
          <tr>
            <th>報酬金 見込 <small class="text-muted">(<?= h($breakdown['success_rate_text']) ?>)</small></th>
            <td class="text-end"><?= fmt_money($breakdown['success_yen']) ?></td>
          </tr>
          <tr>
            <th>相談料</th>
            <td class="text-end"><?= fmt_money($breakdown['consultation_yen']) ?></td>
          </tr>
          <tr>
            <th>実費</th>
            <td class="text-end"><?= fmt_money($breakdown['expense_yen']) ?></td>
          </tr>
          <tr class="table-light">
            <th>小計（税抜）</th>
            <td class="text-end"><?= fmt_money($breakdown['subtotal_yen']) ?></td>
          </tr>
          <tr>
            <th>消費税（10%）</th>
            <td class="text-end"><?= fmt_money($breakdown['tax_yen']) ?></td>
          </tr>
          <tr class="table-primary">
            <th class="fs-6">合計（税込）</th>
            <td class="text-end fs-5 fw-bold"><?= fmt_money($breakdown['total_yen']) ?></td>
          </tr>
        </tbody>
      </table>

      <div class="small text-muted mt-2">
        ※ 報酬金は実際に得られた成果額により変動します。
        上記は経済的利益額をそのまま回収できた場合の見込です。
      </div>
    </div>
  </div>
</form>

<?php require __DIR__ . '/../includes/_footer.php'; ?>
