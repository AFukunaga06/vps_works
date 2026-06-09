-- =========================================================
-- 弁護士事務所CRM データベーススキーマ
-- =========================================================
CREATE DATABASE IF NOT EXISTS lawyer_crm
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lawyer_crm;

-- ログインユーザー（弁護士・スタッフ）
CREATE TABLE IF NOT EXISTS users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name         VARCHAR(100) NOT NULL,
  role         ENUM('admin','lawyer','staff') NOT NULL DEFAULT 'staff',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 依頼人台帳
CREATE TABLE IF NOT EXISTS clients (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  name_kana    VARCHAR(100),
  gender       ENUM('男','女','その他'),
  birth_date   DATE,
  phone        VARCHAR(20),
  email        VARCHAR(255),
  postal_code  VARCHAR(10),
  address      TEXT,
  notes        TEXT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 案件管理
CREATE TABLE IF NOT EXISTS cases (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  client_id       INT NOT NULL,
  case_number     VARCHAR(50),
  title           VARCHAR(255) NOT NULL,
  case_type       ENUM('離婚','相続','刑事','民事','労働','交通事故','債務整理','不動産','建設・請負','家族・親権','その他') NOT NULL,
  description     TEXT,
  start_date      DATE,
  end_date        DATE,
  status          ENUM('受任中','完了','取下げ','相談中') NOT NULL DEFAULT '受任中',
  assigned_lawyer VARCHAR(100),
  court_name      VARCHAR(100),
  retainer_fee    INT DEFAULT 0,
  success_fee     INT DEFAULT 0,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 進捗タイムライン（活動記録）
CREATE TABLE IF NOT EXISTS case_activities (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  case_id       INT NOT NULL,
  activity_type ENUM('面談','書類作成','裁判','交渉','電話','メール','その他') NOT NULL,
  content       TEXT NOT NULL,
  activity_date DATE NOT NULL,
  created_by    VARCHAR(100),
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 期日管理
CREATE TABLE IF NOT EXISTS deadlines (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  case_id       INT NOT NULL,
  title         VARCHAR(255) NOT NULL,
  deadline_type ENUM('裁判期日','提出期限','打合せ','申請期限','その他') NOT NULL,
  deadline_date DATE NOT NULL,
  description   TEXT,
  is_completed  TINYINT(1) DEFAULT 0,
  completed_at  TIMESTAMP NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 着手金・成功報酬管理
CREATE TABLE IF NOT EXISTS billing (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  case_id      INT NOT NULL,
  billing_type ENUM('着手金','成功報酬','実費','顧問料','その他') NOT NULL,
  amount       INT NOT NULL,
  billing_date DATE,
  payment_date DATE,
  is_paid      TINYINT(1) DEFAULT 0,
  notes        TEXT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 書類管理
CREATE TABLE IF NOT EXISTS documents (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  case_id       INT NOT NULL,
  document_name VARCHAR(255) NOT NULL,
  document_type ENUM('訴状','答弁書','準備書面','証拠','判決','和解書','委任状','その他') NOT NULL DEFAULT 'その他',
  file_path     VARCHAR(500),
  file_size     INT,
  mime_type     VARCHAR(100),
  notes         TEXT,
  uploaded_by   VARCHAR(100),
  upload_date   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
