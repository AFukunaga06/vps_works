<?php
/**
 * 契約書レビュー結果ビュー
 *
 *  ?id=X で契約書を取得（テナントスコープ）し、要約・リスク件数・条項テーブルを表示。
 *  - 「⚠️ AIによる一次分析。最終判断は弁護士が行う」警告は常時表示（仕様厳守）。
 *  - 条項ごとに [☑ 確認済み] と [弁護士メモ] を編集可能（POST で一括保存）。
 *  - 全条項が確認済みになると lc_contracts.status を 'reviewed' へ遷移。
 *  - status='uploaded' / 'analyzing' の間は 10s ポーリングで自動更新。
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$lc_user = lc_require_login();
$tid     = _tid();

$id = (int)($_GET['id'] ?? 0);

// 契約書本体（テナント確認込み）
$stmt = get_db()->prepare(
    "SELECT c.*, cl.name AS client_name
       FROM lc_contracts c
       LEFT JOIN lc_clients cl ON cl.id = c.client_id
      WHERE c.id = ? AND c.tenant_id = ?"
);
$stmt->execute([$id, $tid]);
$contract = $stmt->fetch();

if (!$contract) {
    http_response_code(404);
    exit('契約書が見つかりません。');
}

$page_title = '契約書レビュー結果';
$page_nav   = 'contracts';

// 確認・メモの一括保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_reviews'])) {
    $reviewed = $_POST['reviewed'] ?? [];
    $notes    = $_POST['note']     ?? [];

    $pdo = get_db();
    $u = $pdo->prepare(
        "UPDATE lc_contract_clauses
            SET reviewed_by_lawyer = ?, lawyer_note = ?
          WHERE id = ? AND contract_id = ? AND tenant_id = ?"
    );
    foreach ($notes as $clauseId => $note) {
        $clauseId = (int)$clauseId;
        if ($clauseId <= 0) continue;
        $rev = isset($reviewed[$clauseId]) ? 1 : 0;
        $u->execute([$rev, (string)$note, $clauseId, $id, $tid]);
    }

    // 全条項が確認済みなら contracts.status='reviewed'
    $remain = $pdo->prepare(
        "SELECT COUNT(*) FROM lc_contract_clauses
          WHERE contract_id=? AND tenant_id=? AND reviewed_by_lawyer=0"
    );
    $remain->execute([$id, $tid]);
    if ((int)$remain->fetchColumn() === 0 && $contract['status'] === 'done') {
        $pdo->prepare("UPDATE lc_contracts SET status='reviewed' WHERE id=? AND tenant_id=?")
            ->execute([$id, $tid]);
    }

    header('Location: ' . LC_BASE_URL . '/contracts/view.php?id=' . $id . '&saved=1');
    exit;
}

// 条項一覧
$cl = get_db()->prepare(
    "SELECT * FROM lc_contract_clauses
      WHERE contract_id = ? AND tenant_id = ?
      ORDER BY sort_order, id"
);
$cl->execute([$id, $tid]);
$clauses = $cl->fetchAll();

// --- 表示ヘルパ ---
function contract_risk_badge(string $level): string {
    $m = [
        '高'   => ['danger',  '🔴 高'],
        '中'   => ['warning', '🟡 中'],
        '低'   => ['success', '🟢 低'],
        'なし' => ['secondary', '— なし'],
    ];
    [$cls, $label] = $m[$level] ?? ['secondary', '—'];
    $extra = ($cls === 'warning') ? ' text-dark' : '';
    return '<span class="badge bg-' . $cls . $extra . '">' . h($label) . '</span>';
}

function contract_status_badge_view(string $s): string {
    $m = [
        'uploaded'  => ['secondary', 'アップロード済'],
        'analyzing' => ['info',      '分析中'],
        'done'      => ['primary',   '分析完了'],
        'reviewed'  => ['success',   '確認済み'],
        'error'     => ['danger',    'エラー'],
    ];
    [$cls, $label] = $m[$s] ?? ['secondary', $s];
    return '<span class="badge bg-' . $cls . '">' . h($label) . '</span>';
}
?>
<?php require __DIR__ . '/../includes/_header.php'; ?>

<?php $lc_analyzing = in_array($contract['status'], ['uploaded', 'analyzing'], true); ?>
<?php if ($lc_analyzing): ?>
<div id="lc-progress" class="card shadow"
     style="position:fixed; top:70px; right:24px; width:268px; z-index:1080; border-left:4px solid var(--lc-gold,#c8a94a);">
  <div class="card-body p-3">
    <div class="d-flex align-items-center mb-2">
      <div class="spinner-border spinner-border-sm text-primary me-2" id="lcp-spin" role="status"></div>
      <strong class="small" id="lcp-title">AI分析中…</strong>
    </div>
    <div class="progress" style="height:8px;">
      <div id="lcp-bar" class="progress-bar progress-bar-striped progress-bar-animated"
           role="progressbar" style="width:8%"></div>
    </div>
    <div class="small text-muted mt-1" id="lcp-text">準備中…</div>
  </div>
</div>
<script>
(function () {
  var id    = <?= (int)$id ?>;
  var base  = '<?= LC_BASE_URL ?>';
  var bar   = document.getElementById('lcp-bar');
  var txt   = document.getElementById('lcp-text');
  var title = document.getElementById('lcp-title');
  var spin  = document.getElementById('lcp-spin');
  var fails = 0;

  function finish(cls, label, sub) {
    bar.style.width = '100%';
    bar.classList.remove('progress-bar-animated', 'progress-bar-striped');
    bar.classList.add(cls);
    if (spin) spin.classList.add('d-none');
    title.textContent = label;
    txt.textContent = sub;
    setTimeout(function () { location.reload(); }, 800);
  }

  function poll() {
    fetch(base + '/contracts/status.php?id=' + id, { cache: 'no-store' })
      .then(function (r) { if (!r.ok) throw 0; return r.json(); })
      .then(function (d) {
        if (d.status === 'done' || d.status === 'reviewed') { finish('bg-success', '分析完了', '結果を表示します…'); return; }
        if (d.status === 'error') { finish('bg-danger', 'エラー', '解析に失敗しました'); return; }
        if (d.total > 0) {
          var pct = Math.min(100, Math.round(d.done / d.total * 100));
          if (pct < 8) pct = 8;
          bar.style.width = pct + '%';
          txt.textContent = '条項 ' + d.done + ' / ' + d.total + ' 件（' + pct + '%）';
        } else {
          txt.textContent = '本文を抽出しています…';
        }
        fails = 0;
        setTimeout(poll, 2000);
      })
      .catch(function () {
        if (++fails > 5) { location.reload(); return; }
        setTimeout(poll, 3000);
      });
  }
  poll();
})();
</script>
<?php endif; ?>

<?php if (!empty($_GET['saved'])): ?>
  <div class="alert alert-success">保存しました。</div>
<?php endif; ?>

<!-- 法令上の警告：常時表示（仕様厳守） -->
<div class="alert alert-warning mb-3">
  <i class="bi bi-shield-exclamation me-1"></i>
  <strong>⚠️ これはAIによる一次分析です。最終判断は必ず弁護士が行ってください。</strong>
  本ツールは弁護士の業務支援を目的としており、AI出力は下書きです。
</div>

<div class="page-card">
  <div class="row g-3">
    <div class="col-md-7">
      <h2 class="h6 mb-1"><i class="bi bi-file-earmark-pdf"></i> <?= h($contract['file_name']) ?></h2>
      <div class="small text-muted">
        依頼者: <?= h($contract['client_name'] ?? '未紐付け') ?>
        ／ 類型: <?= h($contract['contract_type'] ?? '不明') ?>
        ／ 立場: <?= h($contract['party_side']) ?>
        ／ アップロード: <?= h(fmt_datetime($contract['uploaded_at'])) ?>
        ／ ステータス: <?= contract_status_badge_view($contract['status']) ?>
      </div>
    </div>
    <div class="col-md-5 text-md-end">
      <?php if (in_array($contract['status'], ['done', 'reviewed'], true)): ?>
        <span class="badge bg-danger">🔴 高 <?= (int)$contract['total_risk_high']   ?></span>
        <span class="badge bg-warning text-dark">🟡 中 <?= (int)$contract['total_risk_medium'] ?></span>
        <span class="badge bg-success">🟢 低 <?= (int)$contract['total_risk_low']    ?></span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($contract['summary'])): ?>
    <div class="mt-3 p-3 bg-light rounded small">
      <strong>要約:</strong> <?= nl2br(h($contract['summary'])) ?>
    </div>
  <?php endif; ?>

  <?php if (in_array($contract['status'], ['uploaded', 'analyzing'], true)): ?>
    <div class="mt-3 alert alert-info mb-0">
      <i class="bi bi-arrow-repeat"></i>
      分析中です。完了まで通常 30 秒〜 2 分ほどかかります。
      <small class="text-muted">（このページは自動的に更新されます）</small>
    </div>
  <?php elseif ($contract['status'] === 'error'): ?>
    <div class="mt-3 alert alert-danger mb-0">
      <strong>解析エラー:</strong>
      <?= h($contract['error_message'] ?? '不明なエラーが発生しました。') ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($clauses): ?>
<form method="post" class="page-card">
  <h3 class="h6 mb-3">条項ごとのリスク分析</h3>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:9%">条項</th>
          <th style="width:10%">リスク</th>
          <th>問題点 / 修正提案</th>
          <th style="width:20%">弁護士メモ</th>
          <th style="width:7%" class="text-center">確認</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clauses as $c): ?>
        <tr>
          <td>
            <strong><?= h($c['clause_number'] ?: '—') ?></strong>
            <?php if ($c['clause_title']): ?>
              <br><small class="text-muted"><?= h($c['clause_title']) ?></small>
            <?php endif; ?>
          </td>
          <td>
            <?= contract_risk_badge($c['risk_level']) ?>
            <?php if ($c['risk_type']): ?>
              <br><small class="text-muted"><?= h($c['risk_type']) ?></small>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($c['issue']): ?>
              <div class="small"><strong>問題:</strong> <?= nl2br(h($c['issue'])) ?></div>
            <?php endif; ?>
            <?php if ($c['suggestion']): ?>
              <div class="small mt-1"><strong>提案:</strong> <?= nl2br(h($c['suggestion'])) ?></div>
            <?php endif; ?>
            <?php if ($c['related_law']): ?>
              <div class="small mt-1 text-muted">関連: <?= h($c['related_law']) ?></div>
            <?php endif; ?>
            <details class="mt-2 small">
              <summary class="text-muted">条文を表示</summary>
              <div class="mt-1 p-2 bg-light rounded" style="white-space:pre-wrap;"><?= h($c['clause_text']) ?></div>
            </details>
          </td>
          <td>
            <textarea name="note[<?= (int)$c['id'] ?>]" rows="3"
                      class="form-control form-control-sm"><?= h($c['lawyer_note'] ?? '') ?></textarea>
          </td>
          <td class="text-center">
            <input type="checkbox" name="reviewed[<?= (int)$c['id'] ?>]" value="1"
                   class="form-check-input" <?= $c['reviewed_by_lawyer'] ? 'checked' : '' ?>>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="mt-3 d-flex justify-content-between">
    <a href="<?= LC_BASE_URL ?>/contracts/list.php"
       class="btn btn-outline-secondary btn-sm">一覧に戻る</a>
    <button type="submit" name="save_reviews" value="1" class="btn btn-primary btn-sm">
      <i class="bi bi-save me-1"></i>確認・メモを保存
    </button>
  </div>
</form>
<?php endif; ?>

<?php require __DIR__ . '/../includes/_footer.php'; ?>
