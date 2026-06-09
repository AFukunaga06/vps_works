-- ============================================================
-- 業種（vertical）フィールド追加
-- 弁護士/税理士/司法書士/行政書士など、業種別に案件種別マスタを切替える。
-- ============================================================
USE lawyer_crm;

ALTER TABLE lc_tenants
    ADD COLUMN vertical VARCHAR(30) NOT NULL DEFAULT 'lawyer' AFTER name,
    ADD KEY idx_vertical (vertical);

-- 案件テーブルの case_type を ENUM から VARCHAR に変更（業種ごとに値が異なるため）
ALTER TABLE lc_cases
    MODIFY COLUMN case_type VARCHAR(50) NOT NULL DEFAULT 'other';

-- 既存テナント（=弁護士）はそのまま
UPDATE lc_tenants SET vertical='lawyer' WHERE vertical='' OR vertical IS NULL;
