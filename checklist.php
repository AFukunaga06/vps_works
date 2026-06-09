<?php
require_once 'checklist_config.php';

// AJAX POST: 保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $data  = $input['data'] ?? [];
    $stmt  = $pdo->prepare('UPDATE checklist_data SET date1_value=?, date2_value=? WHERE item_order=?');
    foreach ($data as $order => $values) {
        $stmt->execute([
            $values['date1'] ?? '',
            $values['date2'] ?? '',
            (int)$order
        ]);
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    exit;
}

// GET: データ読み込み
$rows = $pdo->query('SELECT * FROM checklist_data ORDER BY item_order')->fetchAll();

function sel($row, $col, $val) {
    return $row[$col] === $val ? ' selected' : '';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>持ち物チェックリスト</title>
  <style>
    body {
      font-family: 'Meiryo', 'メイリオ', sans-serif;
      padding: 20px;
    }
    table {
      border-collapse: collapse;
      width: 420px;
    }
    th, td {
      border: 1px solid #000;
      padding: 8px 12px;
      text-align: left;
    }
    th {
      background-color: #d0e4f7;
      text-align: center;
    }
    td:nth-child(2), td:nth-child(3) {
      width: 60px;
      text-align: center;
    }
    td select {
      width: 55px;
      font-size: 14px;
      cursor: pointer;
    }
    tr:nth-child(even) {
      background-color: #f5f5f5;
    }
    button {
      margin-bottom: 12px;
      padding: 8px 24px;
      font-size: 15px;
      cursor: pointer;
      background-color: #4a90d9;
      color: white;
      border: none;
      border-radius: 4px;
    }
    button:hover {
      background-color: #357ab8;
    }
    #msg {
      margin-left: 12px;
      font-size: 14px;
      color: green;
    }
    tbody tr:hover {
      background-color: #c9eaf8 !important;
    }
  </style>
</head>
<body>
  <button onclick="saveData()">保存する</button>
  <span id="msg"></span>

  <table>
    <thead>
      <tr>
        <th>持ち物チェックリスト名称</th>
        <th>3月18日</th>
        <th>3月19日</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
      <tr data-order="<?= $row['item_order'] ?>">
        <td><?= htmlspecialchars($row['item_name']) ?></td>
        <td>
          <select class="date1">
            <option value=""<?= sel($row,'date1_value','') ?>>ー</option>
            <option value="ok"<?= sel($row,'date1_value','ok') ?>>○</option>
            <option value="before"<?= sel($row,'date1_value','before') ?>>△</option>
          </select>
        </td>
        <td>
          <select class="date2">
            <option value=""<?= sel($row,'date2_value','') ?>>ー</option>
            <option value="ok"<?= sel($row,'date2_value','ok') ?>>○</option>
            <option value="before"<?= sel($row,'date2_value','before') ?>>△</option>
          </select>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <script>
    function saveData() {
      const data = {};
      document.querySelectorAll('tbody tr').forEach(row => {
        const order = row.dataset.order;
        data[order] = {
          date1: row.querySelector('.date1').value,
          date2: row.querySelector('.date2').value
        };
      });
      fetch('checklist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ data })
      })
      .then(r => r.json())
      .then(() => {
        const msg = document.getElementById('msg');
        msg.textContent = '保存しました';
        setTimeout(() => msg.textContent = '', 2000);
      });
    }
  </script>
</body>
</html>
