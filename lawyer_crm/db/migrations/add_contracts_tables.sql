-- ============================================================
-- 契約書AIレビュー機能 テーブル追加マイグレーション
-- 実行: mysql -u root -p lawyer_crm < db/migrations/add_contracts_tables.sql
--
-- 設計メモ:
--   本CRMはマルチテナントSaaS（全業務テーブルが lc_ プレフィックス + tenant_id 必須）。
--   仕様書の DDL（contracts / contract_clauses, tenant_id なし）をそのまま使うと
--   他テナント（他事務所）の契約書が見えてしまうため、既存規約に合わせて
--     - テーブル名を lc_contracts / lc_contract_clauses
--     - tenant_id 列を追加し lc_tenants へ FK
--   とし、全クエリを tenant_id でスコープする。
--
--   追加内容は新規テーブル2つのみ（既存テーブルへの ALTER / DROP なし = 非破壊）。
--   ロールバックは末尾の DROP 文（コメントアウト）参照。
-- ============================================================

USE lawyer_crm;

-- ------------------------------------------------------------
-- 1) 契約書（アップロード単位）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lc_contracts (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id         INT NOT NULL,
    client_id         INT DEFAULT NULL,                 -- 紐付け依頼者（lc_clients）
    case_id           INT DEFAULT NULL,                 -- 紐付け案件（任意）
    uploaded_by       INT DEFAULT NULL,                 -- アップロードした弁護士/スタッフ
    file_name         VARCHAR(255) NOT NULL,            -- 元ファイル名
    stored_name       VARCHAR(255) NOT NULL,            -- 保存名 {id}_{元ファイル名}
    file_path         VARCHAR(500) NOT NULL,            -- サーバ保存パス
    contract_type     VARCHAR(50)  DEFAULT NULL,        -- 業務委託 / 秘密保持 / 売買 / その他
    party_side        ENUM('甲','乙') NOT NULL DEFAULT '甲',
    summary           TEXT NULL,                        -- AI要約
    total_risk_high   INT NOT NULL DEFAULT 0,
    total_risk_medium INT NOT NULL DEFAULT 0,
    total_risk_low    INT NOT NULL DEFAULT 0,
    status            ENUM('uploaded','analyzing','done','reviewed','error') NOT NULL DEFAULT 'uploaded',
    error_message     VARCHAR(500) NULL,                -- 解析失敗時のメッセージ
    uploaded_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    analyzed_at       DATETIME NULL,
    KEY idx_tenant (tenant_id),
    KEY idx_client (client_id),
    KEY idx_status (status),
    CONSTRAINT fk_contracts_tenant FOREIGN KEY (tenant_id)   REFERENCES lc_tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_contracts_client FOREIGN KEY (client_id)   REFERENCES lc_clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_contracts_case   FOREIGN KEY (case_id)     REFERENCES lc_cases(id)   ON DELETE SET NULL,
    CONSTRAINT fk_contracts_user   FOREIGN KEY (uploaded_by) REFERENCES lc_users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2) 条項（契約書を「第◯条」単位に分割した分析結果）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lc_contract_clauses (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id          INT NOT NULL,                    -- 親 contract と同一（スコープ高速化用に冗長保持）
    contract_id        INT NOT NULL,
    clause_number      VARCHAR(20)  DEFAULT NULL,       -- 例: 第8条
    clause_title       VARCHAR(255) DEFAULT NULL,       -- 例: 損害賠償
    clause_text        TEXT NULL,                       -- 条項本文
    risk_level         ENUM('高','中','低','なし') NOT NULL DEFAULT 'なし',
    risk_type          VARCHAR(100) DEFAULT NULL,       -- リスク類型（例: 損害賠償の免除）
    issue              TEXT NULL,                       -- 問題点
    suggestion         TEXT NULL,                       -- 修正提案
    related_law        VARCHAR(255) DEFAULT NULL,       -- 関連法令
    reviewed_by_lawyer TINYINT(1) NOT NULL DEFAULT 0,   -- 弁護士確認済みフラグ
    lawyer_note        TEXT NULL,                       -- 弁護士メモ
    sort_order         INT NOT NULL DEFAULT 0,          -- 表示順（条項の登場順）
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tenant (tenant_id),
    KEY idx_contract (contract_id),
    KEY idx_risk (risk_level),
    CONSTRAINT fk_clauses_tenant   FOREIGN KEY (tenant_id)   REFERENCES lc_tenants(id)   ON DELETE CASCADE,
    CONSTRAINT fk_clauses_contract FOREIGN KEY (contract_id) REFERENCES lc_contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- ロールバック（必要時に手動実行）:
--   DROP TABLE IF EXISTS lc_contract_clauses;
--   DROP TABLE IF EXISTS lc_contracts;
-- ------------------------------------------------------------
