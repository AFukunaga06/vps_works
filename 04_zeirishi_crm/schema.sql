-- ============================================================
-- 税理士事務所向け顧問先管理システム schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS zeirishi_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE zeirishi_crm;

-- 顧問先台帳
CREATE TABLE clients (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    company_name    VARCHAR(100) NOT NULL COMMENT '会社名・屋号',
    company_kana    VARCHAR(100)          COMMENT '会社名カナ',
    representative  VARCHAR(50)           COMMENT '代表者名',
    zip_code        VARCHAR(8)            COMMENT '郵便番号',
    address         VARCHAR(200)          COMMENT '住所',
    phone           VARCHAR(20)           COMMENT '電話番号',
    email           VARCHAR(100)          COMMENT 'メールアドレス',
    industry        VARCHAR(50)           COMMENT '業種',
    corp_type       ENUM('法人','個人') NOT NULL DEFAULT '法人',
    fiscal_month    TINYINT NOT NULL DEFAULT 3 COMMENT '決算月（1-12）',
    contract_start  DATE                  COMMENT '顧問契約開始日',
    monthly_fee     INT     NOT NULL DEFAULT 0 COMMENT '月次顧問料（円）',
    notes           TEXT                  COMMENT '備考',
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 申告期限管理
CREATE TABLE tax_deadlines (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    client_id   INT NOT NULL,
    tax_type    VARCHAR(50) NOT NULL COMMENT '申告種別（法人税・消費税など）',
    fiscal_year VARCHAR(20) NOT NULL COMMENT '対象年度（例: 2024年3月期）',
    deadline    DATE NOT NULL            COMMENT '申告期限',
    filed_at    DATE                     COMMENT '申告完了日',
    status      ENUM('pending','in_progress','filed','overdue') NOT NULL DEFAULT 'pending',
    notes       TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 依頼タスク
CREATE TABLE tasks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    client_id   INT NOT NULL,
    title       VARCHAR(200) NOT NULL COMMENT 'タスク名',
    category    VARCHAR(50)           COMMENT 'カテゴリ（月次・決算・その他）',
    due_date    DATE                  COMMENT '期限',
    priority    ENUM('high','medium','low') NOT NULL DEFAULT 'medium',
    status      ENUM('todo','in_progress','done','cancelled') NOT NULL DEFAULT 'todo',
    assignee    VARCHAR(50)           COMMENT '担当者名',
    notes       TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 月次報酬管理
CREATE TABLE monthly_fees (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    client_id   INT NOT NULL,
    `year_month`  CHAR(7) NOT NULL COMMENT 'YYYY-MM',
    fee_amount  INT NOT NULL DEFAULT 0 COMMENT '請求額（円）',
    paid_at     DATE                  COMMENT '入金日',
    invoice_no  VARCHAR(30)           COMMENT '請求書番号',
    notes       TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_client_month (client_id, `year_month`),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 進捗コメント（タイムライン）
CREATE TABLE progress_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    client_id   INT NOT NULL,
    task_id     INT                   COMMENT '関連タスクID（任意）',
    author      VARCHAR(50)           COMMENT '記録者',
    content     TEXT NOT NULL,
    logged_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id)   REFERENCES tasks(id)   ON DELETE SET NULL
) ENGINE=InnoDB;
