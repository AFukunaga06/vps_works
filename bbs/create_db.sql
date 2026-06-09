-- 簡易掲示板 DB作成スクリプト
-- 実行例: mysql -u root -p bbs_db < create_db.sql

CREATE TABLE IF NOT EXISTS threads (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(200)  NOT NULL,
    body       TEXT          NOT NULL,
    author     VARCHAR(100)  NOT NULL DEFAULT '名無し',
    email      VARCHAR(100)  NOT NULL DEFAULT '',
    ip_address VARCHAR(45)   NOT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS replies (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    thread_id  INT           NOT NULL,
    body       TEXT          NOT NULL,
    author     VARCHAR(100)  NOT NULL DEFAULT '名無し',
    email      VARCHAR(100)  NOT NULL DEFAULT '',
    ip_address VARCHAR(45)   NOT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_thread_id (thread_id),
    CONSTRAINT fk_replies_thread
        FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
