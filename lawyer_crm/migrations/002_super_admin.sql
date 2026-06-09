-- ============================================================
-- スーパー管理者フラグ追加
-- 既存 lc_users に is_super_admin カラムを追加し、
-- 全テナント横断で運営者権限を持つユーザーを表す。
-- ============================================================
USE lawyer_crm;

ALTER TABLE lc_users
    ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER role,
    ADD KEY idx_super_admin (is_super_admin);

-- 既存のtenant 1のadminを super_admin に昇格（運営者用アカウント）
UPDATE lc_users SET is_super_admin = 1
WHERE tenant_id = 1 AND role = 'admin' AND email = 'admin@example.com';
