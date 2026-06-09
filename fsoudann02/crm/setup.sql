-- 弁護士CRM テーブル追加（fhouritu_soudann DBに追加）
USE fhouritu_soudann;

-- 依頼者
CREATE TABLE IF NOT EXISTS crm_clients (
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
    status ENUM('active','pending','closed') NOT NULL DEFAULT 'active',
    memo TEXT NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 案件
CREATE TABLE IF NOT EXISTS crm_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    case_number VARCHAR(50) NOT NULL DEFAULT '',
    case_name VARCHAR(200) NOT NULL,
    case_type ENUM('divorce','inheritance','criminal','civil','labor','real_estate','corporate','debt','other') NOT NULL DEFAULT 'other',
    status ENUM('active','pending','suspended','closed') NOT NULL DEFAULT 'active',
    opened_date DATE DEFAULT NULL,
    closed_date DATE DEFAULT NULL,
    court_name VARCHAR(100) NOT NULL DEFAULT '',
    opponent VARCHAR(100) NOT NULL DEFAULT '',
    memo TEXT NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES crm_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 期日
CREATE TABLE IF NOT EXISTS crm_deadlines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    deadline_date DATETIME NOT NULL,
    deadline_type ENUM('court','document','meeting','payment','other') NOT NULL DEFAULT 'other',
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    memo TEXT NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES crm_cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 活動記録
CREATE TABLE IF NOT EXISTS crm_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT DEFAULT NULL,
    client_id INT NOT NULL,
    activity_type ENUM('phone','meeting','email','document','court','other') NOT NULL DEFAULT 'other',
    activity_at DATETIME NOT NULL,
    duration_min INT DEFAULT NULL,
    title VARCHAR(200) NOT NULL DEFAULT '',
    content TEXT NOT NULL DEFAULT '',
    result TEXT NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES crm_cases(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES crm_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 請求
CREATE TABLE IF NOT EXISTS crm_billing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    billing_type ENUM('retainer','success','hourly','expense','other') NOT NULL DEFAULT 'retainer',
    title VARCHAR(200) NOT NULL DEFAULT '',
    amount DECIMAL(12,0) NOT NULL DEFAULT 0,
    hours DECIMAL(6,2) DEFAULT NULL,
    billed_date DATE DEFAULT NULL,
    paid_date DATE DEFAULT NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    memo TEXT NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES crm_cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 書類
CREATE TABLE IF NOT EXISTS crm_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL DEFAULT 0,
    mime_type VARCHAR(100) NOT NULL DEFAULT '',
    memo VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES crm_cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
