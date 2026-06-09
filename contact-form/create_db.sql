-- お問い合わせログテーブル
CREATE TABLE IF NOT EXISTS contact_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    submitted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address   VARCHAR(45)  NOT NULL,
    name         VARCHAR(100) NOT NULL,
    email        VARCHAR(255) NOT NULL,
    subject      VARCHAR(255),
    message      TEXT,
    extra_data   JSON,
    status       TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
