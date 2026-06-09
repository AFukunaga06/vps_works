-- =====================================================
--  setup.sql  ― テーブル作成スクリプト
--  実行例: mysql -u kakeibo_user -p kakeibo < setup.sql
-- =====================================================

CREATE TABLE IF NOT EXISTS kakeibo_entries (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE         NOT NULL,
    category   VARCHAR(100) NOT NULL DEFAULT '',
    shop       VARCHAR(100) NOT NULL DEFAULT '',
    withdrawal INT UNSIGNED NOT NULL DEFAULT 0,
    charge     INT UNSIGNED NOT NULL DEFAULT 0,
    deposit    INT UNSIGNED NOT NULL DEFAULT 0,
    content    VARCHAR(255) NOT NULL DEFAULT '',
    detail     TEXT         NOT NULL DEFAULT '',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_entry_date (entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kakeibo_categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kakeibo_shops (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- デフォルト分類の挿入
INSERT IGNORE INTO kakeibo_categories (name, sort_order) VALUES
('チャージ',   1),
('食費',       2),
('交通費',     3),
('医療費',     4),
('日用品',     5),
('娯楽',       6),
('光熱費',     7),
('通信費',     8),
('その他',     9),
('預金引出し', 10),
('その他2',    11);

-- デフォルト購入先の挿入
INSERT IGNORE INTO kakeibo_shops (name, sort_order) VALUES
('ファミマ',                 1),
('まいばすけっと',           2),
('湘南交通',                 3),
('かみながや接骨院',         4),
('サカイヤ',                 5),
('橋戸歯科',                 6),
('セブンイレブン',           7),
('ローソン',                 8),
('イオン',                   9),
('みなとみらいクリニック',   10),
('緩和会横浜クリニック',     11),
('釜めし お可免',            12),
('ヨドバシカメラ',           13),
('エラン',                   14),
('ソニー生命',               15),
('イトーヨーカドー',         16),
('すき家',                   17),
('スギ薬局',                 18),
('かず整形外科',             19),
('丸山台薬局',               20),
('母より',                   21),
('木下クリニック',           22),
('間宮薬局',                 23),
('ドトールコーヒー',         24),
('カットファクトリー',       25),
('京急百貨店',               26),
('らら薬局',                 27),
('湘寿クリニック',           28),
('かもめ薬局下永谷店',       29),
('市村税理士事務所',         30),
('Amazon',                   31),
('業務スーパー',             32),
('ダイソー',                 33),
('CanDo',                    34),
('キャンドゥ',               35),
('セリア',                   36),
('コーナン',                 37),
('島忠',                     38),
('オーケーストア',           39),
('イトーヨーカドーネット',   40),
('西松屋',                   41),
('無印良品',                 42),
('ユニクロ',                 43),
('GU',                       44),
('ビックカメラ',             45),
('ノジマ',                   46),
('コジマ',                   47),
('ヤマダデンキ',             48),
('マツモトキヨシ',           49),
('クリエイト',               50),
('ハックドラッグ',           51),
('ウェルシア',               52),
('Amazonマーケットプレイス', 53),
('楽天市場',                 54),
('Yahoo!ショッピング',       55);
