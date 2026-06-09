-- ============================================================
--  シンプル出欠管理データベース
-- ============================================================

CREATE DATABASE IF NOT EXISTS attendance_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE attendance_db;

-- 出欠テーブル（1つだけ）
CREATE TABLE IF NOT EXISTS attendance (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attend_date  DATE         NOT NULL COMMENT '日付',
  name         VARCHAR(100) NOT NULL COMMENT '氏名',
  status       ENUM('出席','欠席') NOT NULL DEFAULT '出席',
  INDEX idx_date (attend_date),
  INDEX idx_name (name)
);

-- サンプルデータ
INSERT INTO attendance (attend_date, name, status) VALUES
('2025-01-05', '田中 太郎', '出席'),
('2025-01-05', '鈴木 花子', '出席'),
('2025-01-05', '佐藤 次郎', '欠席'),
('2025-01-12', '田中 太郎', '出席'),
('2025-01-12', '鈴木 花子', '欠席'),
('2025-01-12', '佐藤 次郎', '出席'),
('2025-01-19', '田中 太郎', '欠席'),
('2025-01-19', '鈴木 花子', '出席'),
('2025-01-19', '佐藤 次郎', '出席'),
('2025-02-02', '田中 太郎', '出席'),
('2025-02-02', '鈴木 花子', '出席'),
('2025-02-02', '佐藤 次郎', '出席'),
('2025-02-09', '田中 太郎', '出席'),
('2025-02-09', '鈴木 花子', '欠席'),
('2025-02-09', '佐藤 次郎', '出席');
