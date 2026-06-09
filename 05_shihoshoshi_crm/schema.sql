-- =========================================================
-- 司法書士事務所CRM データベーススキーマ
-- =========================================================
CREATE DATABASE IF NOT EXISTS shihoshoshi_crm
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shihoshoshi_crm;

-- ログインユーザー
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name          VARCHAR(100) NOT NULL,
  role          ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 依頼人台帳
CREATE TABLE IF NOT EXISTS clients (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  name_kana    VARCHAR(100),
  gender       ENUM('男','女','法人','その他'),
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
  case_type       ENUM(
    '所有権移転登記（売買）',
    '所有権移転登記（相続）',
    '所有権移転登記（贈与）',
    '抵当権設定',
    '抵当権抹消',
    '商業法人登記',
    '成年後見申立',
    '遺言書作成',
    '相続放棄',
    'その他'
  ) NOT NULL,
  description     TEXT,
  start_date      DATE,
  end_date        DATE,
  status          ENUM('受任中','完了','取下げ','相談中') NOT NULL DEFAULT '受任中',
  assigned_staff  VARCHAR(100),
  registry_office VARCHAR(100),
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 物件情報（不動産登記案件に紐づく）
CREATE TABLE IF NOT EXISTS properties (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  case_id          INT NOT NULL,
  property_type    ENUM('土地','建物','区分建物（マンション）','その他') NOT NULL DEFAULT '土地',
  location         TEXT,
  land_area        DECIMAL(10,2),
  building_area    DECIMAL(10,2),
  lot_number       VARCHAR(100),
  registry_number  VARCHAR(100),
  property_value   BIGINT DEFAULT 0,
  notes            TEXT,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 期日管理
CREATE TABLE IF NOT EXISTS deadlines (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  case_id       INT NOT NULL,
  title         VARCHAR(255) NOT NULL,
  deadline_type ENUM('申請期限','書類提出','依頼人確認','登記完了予定','その他') NOT NULL,
  deadline_date DATE NOT NULL,
  description   TEXT,
  is_completed  TINYINT(1) DEFAULT 0,
  completed_at  TIMESTAMP NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 進捗タイムライン（活動記録）
CREATE TABLE IF NOT EXISTS case_activities (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  case_id       INT NOT NULL,
  activity_type ENUM('面談','書類受取','申請','登記完了','依頼人連絡','調査','その他') NOT NULL,
  content       TEXT NOT NULL,
  activity_date DATE NOT NULL,
  created_by    VARCHAR(100),
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 報酬計算
CREATE TABLE IF NOT EXISTS billing (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  case_id           INT NOT NULL,
  billing_type      ENUM('司法書士報酬','登録免許税','実費','その他') NOT NULL,
  amount            BIGINT NOT NULL DEFAULT 0,
  billing_date      DATE,
  payment_date      DATE,
  is_paid           TINYINT(1) DEFAULT 0,
  notes             TEXT,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 書類チェックリスト
CREATE TABLE IF NOT EXISTS doc_checklist (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  case_id      INT NOT NULL,
  doc_name     VARCHAR(255) NOT NULL,
  is_received  TINYINT(1) DEFAULT 0,
  received_at  DATE NULL,
  notes        TEXT,
  sort_order   INT DEFAULT 0,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
