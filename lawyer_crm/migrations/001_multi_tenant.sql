-- ============================================================
-- Multi-tenant 化マイグレーション
-- 実行: mysql -u root -p lawyer_crm < migrations/001_multi_tenant.sql
-- ============================================================

USE lawyer_crm;

-- ------------------------------------------------------------
-- 1) テナント（事務所）テーブル
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lc_tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,                              -- 事務所名
    slug VARCHAR(50) NOT NULL,                               -- URL識別子（サブドメイン or パス）
    contact_email VARCHAR(255) NOT NULL,                     -- 代表メール
    contact_tel VARCHAR(30) NOT NULL DEFAULT '',
    contact_name VARCHAR(100) NOT NULL DEFAULT '',
    plan ENUM('solo','standard','pro','enterprise') NOT NULL DEFAULT 'solo',
    billing_cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    status ENUM('trial','active','past_due','suspended','canceled') NOT NULL DEFAULT 'trial',
    trial_ends_at DATETIME DEFAULT NULL,
    subscription_id VARCHAR(100) NOT NULL DEFAULT '',        -- Stripe等の決済ID（将来）
    max_users INT NOT NULL DEFAULT 1,
    max_clients INT DEFAULT 50,                              -- NULL=無制限
    max_cases INT DEFAULT 50,
    storage_quota_mb INT NOT NULL DEFAULT 5000,
    storage_used_mb INT NOT NULL DEFAULT 0,
    feature_flags JSON DEFAULT NULL,                         -- {gcal:true, api:false, ...}
    memo TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    canceled_at DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_slug (slug),
    KEY idx_status (status),
    KEY idx_trial (trial_ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2) デフォルトテナントを作成（既存データの引き受け先）
-- ------------------------------------------------------------
INSERT INTO lc_tenants (id, name, slug, contact_email, plan, status, trial_ends_at, max_users, max_clients, max_cases, storage_quota_mb)
VALUES (1, '初期テナント', 'default', 'admin@example.com', 'pro', 'active', NULL, 999, NULL, NULL, 500000)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ------------------------------------------------------------
-- 3) 既存テーブルに tenant_id カラム追加
--    （既に存在する場合はエラーにならないよう IGNORE で）
-- ------------------------------------------------------------

-- lc_users
ALTER TABLE lc_users
    ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id,
    ADD KEY idx_tenant (tenant_id);
UPDATE lc_users SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL;
-- email一意制約をテナント単位に変更
ALTER TABLE lc_users DROP INDEX uniq_email;
ALTER TABLE lc_users ADD UNIQUE KEY uniq_tenant_email (tenant_id, email);
ALTER TABLE lc_users ADD CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES lc_tenants(id) ON DELETE CASCADE;

-- lc_clients
ALTER TABLE lc_clients
    ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id,
    ADD KEY idx_tenant (tenant_id);
UPDATE lc_clients SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL;
ALTER TABLE lc_clients ADD CONSTRAINT fk_clients_tenant FOREIGN KEY (tenant_id) REFERENCES lc_tenants(id) ON DELETE CASCADE;

-- lc_cases
ALTER TABLE lc_cases
    ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id,
    ADD KEY idx_tenant (tenant_id);
UPDATE lc_cases SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL;
ALTER TABLE lc_cases ADD CONSTRAINT fk_cases_tenant FOREIGN KEY (tenant_id) REFERENCES lc_tenants(id) ON DELETE CASCADE;

-- lc_deadlines
ALTER TABLE lc_deadlines
    ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id,
    ADD KEY idx_tenant (tenant_id);
UPDATE lc_deadlines SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL;
ALTER TABLE lc_deadlines ADD CONSTRAINT fk_deadlines_tenant FOREIGN KEY (tenant_id) REFERENCES lc_tenants(id) ON DELETE CASCADE;

-- lc_activities
ALTER TABLE lc_activities
    ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id,
    ADD KEY idx_tenant (tenant_id);
UPDATE lc_activities SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL;
ALTER TABLE lc_activities ADD CONSTRAINT fk_activities_tenant FOREIGN KEY (tenant_id) REFERENCES lc_tenants(id) ON DELETE CASCADE;

-- lc_billing
ALTER TABLE lc_billing
    ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id,
    ADD KEY idx_tenant (tenant_id);
UPDATE lc_billing SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL;
ALTER TABLE lc_billing ADD CONSTRAINT fk_billing_tenant FOREIGN KEY (tenant_id) REFERENCES lc_tenants(id) ON DELETE CASCADE;

-- lc_documents
ALTER TABLE lc_documents
    ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id,
    ADD KEY idx_tenant (tenant_id);
UPDATE lc_documents SET tenant_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL;
ALTER TABLE lc_documents ADD CONSTRAINT fk_documents_tenant FOREIGN KEY (tenant_id) REFERENCES lc_tenants(id) ON DELETE CASCADE;

-- ------------------------------------------------------------
-- 4) 課金ログ（後の集計・履歴用）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lc_tenant_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    invoice_no VARCHAR(50) NOT NULL DEFAULT '',
    plan ENUM('solo','standard','pro','enterprise') NOT NULL,
    billing_cycle ENUM('monthly','yearly') NOT NULL,
    amount DECIMAL(10,0) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    status ENUM('draft','open','paid','void','uncollectible') NOT NULL DEFAULT 'draft',
    paid_at DATETIME DEFAULT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT '',
    external_id VARCHAR(100) NOT NULL DEFAULT '',
    memo TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tenant (tenant_id),
    KEY idx_status (status),
    FOREIGN KEY (tenant_id) REFERENCES lc_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 5) 監査ログ（Standard以上の機能）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lc_audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    action VARCHAR(50) NOT NULL,                             -- login, create_client, update_case, ...
    target_type VARCHAR(30) NOT NULL DEFAULT '',             -- client, case, deadline, ...
    target_id INT DEFAULT NULL,
    detail JSON DEFAULT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tenant_created (tenant_id, created_at),
    KEY idx_user (user_id),
    FOREIGN KEY (tenant_id) REFERENCES lc_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
