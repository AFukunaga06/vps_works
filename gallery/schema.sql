CREATE TABLE IF NOT EXISTS files (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    orig_name     VARCHAR(500) NOT NULL,
    stored_name   VARCHAR(255) NOT NULL,
    thumb_name    VARCHAR(255) DEFAULT NULL,
    mime          VARCHAR(120) NOT NULL,
    size_bytes    BIGINT NOT NULL,
    title         VARCHAR(255) DEFAULT NULL,
    description   TEXT,
    is_image      TINYINT(1) NOT NULL DEFAULT 0,
    width         INT DEFAULT NULL,
    height        INT DEFAULT NULL,
    uploaded_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_uploaded (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
