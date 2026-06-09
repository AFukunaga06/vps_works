-- calendar02 データベース・テーブル作成
CREATE DATABASE IF NOT EXISTS calendar02
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE calendar02;

-- ユーザーテーブル
CREATE TABLE IF NOT EXISTS users (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email        VARCHAR(255) NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 予定テーブル
CREATE TABLE IF NOT EXISTS events (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    event_date DATE         NOT NULL,
    event_time TIME         NULL,
    title      VARCHAR(200) NOT NULL,
    memo       TEXT         NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- デフォルトユーザー作成（パスワード: calendar2025）
-- 本番では必ず変更すること
INSERT IGNORE INTO users (username, password_hash, email)
VALUES (
    'admin',
    'REDACTED_PASSWORD_HASH',
    'afky5906s@gmail.com'
);
