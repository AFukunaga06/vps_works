-- ============================================================
-- 見積算出機能 テーブル追加マイグレーション
-- 実行: mysql lawyer_crm < db/migrations/add_quotes_table.sql
--
-- 既存規約準拠: lc_ プレフィックス + tenant_id 必須
-- 階層料金（旧日弁連基準）はアプリ側のロジック（includes/quote_calc.php）で
-- 計算し、本テーブルには結果の金額のみ保存する（後でカスタマイズ可能）。
-- ============================================================

USE lawyer_crm;

CREATE TABLE IF NOT EXISTS lc_quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    client_id INT DEFAULT NULL,
    case_id   INT DEFAULT NULL,
    created_by INT DEFAULT NULL,

    quote_number VARCHAR(50) NOT NULL DEFAULT '',    -- Q-YYYYMMDD-{id} 自動採番
    title VARCHAR(200) NOT NULL DEFAULT '',          -- 件名
    case_type VARCHAR(50) DEFAULT NULL,              -- 案件種別

    economic_benefit_yen BIGINT NOT NULL DEFAULT 0,  -- 経済的利益（基礎額）

    retainer_yen BIGINT NOT NULL DEFAULT 0,          -- 着手金（計算値）
    retainer_rate_text VARCHAR(50) DEFAULT '',       -- 例: '8%', '5% + 9万円'

    success_yen BIGINT NOT NULL DEFAULT 0,           -- 報酬金（見込・計算値）
    success_rate_text VARCHAR(50) DEFAULT '',        -- 例: '16%', '10% + 18万円'

    consultation_yen BIGINT NOT NULL DEFAULT 0,      -- 相談料
    expense_yen BIGINT NOT NULL DEFAULT 0,           -- 実費（印紙・郵券等）

    subtotal_yen BIGINT NOT NULL DEFAULT 0,          -- 税抜小計
    tax_yen BIGINT NOT NULL DEFAULT 0,               -- 消費税
    total_yen BIGINT NOT NULL DEFAULT 0,             -- 税込総額

    notes TEXT NULL,
    valid_until DATE NULL,                           -- 見積有効期限
    status ENUM('draft','sent','accepted','rejected','canceled')
           NOT NULL DEFAULT 'draft',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_tenant (tenant_id),
    KEY idx_client (client_id),
    KEY idx_status (status),
    KEY idx_created (created_at),
    CONSTRAINT fk_quotes_tenant FOREIGN KEY (tenant_id) REFERENCES lc_tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_quotes_client FOREIGN KEY (client_id) REFERENCES lc_clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_quotes_case   FOREIGN KEY (case_id)   REFERENCES lc_cases(id)   ON DELETE SET NULL,
    CONSTRAINT fk_quotes_user   FOREIGN KEY (created_by) REFERENCES lc_users(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ロールバック:
--   DROP TABLE IF EXISTS lc_quotes;
