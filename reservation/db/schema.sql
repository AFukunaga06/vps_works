-- =====================================================================
-- 顧客予約システム（弁護士向け）スキーマ
-- B案: 既存 lawyer_crm DB に lc_ プレフィックスで3テーブル追加
--   lc_settings_reserve   : 営業設定（テナント毎1行）
--   lc_appointment_slots  : 例外日（休業 / 臨時営業 / オーバーライド）
--   lc_appointments       : 予約レコード
--
-- 認証は既存 lc_users を流用するため、本予約システム用の admins テーブルは作らない。
-- ※ lawyer_crm はマルチテナント。tenant_id を必須化し、業務テーブルと足並みを揃える。
-- =====================================================================

USE lawyer_crm;

-- ---------------------------------------------------------------------
-- lc_settings_reserve : 予約システム営業設定（tenant毎1行）
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lc_settings_reserve (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  business_name VARCHAR(150) NOT NULL DEFAULT '法律事務所',
  slot_minutes INT NOT NULL DEFAULT 30,                        -- 1枠の長さ（分）: 15/30/60
  open_time TIME NOT NULL DEFAULT '10:00:00',
  close_time TIME NOT NULL DEFAULT '18:00:00',
  open_days VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5',          -- 0=日,1=月,...,6=土
  buffer_minutes INT NOT NULL DEFAULT 0,                       -- 枠間バッファ
  advance_days INT NOT NULL DEFAULT 30,                        -- 何日先まで予約可
  cancel_deadline_hours INT NOT NULL DEFAULT 24,
  contact_email VARCHAR(255) NOT NULL DEFAULT '',
  contact_tel VARCHAR(30) NOT NULL DEFAULT '',
  consultation_types VARCHAR(500) NOT NULL DEFAULT '一般相談,離婚,相続,刑事,労働,債務整理,その他',
  notice_message TEXT,                                         -- 公開ページ上部の案内文
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- lc_appointment_slots : 例外日（祝日・臨時休業 / 臨時営業）
--   type='closed' : その日は休業（営業日でも閉じる）
--   type='open'   : その日は臨時営業（open_time/close_time 指定可）
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lc_appointment_slots (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  exception_date DATE NOT NULL,
  type ENUM('closed','open') NOT NULL DEFAULT 'closed',
  open_time TIME NULL,
  close_time TIME NULL,
  memo VARCHAR(255) DEFAULT '',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tenant_date (tenant_id, exception_date),
  KEY idx_date (exception_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- lc_appointments : 予約レコード
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lc_appointments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  reservation_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  status ENUM('pending','confirmed','canceled','noshow')
         NOT NULL DEFAULT 'pending',
  customer_name VARCHAR(100) NOT NULL,
  customer_kana VARCHAR(100) DEFAULT '',
  customer_tel VARCHAR(30) DEFAULT '',
  customer_email VARCHAR(255) DEFAULT '',
  consultation_type VARCHAR(50) DEFAULT '',
  memo TEXT,
  client_id INT NULL,                       -- lc_clients.id への参照（任意・将来連携用）
  ip VARCHAR(45) DEFAULT '',
  user_agent VARCHAR(255) DEFAULT '',
  admin_memo TEXT,                          -- 管理者用メモ（顧客には見えない）
  handled_by INT NULL,                      -- 担当者 lc_users.id
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- 二重予約防止用の生成カラム
  -- アクティブな予約 (pending/confirmed) のみ key を生成し、それ以外は NULL
  -- → UNIQUE INDEX 上で NULL は重複扱いされないため canceled 行が残っても再予約可
  active_slot_key VARCHAR(40) GENERATED ALWAYS AS (
      CASE WHEN status IN ('pending','confirmed')
           THEN CONCAT(reservation_date, ' ', start_time)
      END
  ) VIRTUAL,
  KEY idx_tenant_date (tenant_id, reservation_date),
  KEY idx_status (status),
  KEY idx_client (client_id),
  -- 二重予約防止: 同一テナント・同一日時で active な予約は1つだけ
  UNIQUE KEY uniq_active_slot (tenant_id, active_slot_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 初期データ（tenant_id=1 = 初期テナント＝弁護士事務所）
-- ---------------------------------------------------------------------
INSERT INTO lc_settings_reserve
  (tenant_id, business_name, slot_minutes, open_time, close_time, open_days,
   advance_days, contact_email, notice_message)
VALUES
  (1, '法律事務所（テスト）', 30, '10:00:00', '18:00:00', '1,2,3,4,5',
   30, 'admin@example.com',
   'ご相談予約フォームです。空いている時間枠をお選びください。初回相談は60分以内とさせていただきます。')
ON DUPLICATE KEY UPDATE business_name = VALUES(business_name);
