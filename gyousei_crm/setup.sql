-- 行政書士CRM データベース構築SQL
-- 実行: mysql -u root -p < setup.sql

CREATE DATABASE IF NOT EXISTS gyousei_crm DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'gyousei_user'@'localhost' IDENTIFIED BY 'REDACTED_FOR_PUBLIC';
GRANT ALL PRIVILEGES ON gyousei_crm.* TO 'gyousei_user'@'localhost';
FLUSH PRIVILEGES;

USE gyousei_crm;

-- ユーザー（行政書士・スタッフ）
CREATE TABLE IF NOT EXISTS gc_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    kana VARCHAR(100) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','gyosei','staff') NOT NULL DEFAULT 'staff',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 依頼人台帳
CREATE TABLE IF NOT EXISTS gc_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    kana VARCHAR(100) NOT NULL DEFAULT '',
    company_name VARCHAR(200) NOT NULL DEFAULT '',
    company_kana VARCHAR(200) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL DEFAULT '',
    tel VARCHAR(30) NOT NULL DEFAULT '',
    tel2 VARCHAR(30) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    birth_date DATE DEFAULT NULL,
    gender ENUM('male','female','corporate','') NOT NULL DEFAULT '',
    assigned_user_id INT DEFAULT NULL,
    status ENUM('active','pending','closed') NOT NULL DEFAULT 'active',
    memo TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_user_id) REFERENCES gc_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 許認可案件
CREATE TABLE IF NOT EXISTS gc_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    case_number VARCHAR(50) NOT NULL DEFAULT '',
    case_name VARCHAR(200) NOT NULL,
    case_type ENUM(
        'construction_permit','industrial_waste','residence_status',
        'entertainment','restaurant','secondhand','incorporation',
        'farmland','parking','vehicle','inheritance_proc',
        'permit_renewal','other_permit'
    ) NOT NULL DEFAULT 'other_permit',
    status ENUM('active','pending','suspended','closed') NOT NULL DEFAULT 'active',
    progress_stage ENUM(
        'inquiry','accepted','collecting','checking',
        'preparing','submitted','reviewing','correction',
        'approved','completed','withdrawn'
    ) NOT NULL DEFAULT 'accepted',
    assigned_user_id INT DEFAULT NULL,
    opened_date DATE DEFAULT NULL,
    permit_expiry_date DATE DEFAULT NULL,
    closed_date DATE DEFAULT NULL,
    government_office VARCHAR(100) NOT NULL DEFAULT '',
    application_number VARCHAR(100) NOT NULL DEFAULT '',
    memo TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES gc_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_user_id) REFERENCES gc_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 期日管理
CREATE TABLE IF NOT EXISTS gc_deadlines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    deadline_date DATETIME NOT NULL,
    deadline_type ENUM('submission','meeting','payment','renewal','other') NOT NULL DEFAULT 'other',
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    memo TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES gc_cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 必要書類チェックリスト
CREATE TABLE IF NOT EXISTS gc_doc_checklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    doc_name VARCHAR(200) NOT NULL,
    is_obtained TINYINT(1) NOT NULL DEFAULT 0,
    note VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES gc_cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 進捗記録
CREATE TABLE IF NOT EXISTS gc_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    user_id INT NOT NULL,
    progress_type ENUM('note','contact','submission','approval','correction','other') NOT NULL DEFAULT 'note',
    content TEXT NOT NULL,
    recorded_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES gc_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES gc_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 報酬管理
CREATE TABLE IF NOT EXISTS gc_billing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    billing_type ENUM('retainer','success','expense','other') NOT NULL DEFAULT 'retainer',
    title VARCHAR(200) NOT NULL DEFAULT '',
    amount DECIMAL(12,0) NOT NULL DEFAULT 0,
    billed_date DATE DEFAULT NULL,
    paid_date DATE DEFAULT NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    memo TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES gc_cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初期管理者アカウント（パスワード: admin123）
INSERT IGNORE INTO gc_users (name, kana, email, password_hash, role)
VALUES ('管理者', 'かんりしゃ', 'admin@example.com', 'REDACTED_PASSWORD_HASH', 'admin');
