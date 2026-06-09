<?php
/**
 * 見積書 印刷向けビュー（ブラウザ印刷でPDF化可）
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quote_calc.php';

$lc_user = lc_require_login();
$tid     = _tid();

$id = (int)($_GET['id'] ?? 0);
$stmt = get_db()->prepare(
    "SELECT q.*, cl.name AS client_name, cl.address AS client_address,
            u.name AS creator_name, t.name AS tenant_name
       FROM lc_quotes q
       LEFT JOIN lc_clients cl ON cl.id = q.client_id
       LEFT JOIN lc_users   u  ON u.id  = q.created_by
       LEFT JOIN lc_tenants t  ON t.id  = q.tenant_id
      WHERE q.id = ? AND q.tenant_id = ?"
);
$stmt->execute([$id, $tid]);
$q = $stmt->fetch();
if (!$q) { http_response_code(404); exit('見積が見つかりません。'); }

$page_title = '見積書 / ' . ($q['quote_number'] ?: ('#' . $id));
$page_nav   = 'quotes';
?>
<?php require __DIR__ . '/../includes/_header.php'; ?>

<style>
  .quote-paper { background: #fff; padding: 2.5rem; max-width: 760px; margin: 0 auto; }
  .quote-paper h1 { font-size: 1.7rem; letter-spacing: .3em; border-bottom: 2px solid #1a3a5c; padding-bottom: .5rem; }
  .quote-paper .meta { font-size: .9rem; color: #555; }
  .quote-paper .totals th { background: #f8f9fa; }
  .quote-paper .grand { font-size: 1.3rem; background: #1a3a5c; color: #fff; }
  .no-print { }
  @media print {
    .no-print, nav, .sidebar, .navbar { display: none !important; }
    body, .page-card { background: #fff !important; }
    .quote-paper { box-shadow: none; padding: 0; }
    @page { margin: 1.5cm; }
  }
</style>

<div class="d-flex justify-content-between mb-2 no-print">
  <a href="<?= LC_BASE_URL ?>/quotes/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i> 一覧
  </a>
  <div>
    <a href="<?= LC_BASE_URL ?>/quotes/calc.php?id=<?= (int)$q['id'] ?>"
       class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>編集</a>
    <button onclick="window.print()" class="btn btn-sm btn-primary">
      <i class="bi bi-printer me-1"></i>印刷 / PDF保存
    </button>
    <form action="<?= LC_BASE_URL ?>/quotes/delete.php" method="post" class="d-inline"
          onsubmit="return confirm('この見積を削除します。よろしいですか？');">
      <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>削除</button>
    </form>
  </div>
</div>

<div class="quote-paper page-card">
  <h1 class="text-center">御　見　積　書</h1>

  <div class="d-flex justify-content-between mt-4 meta">
    <div>
      <?php if ($q['client_name']): ?>
        <div class="fs-5"><?= h($q['client_name']) ?> 様</div>
        <div><?= h($q['client_address'] ?? '') ?></div>
      <?php else: ?>
        <div class="fs-5 text-muted">（依頼者未指定）</div>
      <?php endif; ?>
    </div>
    <div class="text-end">
      <div>見積番号: <?= h($q['quote_number'] ?: lc_quote_number((int)$q['id'], $q['created_at'])) ?></div>
      <div>発行日: <?= h(fmt_date($q['created_at'])) ?></div>
      <?php if ($q['valid_until']): ?>
        <div>有効期限: <?= h(fmt_date($q['valid_until'])) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($q['title']): ?>
    <div class="mt-4">
      <div class="text-muted small">件名</div>
      <div class="fs-5"><?= h($q['title']) ?></div>
    </div>
  <?php endif; ?>

  <table class="table totals mt-4">
    <thead>
      <tr>
        <th>項目</th>
        <th>内訳</th>
        <th class="text-end" style="width:25%">金額</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <th>経済的利益（基礎額）</th>
        <td class="text-muted small">着手金・報酬金の算定基礎</td>
        <td class="text-end"><?= fmt_money((int)$q['economic_benefit_yen']) ?></td>
      </tr>
      <tr>
        <th>着手金</th>
        <td><?= h($q['retainer_rate_text']) ?></td>
        <td class="text-end"><?= fmt_money((int)$q['retainer_yen']) ?></td>
      </tr>
      <tr>
        <th>報酬金 見込</th>
        <td><?= h($q['success_rate_text']) ?>（実際の成果額により変動）</td>
        <td class="text-end"><?= fmt_money((int)$q['success_yen']) ?></td>
      </tr>
      <tr>
        <th>相談料</th>
        <td>初回相談・打合せ料</td>
        <td class="text-end"><?= fmt_money((int)$q['consultation_yen']) ?></td>
      </tr>
      <tr>
        <th>実費</th>
        <td>印紙・郵券・交通費等</td>
        <td class="text-end"><?= fmt_money((int)$q['expense_yen']) ?></td>
      </tr>
      <tr>
        <th>小計（税抜）</th>
        <td></td>
        <td class="text-end fw-bold"><?= fmt_money((int)$q['subtotal_yen']) ?></td>
      </tr>
      <tr>
        <th>消費税（10%）</th>
        <td></td>
        <td class="text-end"><?= fmt_money((int)$q['tax_yen']) ?></td>
      </tr>
      <tr class="grand">
        <th colspan="2" class="text-end">合計（税込）</th>
        <th class="text-end"><?= fmt_money((int)$q['total_yen']) ?></th>
      </tr>
    </tbody>
  </table>

  <?php if (!empty($q['notes'])): ?>
    <div class="mt-3">
      <div class="text-muted small">備考</div>
      <div class="border rounded p-2 small" style="white-space:pre-wrap;"><?= h($q['notes']) ?></div>
    </div>
  <?php endif; ?>

  <div class="mt-5 text-end small">
    <div>発行: <?= h($q['tenant_name'] ?? '') ?></div>
    <?php if ($q['creator_name']): ?>
      <div>担当: <?= h($q['creator_name']) ?></div>
    <?php endif; ?>
    <div class="mt-3 text-muted">
      ※ 本見積は旧日弁連報酬基準を参考にした目安額です。最終金額は別途協議のうえ決定します。
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/_footer.php'; ?>
