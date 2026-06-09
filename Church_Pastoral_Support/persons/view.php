<?php
require_once dirname(__DIR__).'/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: '.BASE_URL.'/persons/'); exit; }

$person = $pdo->prepare("SELECT p.*, u.display_name as assigned_name FROM persons p LEFT JOIN users u ON p.assigned_user_id=u.id WHERE p.id=?");
$person->execute([$id]);
$person = $person->fetch();
if (!$person) { header('Location: '.BASE_URL.'/persons/'); exit; }

// Officers can only see assigned persons
if (($_SESSION['user_role']??'')==='officer' && $person['assigned_user_id']!==$_SESSION['user_id']) {
    die('アクセス権限がありません。');
}

// Follow histories
$fh_query = "SELECT f.*, u.display_name as recorder FROM follow_histories f LEFT JOIN users u ON f.recorded_by=u.id WHERE f.person_id=?";
if (!can_view_confidential()) $fh_query .= " AND f.private_flag=0";
$fh_query .= " ORDER BY f.follow_date DESC";
$follows = $pdo->prepare($fh_query);
$follows->execute([$id]);
$follows = $follows->fetchAll();

// Attendance
$attendance = $pdo->prepare("SELECT a.*, mt.name as meeting_name FROM attendance a JOIN meeting_types mt ON a.meeting_type_id=mt.id WHERE a.person_id=? ORDER BY a.attended_date DESC LIMIT 20");
$attendance->execute([$id]);
$attendance = $attendance->fetchAll();

// Tasks
$tasks = $pdo->prepare("SELECT t.*, u.display_name as assigned_name FROM tasks t LEFT JOIN users u ON t.assigned_user_id=u.id WHERE t.person_id=? AND t.status NOT IN ('completed','cancelled') ORDER BY t.due_date ASC");
$tasks->execute([$id]);
$tasks = $tasks->fetchAll();

// Groups
$groups = $pdo->prepare("SELECT gm.*, g.name as group_name FROM group_members gm JOIN groups_tbl g ON gm.group_id=g.id WHERE gm.person_id=? AND gm.left_date IS NULL");
$groups->execute([$id]);
$groups = $groups->fetchAll();

include dirname(__DIR__).'/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">
    <i class="bi bi-person-circle"></i>
    <?= h($person['last_name'].$person['first_name']) ?>
    <?= status_badge($person['status']) ?>
    <?php if($person['alert_flag']): ?><span class="badge bg-danger"><i class="bi bi-exclamation-circle"></i> 要注意</span><?php endif; ?>
  </h4>
  <div class="d-flex gap-2">
    <?php if(can_edit_persons()): ?>
    <a href="<?= BASE_URL ?>/persons/form.php?id=<?= $id ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> 編集</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/persons/" class="btn btn-outline-secondary btn-sm">一覧</a>
  </div>
</div>

<div class="row g-3">
<div class="col-md-4">
  <div class="card mb-3">
    <div class="card-header fw-bold"><i class="bi bi-person"></i> 基本情報</div>
    <div class="card-body small">
      <table class="table table-sm table-borderless mb-0">
        <tr><th width="40%">氏名（かな）</th><td><?= h($person['last_name_kana'].$person['first_name_kana']) ?></td></tr>
        <tr><th>性別</th><td><?= ['male'=>'男性','female'=>'女性','other'=>'その他'][$person['gender']??''] ?? '-' ?></td></tr>
        <tr><th>生年月日</th><td><?= h($person['birth_date'] ?? '-') ?></td></tr>
        <tr><th>電話</th><td><?= h($person['phone'] ?? '-') ?></td></tr>
        <tr><th>メール</th><td><?= h($person['email'] ?? '-') ?></td></tr>
        <tr><th>住所</th><td><?= h($person['address'] ?? '-') ?></td></tr>
        <tr><th>初来会日</th><td><?= h($person['first_visit_date'] ?? '-') ?></td></tr>
        <tr><th>最終出席</th><td><?= h($person['last_attend_date'] ?? '-') ?></td></tr>
        <tr><th>担当者</th><td><?= h($person['assigned_name'] ?? '-') ?></td></tr>
        <tr><th>個人情報同意</th><td><?= $person['consent_personal_info']?'✓':'－' ?></td></tr>
        <tr><th>写真掲載</th><td><?= $person['photo_permission']?'✓':'－' ?></td></tr>
        <tr><th>名簿掲載</th><td><?= $person['directory_permission']?'✓':'－' ?></td></tr>
      </table>
    </div>
  </div>

  <?php if($person['alert_flag'] && (can_view_confidential() || ($person['assigned_user_id']===$_SESSION['user_id']))): ?>
  <div class="card mb-3 border-danger">
    <div class="card-header fw-bold text-danger"><i class="bi bi-exclamation-circle"></i> 要注意メモ</div>
    <div class="card-body small"><?= nl2br(h($person['alert_note'])) ?></div>
  </div>
  <?php endif; ?>

  <?php if(can_view_confidential()): ?>
  <div class="card mb-3 border-danger">
    <div class="card-header fw-bold text-danger"><i class="bi bi-lock"></i> 牧会情報</div>
    <div class="card-body small">
      <p class="fw-bold mb-1">牧会メモ</p>
      <p class="mb-2"><?= $person['pastoral_memo'] ? nl2br(h($person['pastoral_memo'])) : '<span class="text-muted">なし</span>' ?></p>
      <p class="fw-bold mb-1">祈りの課題</p>
      <p class="mb-2"><?= $person['prayer_requests'] ? nl2br(h($person['prayer_requests'])) : '<span class="text-muted">なし</span>' ?></p>
      <p class="fw-bold mb-1">配慮事項</p>
      <p class="mb-0"><?= $person['care_notes'] ? nl2br(h($person['care_notes'])) : '<span class="text-muted">なし</span>' ?></p>
    </div>
  </div>
  <?php endif; ?>

  <?php if(!empty($groups)): ?>
  <div class="card mb-3">
    <div class="card-header fw-bold"><i class="bi bi-diagram-3"></i> グループ所属</div>
    <div class="card-body small">
      <?php foreach($groups as $g): ?>
      <span class="badge bg-secondary me-1"><?= h($g['group_name']) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="col-md-8">
  <!-- Follow histories -->
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center fw-bold">
      <span><i class="bi bi-telephone-forward"></i> フォロー履歴</span>
      <a href="<?= BASE_URL ?>/follows/add.php?person_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">追加</a>
    </div>
    <div class="card-body p-0">
      <?php if(empty($follows)): ?>
      <p class="text-muted p-3 mb-0 small">フォロー履歴はありません</p>
      <?php else: ?>
      <table class="table table-sm mb-0">
        <thead><tr><th>日付</th><th>種別</th><th>内容</th><th>次回予定</th><th>記録者</th></tr></thead>
        <tbody>
        <?php foreach($follows as $f): ?>
        <tr>
          <td class="small"><?= h($f['follow_date']) ?></td>
          <td class="small"><?= follow_type_label($f['follow_type']) ?><?= $f['private_flag']?' <i class="bi bi-lock-fill text-danger small"></i>':'' ?></td>
          <td class="small"><?= h(mb_strimwidth($f['content']??'',0,50,'…')) ?></td>
          <td class="small"><?= $f['next_plan_date']?h($f['next_plan_date']).': '.h(mb_strimwidth($f['next_plan']??'',0,20,'…')):'-' ?></td>
          <td class="small"><?= h($f['recorder']??'-') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tasks -->
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center fw-bold">
      <span><i class="bi bi-check2-square"></i> タスク</span>
      <a href="<?= BASE_URL ?>/tasks/form.php?person_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">追加</a>
    </div>
    <div class="card-body p-0">
      <?php if(empty($tasks)): ?>
      <p class="text-muted p-3 mb-0 small">未完了のタスクはありません</p>
      <?php else: ?>
      <table class="table table-sm mb-0">
        <thead><tr><th>期限</th><th>タイトル</th><th>担当</th><th>状況</th></tr></thead>
        <tbody>
        <?php foreach($tasks as $t): ?>
        <tr>
          <td class="small <?= ($t['due_date']&&$t['due_date']<date('Y-m-d'))?'text-danger fw-bold':'' ?>"><?= h($t['due_date']??'-') ?></td>
          <td><a href="<?= BASE_URL ?>/tasks/form.php?id=<?= $t['id'] ?>" class="small"><?= h($t['title']) ?></a></td>
          <td class="small"><?= h($t['assigned_name']??'-') ?></td>
          <td><span class="badge <?= ['pending'=>'bg-warning text-dark','in_progress'=>'bg-primary'][$t['status']]??'bg-secondary' ?> small"><?= ['pending'=>'未着手','in_progress'=>'対応中'][$t['status']]??h($t['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Attendance -->
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center fw-bold">
      <span><i class="bi bi-calendar-check"></i> 出席履歴</span>
      <a href="<?= BASE_URL ?>/attendance/?person_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">記録</a>
    </div>
    <div class="card-body p-0">
      <?php if(empty($attendance)): ?>
      <p class="text-muted p-3 mb-0 small">出席記録はありません</p>
      <?php else: ?>
      <table class="table table-sm mb-0">
        <thead><tr><th>日付</th><th>集会</th><th>備考</th></tr></thead>
        <tbody>
        <?php foreach($attendance as $a): ?>
        <tr>
          <td class="small"><?= h($a['attended_date']) ?></td>
          <td class="small"><?= h($a['meeting_name']) ?></td>
          <td class="small"><?= h($a['notes']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>

<?php include dirname(__DIR__).'/footer.php'; ?>
