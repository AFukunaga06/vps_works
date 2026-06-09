CREATE DATABASE IF NOT EXISTS kojinnjyouho CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kojinnjyouho;

-- 調査依頼テーブル
CREATE TABLE IF NOT EXISTS research_requests (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    topic         TEXT NOT NULL COMMENT '調査テーマ・質問',
    submitter     VARCHAR(100) DEFAULT NULL COMMENT '投稿者名（NULLは匿名）',
    detail        TEXT DEFAULT NULL COMMENT '補足・詳細',
    status        ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    result        LONGTEXT DEFAULT NULL COMMENT 'Claude APIの調査結果',
    iterations    INT NOT NULL DEFAULT 0 COMMENT '試行回数',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at  TIMESTAMP DEFAULT NULL,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) COMMENT='調査依頼一覧';

-- 実験ログテーブル（autoresearchのresults.tsvに相当）
CREATE TABLE IF NOT EXISTS research_log (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    request_id    INT NOT NULL,
    iteration     INT NOT NULL DEFAULT 1,
    prompt_used   TEXT COMMENT '送信したプロンプト',
    result        LONGTEXT COMMENT 'Claude APIの応答',
    status        ENUM('success','failed') DEFAULT 'success',
    logged_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES research_requests(id) ON DELETE CASCADE,
    INDEX idx_request (request_id)
) COMMENT='実験ループのログ';
