#!/usr/bin/env php
<?php
// =====================================================
//  backup_cron.php  ― 深夜0時自動バックアップ用スクリプト
//  cron: 0 0 * * * /usr/bin/php /var/www/html/My_kakeibo_01/backup_cron.php >> /var/log/kakeibo_backup.log 2>&1
// =====================================================

require_once __DIR__ . '/config.php';

$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0750, true);

try {
    $pdo = getDB();

    $filename = 'kakeibo_auto_' . date('Ymd_His') . '.csv';
    $filepath = $backupDir . '/' . $filename;

    $stmt = $pdo->query('
        SELECT entry_date AS date, category, shop,
               withdrawal, charge, deposit, content, detail
        FROM kakeibo_entries
        ORDER BY entry_date ASC, id ASC
    ');
    $entries = $stmt->fetchAll();

    $fp = fopen($filepath, 'w');
    fwrite($fp, "\xEF\xBB\xBF"); // UTF-8 BOM
    fputcsv($fp, ['日付','分類','購入先','出金額','チャージ額','入金額','内容等','メモ']);
    foreach ($entries as $e) {
        fputcsv($fp, [
            $e['date'], $e['category'], $e['shop'],
            $e['withdrawal'], $e['charge'], $e['deposit'],
            $e['content'], $e['detail']
        ]);
    }
    fclose($fp);

    // 30日より古い自動バックアップを削除
    $oldFiles = glob($backupDir . '/kakeibo_auto_*.csv');
    $cutoff = time() - (30 * 24 * 3600);
    $deleted = 0;
    foreach ($oldFiles as $f) {
        if (filemtime($f) < $cutoff) {
            unlink($f);
            $deleted++;
        }
    }

    $cnt = count($entries);
    $ts  = date('Y-m-d H:i:s');
    $del = $deleted > 0 ? " / 古いファイル削除: {$deleted}件" : '';
    echo "{$ts} [OK] バックアップ完了: {$filename} ({$cnt}件){$del}\n";

} catch (Throwable $e) {
    $ts = date('Y-m-d H:i:s');
    $msg = $e->getMessage();
    echo "{$ts} [ERROR] {$msg}\n";
    exit(1);
}
