CREATE TABLE IF NOT EXISTS short_urls (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(32) NOT NULL UNIQUE,
    long_url    TEXT NOT NULL,
    title       VARCHAR(255) DEFAULT NULL,
    clicks      INT NOT NULL DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_access DATETIME DEFAULT NULL,
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS click_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    short_id   INT NOT NULL,
    accessed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    referer    VARCHAR(2048) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    ip         VARCHAR(64) DEFAULT NULL,
    INDEX idx_short (short_id),
    FOREIGN KEY (short_id) REFERENCES short_urls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
