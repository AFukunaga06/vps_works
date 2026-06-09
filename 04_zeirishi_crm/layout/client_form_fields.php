<?php $f = $f ?? []; ?>
<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">会社名・屋号 <span class="text-danger">*</span></label>
    <input type="text" name="company_name" class="form-control" required value="<?= h((string)($f['company_name'] ?? '')) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label">会社名カナ</label>
    <input type="text" name="company_kana" class="form-control" value="<?= h((string)($f['company_kana'] ?? '')) ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">代表者名</label>
    <input type="text" name="representative" class="form-control" value="<?= h((string)($f['representative'] ?? '')) ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">法人/個人</label>
    <select name="corp_type" class="form-select">
      <option value="法人" <?= ($f['corp_type']??'法人')==='法人'?'selected':'' ?>>法人</option>
      <option value="個人" <?= ($f['corp_type']??'')==='個人'?'selected':'' ?>>個人</option>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">決算月</label>
    <select name="fiscal_month" class="form-select">
      <?php for ($m=1;$m<=12;$m++): ?>
      <option value="<?= $m ?>" <?= ((int)($f['fiscal_month']??3))===$m?'selected':'' ?>><?= $m ?>月</option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">郵便番号</label>
    <input type="text" name="zip_code" class="form-control" value="<?= h((string)($f['zip_code'] ?? '')) ?>">
  </div>
  <div class="col-md-8">
    <label class="form-label">住所</label>
    <input type="text" name="address" class="form-control" value="<?= h((string)($f['address'] ?? '')) ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">電話番号</label>
    <input type="text" name="phone" class="form-control" value="<?= h((string)($f['phone'] ?? '')) ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">メールアドレス</label>
    <input type="email" name="email" class="form-control" value="<?= h((string)($f['email'] ?? '')) ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">業種</label>
    <input type="text" name="industry" class="form-control" value="<?= h((string)($f['industry'] ?? '')) ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">顧問契約開始日</label>
    <input type="date" name="contract_start" class="form-control" value="<?= h((string)($f['contract_start'] ?? '')) ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">月次顧問料（円）</label>
    <input type="number" name="monthly_fee" class="form-control" min="0" step="1000" value="<?= h((string)($f['monthly_fee'] ?? 0)) ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">状態</label>
    <select name="status" class="form-select">
      <option value="active" <?= ($f['status']??'active')==='active'?'selected':'' ?>>稼働</option>
      <option value="inactive" <?= ($f['status']??'')==='inactive'?'selected':'' ?>>停止</option>
    </select>
  </div>
  <div class="col-12">
    <label class="form-label">備考</label>
    <textarea name="notes" class="form-control" rows="2"><?= h((string)($f['notes'] ?? '')) ?></textarea>
  </div>
</div>
