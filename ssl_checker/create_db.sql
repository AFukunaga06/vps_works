-- SSL証明書 期限チェッカー  初期セットアップ
-- 実行例: mysql -u root -p < create_db.sql

CREATE DATABASE IF NOT EXISTS ssl_checker
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'ssl_user'@'localhost' IDENTIFIED BY 'REDACTED_FOR_PUBLIC';
GRANT ALL PRIVILEGES ON ssl_checker.* TO 'ssl_user'@'localhost';
FLUSH PRIVILEGES;

USE ssl_checker;

CREATE TABLE IF NOT EXISTS ssl_domains (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    domain       VARCHAR(255) NOT NULL UNIQUE,
    port         INT          NOT NULL DEFAULT 443,
    note         VARCHAR(500) DEFAULT NULL,
    valid_from   DATETIME     DEFAULT NULL,
    valid_to     DATETIME     DEFAULT NULL,
    issuer       VARCHAR(500) DEFAULT NULL,
    common_name  VARCHAR(255) DEFAULT NULL,
    last_checked DATETIME     DEFAULT NULL,
    last_error   VARCHAR(500) DEFAULT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_valid_to (valid_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
