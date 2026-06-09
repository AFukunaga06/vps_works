<?php
require_once 'config.php';

try {
    // DBが存在しない場合は作成
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");

    $pdo->exec("CREATE TABLE IF NOT EXISTS works (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        url VARCHAR(500) DEFAULT '#',
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // サンプルデータ挿入
    $count = $pdo->query("SELECT COUNT(*) FROM works")->fetchColumn();
    if ($count == 0) {
        $stmt = $pdo->prepare("INSERT INTO works (title, description, url, sort_order) VALUES (?, ?, ?, ?)");
        $samples = [
            ['出席簿システム', '教会の出欠管理を効率化するWebアプリです。メンバーの出席状況をリアルタイムで管理できます。', '#', 1],
            ['聖書通読表アプリ', '聖書の通読計画を管理・記録できるアプリです。日々の読書進捗を可視化します。', '#', 2],
            ['顧客管理システム', '顧客情報を一元管理するCRMシステムです。商談履歴や連絡先を整理して活用できます。', '#', 3],
            ['作品紹介ページ', '制作した作品をまとめて紹介するポートフォリオサイトです。', '#', 4],
            ['AI相談サイト', 'AIを活用した相談・サポートサービスです。気軽に悩みを相談できます。', '#', 5],
        ];
        foreach ($samples as $s) {
            $stmt->execute($s);
        }
        echo "✅ サンプルデータを挿入しました。<br>";
    }

    echo "✅ データベースのセットアップが完了しました！<br>";
    echo "<a href='index.php'>← トップページへ戻る</a>";
} catch (PDOException $e) {
    echo "❌ エラー: " . htmlspecialchars($e->getMessage());
}
?>
