-- 弁護士CRM データベース構築SQL
-- 実行: mysql -u root -p < setup.sql

CREATE DATABASE IF NOT EXISTS lawyer_crm DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'lawyer_user'@'localhost' IDENTIFIED BY 'REDACTED_FOR_PUBLIC';
GRANT ALL PRIVILEGES ON lawyer_crm.* TO 'lawyer_user'@'localhost';
FLUSH PRIVILEGES;

USE lawyer_crm;

-- ユーザー（弁護士・スタッフ）
CREATE TABLE IF NOT EXISTS lc_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','lawyer','staff') NOT NULL DEFAULT 'staff',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 依頼者
CREATE TABLE IF NOT EXISTS lc_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    kana VARCHAR(100) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL DEFAULT '',
    tel VARCHAR(30) NOT NULL DEFAULT '',
    tel2 VARCHAR(30) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    birth_date DATE DEFAULT NULL,
    gender ENUM('male','female','other','') NOT NULL DEFAULT '',
    occupation VARCHAR(100) NOT NULL DEFAULT '',
    assigned_lawyer_id INT DEFAULT NULL,
    status ENUM('active','closed','pending') NOT NULL DEFAULT 'active',
    memo TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_lawyer_id) REFERENCES lc_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 案件
CREATE TABLE IF NOT EXISTS lc_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    case_number VARCHAR(50) NOT NULL DEFAULT '',
    case_name VARCHAR(200) NOT NULL,
    case_type ENUM('divorce','inheritance','criminal','civil','labor','real_estate','corporate','debt','other') NOT NULL DEFAULT 'other',
    status ENUM('active','closed','pending','suspended') NOT NULL DEFAULT 'active',
    assigned_lawyer_id INT DEFAULT NULL,
    opened_date DATE DEFAULT NULL,
    closed_date DATE DEFAULT NULL,
    court_name VARCHAR(100) NOT NULL DEFAULT '',
    opponent VARCHAR(100) NOT NULL DEFAULT '',
    memo TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES lc_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_lawyer_id) REFERENCES lc_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 期日管理
CREATE TABLE IF NOT EXISTS lc_deadlines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    deadline_date DATETIME NOT NULL,
    deadline_type ENUM('court','document','meeting','payment','other') NOT NULL DEFAULT 'other',
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    memo TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES lc_cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 活動記録
CREATE TABLE IF NOT EXISTS lc_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT DEFAULT NULL,
    client_id INT NOT NULL,
    user_id INT NOT NULL,
    activity_type ENUM('phone','meeting','email','document','court','other') NOT NULL DEFAULT 'other',
    activity_at DATETIME NOT NULL,
    duration_min INT DEFAULT NULL,
    title VARCHAR(200) NOT NULL DEFAULT '',
    content TEXT NOT NULL,
    result TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES lc_cases(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES lc_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES lc_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 請求管理
CREATE TABLE IF NOT EXISTS lc_billing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    billing_type ENUM('retainer','success','hourly','expense','other') NOT NULL DEFAULT 'retainer',
    title VARCHAR(200) NOT NULL DEFAULT '',
    amount DECIMAL(12,0) NOT NULL DEFAULT 0,
    hours DECIMAL(6,2) DEFAULT NULL,
    billed_date DATE DEFAULT NULL,
    paid_date DATE DEFAULT NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    memo TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES lc_cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 書類管理
CREATE TABLE IF NOT EXISTS lc_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL DEFAULT 0,
    mime_type VARCHAR(100) NOT NULL DEFAULT '',
    uploaded_by INT NOT NULL,
    memo TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES lc_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES lc_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初期管理者アカウント（パスワード: admin123）
INSERT IGNORE INTO lc_users (name, email, password_hash, role)
VALUES ('管理者', 'admin@example.com', 'REDACTED_PASSWORD_HASH', 'admin');
