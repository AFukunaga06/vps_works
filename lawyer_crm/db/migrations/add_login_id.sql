-- ============================================================
-- lc_users にログインIDを追加（メール → ログインID 方式へ変更）
-- 実行: mysql lawyer_crm < db/migrations/add_login_id.sql
-- ============================================================

USE lawyer_crm;

-- 1) login_id カラム追加（既存ユーザーは後でバックフィル）
ALTER TABLE lc_users
    ADD COLUMN login_id VARCHAR(50) NOT NULL DEFAULT '' AFTER tenant_id;

-- 2) バックフィル: 既存ユーザーの login_id は email の @ より前を採用
UPDATE lc_users SET login_id = SUBSTRING_INDEX(email, '@', 1)
 WHERE login_id = '';

-- 3) (tenant_id, login_id) でユニーク制約
ALTER TABLE lc_users
    ADD UNIQUE KEY uniq_tenant_loginid (tenant_id, login_id);

-- 4) 初期管理者の login_id を明示的に 'admin' へ（既に SUBSTRING_INDEX で 'admin' になっているが念のため）
UPDATE lc_users SET login_id = 'admin'
 WHERE email = 'admin@example.com';

-- 注意: 'admin' パスワードのリセットは別途 PHP で
--   password_hash('admin123', PASSWORD_DEFAULT) を生成して UPDATE する。
--   （SQL からは bcrypt ハッシュを直接書けないため）

-- ロールバック:
--   ALTER TABLE lc_users DROP KEY uniq_tenant_loginid;
--   ALTER TABLE lc_users DROP COLUMN login_id;
