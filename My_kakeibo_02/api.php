<?php
// =====================================================
//  api.php  ― JSON API エンドポイント
//  フロントエンドの fetch() から呼び出されます
// =====================================================

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// セッションによる簡易CSRF保護
session_start();
$action = $_GET['action'] ?? '';

// GETリクエストは参照系のみ許可
$safeGetActions = ['entries', 'categories', 'shops', 'summary', 'contents'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token mismatch']);
        exit;
    }
}

try {
    $pdo = getDB();

    switch ($action) {

        // ─────────────────────────────────────────────
        //  エントリ一覧取得
        // ─────────────────────────────────────────────
        case 'entries':
            $stmt = $pdo->query('
                SELECT id, entry_date AS `date`, category, shop,
                       withdrawal, charge, deposit, content, detail
                FROM kakeibo_entries
                ORDER BY entry_date ASC, id ASC
            ');
            echo json_encode(['ok' => true, 'entries' => $stmt->fetchAll()]);
            break;

        // ─────────────────────────────────────────────
        //  月別・全体サマリ取得
        // ─────────────────────────────────────────────
        case 'summary':
            $month = $_GET['month'] ?? '';
            // 全体集計
            $total = $pdo->query('
                SELECT SUM(withdrawal) AS withdrawal,
                       SUM(charge)     AS charge,
                       SUM(deposit)    AS deposit
                FROM kakeibo_entries
            ')->fetch();

            // 月別集計
            $monthly = ['withdrawal' => 0, 'charge' => 0, 'deposit' => 0];
            if (preg_match('/^\d{4}-\d{2}$/', $month)) {
                $stmt = $pdo->prepare('
                    SELECT SUM(withdrawal) AS withdrawal,
                           SUM(charge)     AS charge,
                           SUM(deposit)    AS deposit
                    FROM kakeibo_entries
                    WHERE DATE_FORMAT(entry_date, "%Y-%m") = ?
                ');
                $stmt->execute([$month]);
                $monthly = $stmt->fetch();
            }

            echo json_encode([
                'ok'      => true,
                'total'   => $total,
                'monthly' => $monthly,
            ]);
            break;

        // ─────────────────────────────────────────────
        //  エントリ保存（新規 or 更新）
        // ─────────────────────────────────────────────
        case 'save_entry':
            $id         = intval($_POST['id'] ?? 0);
            $date       = $_POST['date']       ?? '';
            $category   = mb_substr(trim($_POST['category']   ?? ''), 0, 100);
            $shop       = mb_substr(trim($_POST['shop']       ?? ''), 0, 100);
            $withdrawal = max(0, intval($_POST['withdrawal']  ?? 0));
            $charge     = max(0, intval($_POST['charge']      ?? 0));
            $deposit    = max(0, intval($_POST['deposit']     ?? 0));
            $content    = mb_substr(trim($_POST['content']    ?? ''), 0, 255);
            $detail     = trim($_POST['detail'] ?? '');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new InvalidArgumentException('日付の形式が不正です');
            }

            if ($id > 0) {
                // 更新
                $stmt = $pdo->prepare('
                    UPDATE kakeibo_entries
                    SET entry_date=?, category=?, shop=?,
                        withdrawal=?, charge=?, deposit=?,
                        content=?, detail=?
                    WHERE id=?
                ');
                $stmt->execute([$date, $category, $shop,
                                $withdrawal, $charge, $deposit,
                                $content, $detail, $id]);
                echo json_encode(['ok' => true, 'id' => $id, 'updated' => true]);
            } else {
                // 新規
                $stmt = $pdo->prepare('
                    INSERT INTO kakeibo_entries
                        (entry_date, category, shop, withdrawal, charge, deposit, content, detail)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([$date, $category, $shop,
                                $withdrawal, $charge, $deposit,
                                $content, $detail]);
                echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'updated' => false]);
            }
            break;

        // ─────────────────────────────────────────────
        //  エントリ削除
        // ─────────────────────────────────────────────
        case 'delete_entry':
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) throw new InvalidArgumentException('IDが不正です');
            $stmt = $pdo->prepare('DELETE FROM kakeibo_entries WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['ok' => true]);
            break;

        // ─────────────────────────────────────────────
        //  分類一覧取得
        // ─────────────────────────────────────────────
        case 'categories':
            $stmt = $pdo->query('SELECT name FROM kakeibo_categories ORDER BY sort_order, id');
            $list = array_column($stmt->fetchAll(), 'name');
            echo json_encode(['ok' => true, 'categories' => $list]);
            break;

        // ─────────────────────────────────────────────
        //  分類追加
        // ─────────────────────────────────────────────
        case 'add_category':
            $name = mb_substr(trim($_POST['name'] ?? ''), 0, 100);
            if ($name === '') throw new InvalidArgumentException('分類名が空です');
            // 最大 sort_order を取得して末尾に追加
            $max = $pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM kakeibo_categories')->fetchColumn();
            $stmt = $pdo->prepare('INSERT IGNORE INTO kakeibo_categories (name, sort_order) VALUES (?, ?)');
            $stmt->execute([$name, $max + 1]);
            echo json_encode(['ok' => true, 'name' => $name]);
            break;

        // ─────────────────────────────────────────────
        //  分類削除
        // ─────────────────────────────────────────────
        case 'delete_category':
            $name = mb_substr(trim($_POST['name'] ?? ''), 0, 100);
            if ($name === '') throw new InvalidArgumentException('分類名が空です');
            $stmt = $pdo->prepare('DELETE FROM kakeibo_categories WHERE name = ?');
            $stmt->execute([$name]);
            echo json_encode(['ok' => true, 'name' => $name]);
            break;

        // ─────────────────────────────────────────────
        //  購入先一覧取得
        // ─────────────────────────────────────────────
        case 'shops':
            $stmt = $pdo->query('SELECT name FROM kakeibo_shops ORDER BY CONVERT(name USING utf8mb4) COLLATE utf8mb4_unicode_ci');
            $list = array_column($stmt->fetchAll(), 'name');
            echo json_encode(['ok' => true, 'shops' => $list]);
            break;

        // ─────────────────────────────────────────────
        //  購入先追加
        // ─────────────────────────────────────────────
        case 'add_shop':
            $name = mb_substr(trim($_POST['name'] ?? ''), 0, 100);
            if ($name === '') throw new InvalidArgumentException('購入先名が空です');
            $max = $pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM kakeibo_shops')->fetchColumn();
            $stmt = $pdo->prepare('INSERT IGNORE INTO kakeibo_shops (name, sort_order) VALUES (?, ?)');
            $stmt->execute([$name, $max + 1]);
            echo json_encode(['ok' => true, 'name' => $name]);
            break;

        // ─────────────────────────────────────────────
        //  購入先削除
        // ─────────────────────────────────────────────
        case 'delete_shop':
            $name = mb_substr(trim($_POST['name'] ?? ''), 0, 100);
            if ($name === '') throw new InvalidArgumentException('購入先名が空です');
            $stmt = $pdo->prepare('DELETE FROM kakeibo_shops WHERE name = ?');
            $stmt->execute([$name]);
            echo json_encode(['ok' => true, 'name' => $name]);
            break;

        // ─────────────────────────────────────────────
        //  内容等マスタ一覧取得
        // ─────────────────────────────────────────────
        case 'contents':
            $stmt = $pdo->query('SELECT name FROM kakeibo_contents ORDER BY sort_order, id');
            $list = array_column($stmt->fetchAll(), 'name');
            echo json_encode(['ok' => true, 'contents' => $list]);
            break;

        // ─────────────────────────────────────────────
        //  内容等マスタ追加
        // ─────────────────────────────────────────────
        case 'add_content':
            $name = mb_substr(trim($_POST['name'] ?? ''), 0, 255);
            if ($name === '') throw new InvalidArgumentException('内容等が空です');
            $max = $pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM kakeibo_contents')->fetchColumn();
            $stmt = $pdo->prepare('INSERT IGNORE INTO kakeibo_contents (name, sort_order) VALUES (?, ?)');
            $stmt->execute([$name, $max + 1]);
            echo json_encode(['ok' => true, 'name' => $name]);
            break;

        // ─────────────────────────────────────────────
        //  内容等マスタ削除
        // ─────────────────────────────────────────────
        case 'delete_content':
            $name = mb_substr(trim($_POST['name'] ?? ''), 0, 255);
            if ($name === '') throw new InvalidArgumentException('内容等が空です');
            $stmt = $pdo->prepare('DELETE FROM kakeibo_contents WHERE name = ?');
            $stmt->execute([$name]);
            echo json_encode(['ok' => true, 'name' => $name]);
            break;

        // ─────────────────────────────────────────────
        //  CSV取り込み
        // ─────────────────────────────────────────────
        case 'import_csv':
            $csv = $_POST['csv'] ?? '';
            if ($csv === '') throw new InvalidArgumentException('CSVデータが空です');
            // BOM除去
            $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
            $lines = preg_split('/\r\n|\r|\n/', trim($csv));
            // タブ区切りかカンマ区切りか自動判定
            $delimiter = (isset($lines[0]) && substr_count($lines[0], "\t") >= substr_count($lines[0], ',')) ? "\t" : ',';
            // 1行目はヘッダーとしてスキップ
            $inserted = 0;
            $stmt = $pdo->prepare('
                INSERT INTO kakeibo_entries
                    (entry_date, category, shop, withdrawal, charge, deposit, content, detail)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            foreach ($lines as $i => $line) {
                if ($i === 0) continue; // ヘッダースキップ
                if (trim($line) === '') continue;
                $row = str_getcsv($line, $delimiter);
                if (count($row) < 3) continue;
                $date       = trim($row[0] ?? '');
                $category   = mb_substr(trim($row[1] ?? ''), 0, 100);
                $shop       = mb_substr(trim($row[2] ?? ''), 0, 100);
                $withdrawal = max(0, intval(str_replace([',', '，', ' '], '', $row[3] ?? 0)));
                $charge     = max(0, intval(str_replace([',', '，', ' '], '', $row[4] ?? 0)));
                $deposit    = max(0, intval(str_replace([',', '，', ' '], '', $row[5] ?? 0)));
                $content    = mb_substr(trim($row[6] ?? ''), 0, 255);
                $detail     = trim($row[7] ?? '');
                // 「3月6日」「2026年3月6日」形式を YYYY-MM-DD に変換
                if (preg_match('/^(\d{4})年(\d{1,2})月(\d{1,2})日$/', $date, $m)) {
                    $date = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
                } elseif (preg_match('/^(\d{1,2})月(\d{1,2})日$/', $date, $m)) {
                    $date = sprintf('%04d-%02d-%02d', '2026', $m[1], $m[2]);
                }
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
                $stmt->execute([$date, $category, $shop, $withdrawal, $charge, $deposit, $content, $detail]);
                $inserted++;
            }
            echo json_encode(['ok' => true, 'inserted' => $inserted]);
            break;

        // ─────────────────────────────────────────────
        //  CSRFトークン発行
        // ─────────────────────────────────────────────
        case 'csrf_token':
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            echo json_encode(['ok' => true, 'token' => $_SESSION['csrf_token']]);
            break;


        // ─────────────────────────────────────────────
        //  バックアップ
        // ─────────────────────────────────────────────
        case 'backup':
            $backupDir = __DIR__ . '/backups';
            if (!is_dir($backupDir)) mkdir($backupDir, 0750, true);
            $filename = 'kakeibo_' . date('Ymd_His') . '.csv';
            $filepath = $backupDir . '/' . $filename;

            $stmt = $pdo->query('
                SELECT entry_date AS date, category, shop,
                       withdrawal, charge, deposit, content, detail
                FROM kakeibo_entries
                ORDER BY entry_date ASC, id ASC
            ');
            $entries = $stmt->fetchAll();

            $fp = fopen($filepath, 'w');
            fwrite($fp, "ï»¿"); // BOM
            fputcsv($fp, ['日付','分類','購入先','出金額','チャージ額','入金額','内容等','メモ']);
            foreach ($entries as $e) {
                fputcsv($fp, [
                    $e['date'], $e['category'], $e['shop'],
                    $e['withdrawal'], $e['charge'], $e['deposit'],
                    $e['content'], $e['detail']
                ]);
            }
            fclose($fp);

            echo json_encode(['ok' => true, 'filename' => $filename, 'count' => count($entries)]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'unknown action']);
    }

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    // 本番では詳細を出力しない
    echo json_encode(['error' => 'サーバーエラーが発生しました']);
    error_log('[kakeibo] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

