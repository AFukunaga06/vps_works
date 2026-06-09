<?php
require_once dirname(__DIR__).'/auth.php';
require_login();

$person_id_filter = (int)($_GET['person_id'] ?? 0);
$meeting_types = $pdo->query("SELECT * FROM meeting_types WHERE is_active=1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $person_id = (int)$_POST['person_id'];
    $meeting_type_id = (int)$_POST['meeting_type_id'];
    $attended_date = $_POST['attended_date'] ?? date('Y-m-d');
    $notes = $_POST['notes'] ?? null;

    if ($person_id && $meeting_type_id && $attended_date) {
        // Check duplicate
        $dup = $pdo->prepare("SELECT id FROM attendance WHERE person_id=? AND meeting_type_id=? AND attended_date=?");
        $dup->execute([$person_id,$meeting_type_id,$attended_date]);
        if (!$dup->fetch()) {
            $pdo->prepare("INSERT INTO attendance (person_id,meeting_type_id,attended_date,notes,recorded_by) VALUES (?,?,?,?,?)")
                ->execute([$person_id,$meeting_type_id,$attended_date,$notes,$_SESSION['user_id']]);
            // Update last_attend_date
            $pdo->prepare("UPDATE persons SET last_attend_date=GREATEST(COALESCE(last_attend_date,'1900-01-01'),?) WHERE id=?")
                ->execute([$attended_date,$person_id]);
            flash('出席を記録しました。');
        } else {
            flash('同日同集会の記録が既に存在します。','warning');
        }
    }
    header('Location: '.BASE_URL.'/attendance/?person_id='.$person_id);
    exit;
}

// Person search for the form
$person_search = trim($_GET['ps'] ?? '');
$person = null;
$search_results = [];
if ($person_id_filter) {
    $stmt = $pdo->prepare("SELECT * FROM persons WHERE id=?");
    $stmt->execute([$person_id_filter]);
    $person = $stmt->fetch();
}
if ($person_search) {
    $stmt = $pdo->prepare("SELECT id,last_name,first_name FROM persons WHERE (last_name LIKE ? OR first_name LIKE ? OR last_name_kana LIKE ?) AND status NOT IN ('ended') LIMIT 20");
    $s = "%$person_search%";
    $stmt->execute([$s,$s,$s]);
    $search_results = $stmt->fetchAll();
}

// Recent attendance
$recent_where = $person_id_filter ? "WHERE a.person_id=$person_id_filter" : "WHERE 1=1";
$recent = $pdo->query("SELECT a.*, p.last_name, p.first_name, mt.name as meeting_name FROM attendance a JOIN persons p ON a.person_id=p.id JOIN meeting_types mt ON a.meeting_type_id=mt.id $recent_where ORDER BY a.attended_date DESC, a.created_at DESC LIMIT 30")->fetchAll();

include dirname(__DIR__).'/header.php';
?>
<h4 class="mb-3"><i class="bi bi-calendar-check"></i> 出席記録</h4>

<div class="row g-3">
<div class="col-md-4">
  <div class="card mb-3">
    <div class="card-header fw-bold">出席を記録する</div>
    <div class="card-body">
      <?php if(!$person && !$person_search): ?>
      <form class="d-flex gap-2 mb-3">
        <input type="hidden" name="person_id" value="<?= $person_id_filter ?>">
        <input type="text" name="ps" class="form-control form-control-sm" placeholder="氏名で検索" value="<?= h($person_search) ?>">
        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
      </form>
      <?php if(!empty($search_results)): ?>
      <ul class="list-group list-group-flush">
        <?php foreach($search_results as $sr): ?>
        <li class="list-group-item py-1">
          <a href="?person_id=<?= $sr['id'] ?>" class="small"><?= h($sr['last_name'].$sr['first_name']) ?></a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php else: ?>
      <?php if($person): ?>
      <p class="mb-2"><strong><?= h($person['last_name'].$person['first_name']) ?></strong>
        <a href="<?= BASE_URL ?>/attendance/" class="btn btn-outline-secondary btn-sm py-0 ms-2">変更</a>
      </p>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="person_id" value="<?= $person_id_filter ?>">
        <?php if(!$person_id_filter): ?>
        <div class="mb-2">
          <label class="form-label small fw-bold">対象者</label>
          <select name="person_id" class="form-select form-select-sm" required>
            <option value="">選択</option>
            <?php
            $all_p = $pdo->query("SELECT id,last_name,first_name FROM persons WHERE status NOT IN ('ended') ORDER BY last_name")->fetchAll();
            foreach($all_p as $ap):
            ?><option value="<?= $ap['id'] ?>"><?= h($ap['last_name'].$ap['first_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="mb-2">
          <label class="form-label small fw-bold">集会</label>
          <select name="meeting_type_id" class="form-select form-select-sm" required>
            <?php foreach($meeting_types as $mt): ?>
            <option value="<?= $mt['id'] ?>"><?= h($mt['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">出席日</label>
          <input type="date" name="attended_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label small">備考</label>
          <input type="text" name="notes" class="form-control form-control-sm">
        </div>
        <button class="btn btn-primary btn-sm w-100">記録する</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="col-md-8">
  <div class="card">
    <div class="card-header fw-bold">
      <?= $person ? h($person['last_name'].$person['first_name']).'の出席記録' : '最近の出席記録' ?>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <thead><tr><th>日付</th><th>氏名</th><th>集会</th><th>備考</th></tr></thead>
        <tbody>
        <?php if(empty($recent)): ?><tr><td colspan="4" class="text-center text-muted py-3">記録なし</td></tr><?php endif; ?>
        <?php foreach($recent as $r): ?>
        <tr>
          <td class="small"><?= h($r['attended_date']) ?></td>
          <td><a href="<?= BASE_URL ?>/persons/view.php?id=<?= $r['person_id'] ?>" class="small"><?= h($r['last_name'].$r['first_name']) ?></a></td>
          <td class="small"><?= h($r['meeting_name']) ?></td>
          <td class="small"><?= h($r['notes']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>
<?php include dirname(__DIR__).'/footer.php'; ?>
