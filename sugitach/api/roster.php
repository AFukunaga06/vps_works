<?php
/**
 * 名簿API
 * GET  : 名簿一覧取得
 * POST : 名簿の初期化・CSV読み込み・行追加削除
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();

// ==================== GET: 名簿取得 ====================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $gender = $_GET['gender'] ?? null; // male / female / null(全件)
    
    if ($gender && in_array($gender, ['male', 'female'])) {
        $stmt = $pdo->prepare('SELECT id, gender, sort_order, name, is_newcomer FROM roster WHERE gender = ? ORDER BY sort_order');
        $stmt->execute([$gender]);
    } else {
        $stmt = $pdo->query('SELECT id, gender, sort_order, name, is_newcomer FROM roster ORDER BY gender, sort_order');
    }
    
    echo json_encode(['success' => true, 'roster' => $stmt->fetchAll()]);
    exit;
}

// ==================== POST: 名簿操作 ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    // --- 名簿初期化（デフォルト名簿をDBに投入） ---
    if ($action === 'initialize') {
        $maleNames = [
            "青木 健太","石井 直樹","上田 翔太","大野 和也","岡田 拓也",
            "小川 裕樹","河野 智也","菊地 大輔","黒田 亮","坂本 正人",
            "塩谷 拓海","杉山 健一","関口 誠","高木 直人","武田 俊也",
            "谷口 祐介","永井 健","野村 拓真","原田 直哉","藤原 誠",
            "堀内 祐樹","増田 健","三浦 隆志","宮本 大樹","村上 恒一",
        ];
        $femaleNames = [
            "佐藤 彩花","鈴木 美咲","高橋 玲奈","田中 結衣","伊藤 陽菜",
            "渡辺 さくら","山本 りお","中村 愛","小林 えま","加藤 みお",
            "吉田 ひなた","山田 こころ","佐々木 杏","山口 莉子","松本 結菜",
            "井上 乃愛","木村 葵","林 美月","斎藤 心春","清水 まゆ",
            "山崎 ほのか","阿部 かのん","森 ひかり","池田 いちか","橋本 みなみ",
        ];
        $newcomerCount = 15;

        $pdo->beginTransaction();
        try {
            // 既存データ削除
            $pdo->exec('DELETE FROM attendance');
            $pdo->exec('DELETE FROM roster');
            $pdo->exec('ALTER TABLE roster AUTO_INCREMENT = 1');

            $stmt = $pdo->prepare('INSERT INTO roster (gender, sort_order, name, is_newcomer) VALUES (?, ?, ?, ?)');
            
            $order = 1;
            foreach ($maleNames as $name) {
                $stmt->execute(['male', $order++, $name, 0]);
            }
            for ($i = 1; $i <= $newcomerCount; $i++) {
                $stmt->execute(['male', $order++, "新来会者等{$i}", 1]);
            }
            
            $order = 1;
            foreach ($femaleNames as $name) {
                $stmt->execute(['female', $order++, $name, 0]);
            }
            for ($i = 1; $i <= $newcomerCount; $i++) {
                $stmt->execute(['female', $order++, "新来会者等{$i}", 1]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => '名簿を初期化しました']);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('名簿初期化エラー: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => '名簿の初期化に失敗しました']);
        }
        exit;
    }

    // --- CSV読み込み（置換 or 追加） ---
    if ($action === 'import_csv') {
        $gender = $input['gender'] ?? '';
        $names  = $input['names'] ?? [];
        $mode   = $input['mode'] ?? 'replace'; // replace or append
        $newcomerCount = 15;

        if (!in_array($gender, ['male', 'female']) || !is_array($names)) {
            echo json_encode(['success' => false, 'message' => 'パラメータが不正です']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            if ($mode === 'replace') {
                // 該当性別の出欠データも削除
                $stmt = $pdo->prepare('DELETE FROM attendance WHERE roster_id IN (SELECT id FROM roster WHERE gender = ?)');
                $stmt->execute([$gender]);
                // 該当性別の名簿を削除
                $stmt = $pdo->prepare('DELETE FROM roster WHERE gender = ?');
                $stmt->execute([$gender]);
            }

            $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM roster WHERE gender = ?');
            $stmt->execute([$gender]);
            $order = (int)$stmt->fetchColumn() + 1;

            $ins = $pdo->prepare('INSERT INTO roster (gender, sort_order, name, is_newcomer) VALUES (?, ?, ?, ?)');
            
            foreach ($names as $name) {
                $name = trim($name);
                if ($name !== '') {
                    $ins->execute([$gender, $order++, $name, 0]);
                }
            }

            // replace モードの場合は新来会者枠も再追加
            if ($mode === 'replace') {
                for ($i = 1; $i <= $newcomerCount; $i++) {
                    $ins->execute([$gender, $order++, "新来会者等{$i}", 1]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'CSVを読み込みました']);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('CSV読み込みエラー: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'CSV読み込みに失敗しました']);
        }
        exit;
    }

    // --- 行追加 ---
    if ($action === 'add_rows') {
        $gender = $input['gender'] ?? '';
        $count  = intval($input['count'] ?? 0);

        if (!in_array($gender, ['male', 'female']) || $count <= 0) {
            echo json_encode(['success' => false, 'message' => 'パラメータが不正です']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM roster WHERE gender = ?');
        $stmt->execute([$gender]);
        $order = (int)$stmt->fetchColumn() + 1;

        $ins = $pdo->prepare('INSERT INTO roster (gender, sort_order, name, is_newcomer) VALUES (?, ?, ?, ?)');
        for ($i = 0; $i < $count; $i++) {
            $ins->execute([$gender, $order + $i, '', 0]);
        }

        echo json_encode(['success' => true, 'message' => "{$count}行追加しました"]);
        exit;
    }

    // --- 末尾空欄行削除 ---
    if ($action === 'remove_rows') {
        $gender = $input['gender'] ?? '';
        $count  = intval($input['count'] ?? 0);

        if (!in_array($gender, ['male', 'female']) || $count <= 0) {
            echo json_encode(['success' => false, 'message' => 'パラメータが不正です']);
            exit;
        }

        // 末尾の空欄行を取得
        $stmt = $pdo->prepare("SELECT id FROM roster WHERE gender = ? AND name = '' ORDER BY sort_order DESC LIMIT ?");
        $stmt->bindValue(1, $gender);
        $stmt->bindValue(2, $count, PDO::PARAM_INT);
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM attendance WHERE roster_id IN ({$placeholders})")->execute($ids);
            $pdo->prepare("DELETE FROM roster WHERE id IN ({$placeholders})")->execute($ids);
        }

        echo json_encode(['success' => true, 'message' => count($ids) . '行削除しました']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => '不明なアクションです']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
