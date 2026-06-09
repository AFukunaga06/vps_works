USE fuku_soudan;

CREATE TABLE IF NOT EXISTS crm_staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS crm_customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    kana VARCHAR(100) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL DEFAULT '',
    tel VARCHAR(20) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    birth_date DATE DEFAULT NULL,
    member_status ENUM('active','inactive','pending') NOT NULL DEFAULT 'pending',
    member_since DATE DEFAULT NULL,
    source ENUM('fuku_soudan','manual') NOT NULL DEFAULT 'manual',
    memo TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS crm_consultations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    staff_id INT NOT NULL,
    consulted_at DATETIME NOT NULL,
    consultation_type VARCHAR(20) NOT NULL DEFAULT '',
    method VARCHAR(10) NOT NULL DEFAULT '',
    is_first TINYINT(1) NOT NULL DEFAULT 0,
    -- 共通フィールド
    content TEXT NOT NULL,
    result TEXT NOT NULL,
    next_action TEXT NOT NULL,
    -- 初回専用
    first_background TEXT DEFAULT NULL,
    first_living_situation TEXT DEFAULT NULL,
    first_urgency ENUM('low','medium','high') DEFAULT NULL,
    -- 2回目以降専用
    repeat_progress TEXT DEFAULT NULL,
    repeat_payment_status VARCHAR(50) DEFAULT NULL,
    repeat_materials TEXT DEFAULT NULL,
    -- 連携
    reserve_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES crm_customers(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES crm_staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS crm_customer_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    tag VARCHAR(50) NOT NULL,
    UNIQUE KEY uniq_ct (customer_id, tag),
    FOREIGN KEY (customer_id) REFERENCES crm_customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
