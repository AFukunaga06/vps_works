<?php
require_once dirname(__DIR__).'/auth.php';
require_login();
require_role(['admin','pastor','secretary']);

// CSV export
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$type.'_'.date('Ymd').'.csv"');
    echo "\xEF\xBB\xBF"; // BOM for Excel

    if ($type === 'persons') {
        echo implode(',',['ID','姓','名','姓カナ','名カナ','ステータス','性別','生年月日','電話','メール','住所','初来会日','最終出席日','担当者','個人情報同意','写真掲載','名簿掲載'])."\n";
        $rows = $pdo->query("SELECT p.*,u.display_name FROM persons p LEFT JOIN users u ON p.assigned_user_id=u.id ORDER BY p.last_name")->fetchAll();
        foreach($rows as $r) {
            $gender = ['male'=>'男性','female'=>'女性','other'=>'その他'][$r['gender']??'']??'';
            echo implode(',',array_map(fn($v)=>'"'.str_replace('"','""',$v??'').'"',[
                $r['id'],$r['last_name'],$r['first_name'],$r['last_name_kana'],$r['first_name_kana'],
                status_label($r['status']),$gender,$r['birth_date'],$r['phone'],$r['email'],
                $r['address'],$r['first_visit_date'],$r['last_attend_date'],$r['display_name'],
                $r['consent_personal_info']?'○':'×',$r['photo_permission']?'○':'×',$r['directory_permission']?'○':'×'
            ]))."\n";
        }
    } elseif ($type === 'long_absent') {
        echo implode(',',['ID','姓','名','ステータス','最終出席日','担当者','電話','メール'])."\n";
        $rows = $pdo->query("SELECT p.*,u.display_name FROM persons p LEFT JOIN users u ON p.assigned_user_id=u.id WHERE p.status IN ('regular','member','seeker') AND (p.last_attend_date IS NULL OR p.last_attend_date < DATE_SUB(CURDATE(),INTERVAL 2 MONTH)) ORDER BY p.last_attend_date ASC")->fetchAll();
        foreach($rows as $r) {
            echo implode(',',array_map(fn($v)=>'"'.str_replace('"','""',$v??'').'"',[
                $r['id'],$r['last_name'],$r['first_name'],status_label($r['status']),
                $r['last_attend_date'],$r['display_name'],$r['phone'],$r['email']
            ]))."\n";
        }
    } elseif ($type === 'inquiries') {
        echo implode(',',['ID','氏名','ふりがな','メール','電話','連絡希望時間帯','種別','日付','内容','状況'])."\n";
        $rows = $pdo->query("SELECT * FROM inquiries ORDER BY inquiry_date DESC")->fetchAll();
        $itype_map = ['phone'=>'電話','email'=>'メール','form'=>'フォーム','walk_in'=>'来訪','other'=>'その他'];
        $istatus_map = ['pending'=>'未対応','in_progress'=>'対応中','resolved'=>'解決済'];
        foreach($rows as $r) {
            echo implode(',',array_map(fn($v)=>'"'.str_replace('"','""',$v??'').'"',[
                $r['id'], $r['inquirer_name'], $r['inquirer_kana'], $r['inquirer_email'],
                $r['inquirer_tel'], $r['preferred_time'],
                $itype_map[$r['inquiry_type']]??$r['inquiry_type'],
                $r['inquiry_date'], $r['content'],
                $istatus_map[$r['status']]??$r['status'],
            ]))."\n";
        }
    } elseif ($type === 'attendance_monthly') {
        $month = $_GET['month'] ?? date('Y-m');
        echo implode(',',['日付','氏名','集会','備考'])."\n";
        $stmt = $pdo->prepare("SELECT a.*,p.last_name,p.first_name,mt.name as meeting_name FROM attendance a JOIN persons p ON a.person_id=p.id JOIN meeting_types mt ON a.meeting_type_id=mt.id WHERE DATE_FORMAT(a.attended_date,'%Y-%m')=? ORDER BY a.attended_date,mt.name,p.last_name");
        $stmt->execute([$month]);
        foreach($stmt->fetchAll() as $r) {
            echo implode(',',array_map(fn($v)=>'"'.str_replace('"','""',$v??'').'"',[
                $r['attended_date'],$r['last_name'].$r['first_name'],$r['meeting_name'],$r['notes']
            ]))."\n";
        }
    }
    exit;
}

// Stats
$status_counts = $pdo->query("SELECT status, COUNT(*) as cnt FROM persons GROUP BY status")->fetchAll();
$status_map = array_column($status_counts,'cnt','status');

$monthly_first = $pdo->query("SELECT DATE_FORMAT(first_visit_date,'%Y-%m') as ym, COUNT(*) as cnt FROM persons WHERE first_visit_date IS NOT NULL GROUP BY ym ORDER BY ym DESC LIMIT 12")->fetchAll();

$long_absent = $pdo->query("SELECT p.*,u.display_name as assigned_name FROM persons p LEFT JOIN users u ON p.assigned_user_id=u.id WHERE p.status IN ('regular','member','seeker') AND (p.last_attend_date IS NULL OR p.last_attend_date < DATE_SUB(CURDATE(),INTERVAL 2 MONTH)) ORDER BY p.last_attend_date ASC LIMIT 50")->fetchAll();

$follow_by_user = $pdo->query("SELECT u.display_name, COUNT(f.id) as cnt FROM follow_histories f JOIN users u ON f.recorded_by=u.id WHERE f.follow_date >= DATE_SUB(CURDATE(),INTERVAL 1 MONTH) GROUP BY u.id ORDER BY cnt DESC")->fetchAll();

$inquiries_list = $pdo->query("SELECT id, inquirer_name, inquirer_kana, inquirer_email, inquirer_tel, preferred_time, inquiry_date, status FROM inquiries ORDER BY inquiry_date DESC LIMIT 50")->fetchAll();

include dirname(__DIR__).'/header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h4 class="mb-0"><i class="bi bi-bar-chart"></i> 集計・CSV出力</h4>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header fw-bold">ステータス別人数</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>ステータス</th><th class="text-end">人数</th></tr></thead>
          <tbody>
          <?php foreach(['inquiry','first_visit','regular','seeker','baptism_prep','member','inactive','ended'] as $s): ?>
          <tr>
            <td><?= status_badge($s) ?></td>
            <td class="text-end fw-bold"><?= $status_map[$s]??0 ?></td>
          </tr>
          <?php endforeach; ?>
          <tr class="table-light"><td><strong>合計（終了除く）</strong></td><td class="text-end fw-bold"><?= array_sum(array_filter($status_map,fn($k)=>$k!=='ended',ARRAY_FILTER_USE_KEY)) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-header fw-bold">月別初来会者数（直近12ヶ月）</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>年月</th><th class="text-end">人数</th></tr></thead>
          <tbody>
          <?php if(empty($monthly_first)): ?><tr><td colspan="2" class="text-center text-muted py-3">データなし</td></tr><?php endif; ?>
          <?php foreach($monthly_first as $m): ?>
          <tr><td><?= h($m['ym']) ?></td><td class="text-end"><?= $m['cnt'] ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-header fw-bold">先月のフォロー件数（担当者別）</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <?php if(empty($follow_by_user)): ?><tr><td class="text-center text-muted py-3">データなし</td></tr><?php endif; ?>
          <?php foreach($follow_by_user as $f): ?>
          <tr><td><?= h($f['display_name']) ?></td><td class="text-end fw-bold"><?= $f['cnt'] ?>件</td></tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-header fw-bold"><i class="bi bi-download"></i> CSV出力</div>
      <div class="card-body">
        <div class="d-grid gap-2">
          <a href="?export=persons" class="btn btn-outline-primary btn-sm"><i class="bi bi-people"></i> 全人物一覧 CSV</a>
          <a href="?export=long_absent" class="btn btn-outline-warning btn-sm"><i class="bi bi-person-x"></i> 長期未出席者 CSV</a>
          <a href="?export=inquiries" class="btn btn-outline-info btn-sm"><i class="bi bi-chat-dots"></i> お問い合わせ CSV</a>
          <div class="input-group input-group-sm">
            <input type="month" id="month_input" class="form-control" value="<?= date('Y-m') ?>">
            <button class="btn btn-outline-secondary" onclick="location.href='?export=attendance_monthly&month='+document.getElementById('month_input').value">
              <i class="bi bi-calendar3"></i> 出席記録 CSV
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header fw-bold text-warning"><i class="bi bi-person-x"></i> 長期未出席者（2ヶ月以上）</div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0">
      <thead><tr><th>氏名</th><th>ステータス</th><th>最終出席</th><th>担当者</th><th>電話</th></tr></thead>
      <tbody>
      <?php if(empty($long_absent)): ?><tr><td colspan="5" class="text-center text-muted py-3">該当者なし</td></tr><?php endif; ?>
      <?php foreach($long_absent as $p): ?>
      <tr>
        <td><a href="<?= BASE_URL ?>/persons/view.php?id=<?= $p['id'] ?>" class="small"><?= h($p['last_name'].$p['first_name']) ?></a></td>
        <td><?= status_badge($p['status']) ?></td>
        <td class="small text-danger"><?= h($p['last_attend_date']??'記録なし') ?></td>
        <td class="small"><?= h($p['assigned_name']??'-') ?></td>
        <td class="small"><?= h($p['phone']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mt-4">
  <div class="card-header fw-bold"><i class="bi bi-chat-dots"></i> お問い合わせ管理（直近50件）</div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0">
      <thead><tr><th>日付</th><th>問い合わせ者</th><th>ふりがな</th><th>メール</th><th>電話</th><th>連絡希望時間帯</th><th>状況</th><th></th></tr></thead>
      <tbody>
      <?php if(empty($inquiries_list)): ?>
      <tr><td colspan="8" class="text-center text-muted py-3">データなし</td></tr>
      <?php endif; ?>
      <?php foreach($inquiries_list as $i):
        $badge = ['pending'=>'bg-warning text-dark','in_progress'=>'bg-primary','resolved'=>'bg-success'][$i['status']]??'bg-secondary';
        $label = ['pending'=>'未対応','in_progress'=>'対応中','resolved'=>'解決済'][$i['status']]??$i['status'];
      ?>
      <tr>
        <td class="small"><?= h($i['inquiry_date']) ?></td>
        <td class="small fw-bold"><?= h($i['inquirer_name']??'-') ?></td>
        <td class="small"><?= h($i['inquirer_kana']??'-') ?></td>
        <td class="small"><?= h($i['inquirer_email']??'-') ?></td>
        <td class="small"><?= h($i['inquirer_tel']??'-') ?></td>
        <td class="small"><?= h($i['preferred_time']??'-') ?></td>
        <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
        <td><a href="<?= BASE_URL ?>/inquiries/form.php?id=<?= $i['id'] ?>" class="btn btn-outline-secondary btn-sm py-0"><i class="bi bi-pencil"></i></a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include dirname(__DIR__).'/footer.php'; ?>
