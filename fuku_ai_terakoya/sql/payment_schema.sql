-- フクのAI寺子屋 決済関連スキーマ（Square連携）
USE fuku_ai_terakoya;

-- 料金プランマスター（最初は固定2件をseedするだけ。将来増やす場合に拡張可能な構造）
CREATE TABLE IF NOT EXISTS plans (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(32)  NOT NULL UNIQUE,                      -- 'single' / 'monthly'
  name         VARCHAR(100) NOT NULL,                             -- 表示名
  amount       INT UNSIGNED NOT NULL,                             -- 円
  type         ENUM('single','subscription') NOT NULL,
  monthly_quota INT UNSIGNED DEFAULT NULL,                        -- subscription時のみ。月の受講可能回数
  description  TEXT         DEFAULT NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order   INT          NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 決済履歴（単発・月謝の初回・月謝の毎月分すべてを記録）
CREATE TABLE IF NOT EXISTS payments (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id         INT UNSIGNED DEFAULT NULL,
  plan_id            INT UNSIGNED DEFAULT NULL,
  subscription_id    INT UNSIGNED DEFAULT NULL,                  -- 月謝の場合の紐付け
  amount             INT UNSIGNED NOT NULL,
  currency           CHAR(3)      NOT NULL DEFAULT 'JPY',
  status             ENUM('pending','completed','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  square_environment ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
  square_payment_link_id VARCHAR(64)  DEFAULT NULL,              -- 決済リンクID
  square_order_id    VARCHAR(64)  DEFAULT NULL,                  -- Order ID
  square_payment_id  VARCHAR(64)  DEFAULT NULL,                  -- Payment ID（webhook受信後）
  square_checkout_url VARCHAR(500) DEFAULT NULL,                 -- リダイレクト先URL
  customer_name      VARCHAR(100) NOT NULL,
  customer_email     VARCHAR(255) NOT NULL,
  paid_at            DATETIME     DEFAULT NULL,
  source_ip          VARCHAR(45)  DEFAULT NULL,
  user_agent         VARCHAR(255) DEFAULT NULL,
  raw_response       MEDIUMTEXT   DEFAULT NULL,                  -- API応答 JSON保存（デバッグ用）
  admin_note         TEXT         DEFAULT NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_payments_student      (student_id),
  KEY idx_payments_status       (status),
  KEY idx_payments_subscription (subscription_id),
  KEY idx_payments_link         (square_payment_link_id),
  KEY idx_payments_payment      (square_payment_id),
  CONSTRAINT fk_payments_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_payments_plan    FOREIGN KEY (plan_id)    REFERENCES plans(id)    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 月謝サブスクリプション（Squareの公式Subscriptionsは使わず、毎月手動で決済リンクを送る半自動方式）
CREATE TABLE IF NOT EXISTS subscriptions (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id      INT UNSIGNED NOT NULL,
  plan_id         INT UNSIGNED NOT NULL,
  status          ENUM('active','paused','cancelled') NOT NULL DEFAULT 'active',
  start_date      DATE         NOT NULL,
  end_date        DATE         DEFAULT NULL,
  next_billing_date DATE       DEFAULT NULL,
  cancel_reason   TEXT         DEFAULT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_subscriptions_student (student_id),
  KEY idx_subscriptions_status  (status),
  CONSTRAINT fk_subscriptions_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT fk_subscriptions_plan    FOREIGN KEY (plan_id)    REFERENCES plans(id)    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 受講消化記録（月謝の使用回数管理用）
CREATE TABLE IF NOT EXISTS lesson_usages (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id       INT UNSIGNED NOT NULL,
  subscription_id  INT UNSIGNED DEFAULT NULL,
  payment_id       INT UNSIGNED DEFAULT NULL,                    -- 単発払いの場合
  reservation_id   INT UNSIGNED DEFAULT NULL,
  used_date        DATE         NOT NULL,
  note             TEXT         DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lu_student      (student_id),
  KEY idx_lu_subscription (subscription_id),
  KEY idx_lu_used_date    (used_date),
  CONSTRAINT fk_lu_student      FOREIGN KEY (student_id)      REFERENCES students(id)      ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT fk_lu_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_lu_payment      FOREIGN KEY (payment_id)      REFERENCES payments(id)      ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_lu_reservation  FOREIGN KEY (reservation_id)  REFERENCES reservations(id)  ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhook受信ログ（デバッグ・冪等性確保用）
CREATE TABLE IF NOT EXISTS payment_webhook_logs (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id         VARCHAR(64)  DEFAULT NULL,                     -- Square Event ID（重複検知用）
  event_type       VARCHAR(64)  DEFAULT NULL,                     -- payment.updated 等
  payment_id       INT UNSIGNED DEFAULT NULL,
  signature_valid  TINYINT(1)   NOT NULL DEFAULT 0,
  processed        TINYINT(1)   NOT NULL DEFAULT 0,
  raw_body         MEDIUMTEXT   DEFAULT NULL,
  raw_headers      TEXT         DEFAULT NULL,
  error_message    TEXT         DEFAULT NULL,
  source_ip        VARCHAR(45)  DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pwl_event (event_id),
  KEY idx_pwl_payment (payment_id),
  KEY idx_pwl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- payments.subscription_id の外部キー（subscriptions テーブル作成後に追加）
ALTER TABLE payments
  ADD CONSTRAINT fk_payments_subscription
  FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- ====== シードデータ ======
INSERT INTO plans (code, name, amount, type, monthly_quota, description, sort_order)
VALUES
  ('single',  '単発受講',     1500, 'single',       NULL, '1回 1,500円。都度払いプラン。', 1),
  ('monthly', '月謝（月4回）', 5000, 'subscription',    4, '月4回までの受講で 5,000円。月をまたぐと回数リセット。', 2)
ON DUPLICATE KEY UPDATE
  name=VALUES(name), amount=VALUES(amount), type=VALUES(type),
  monthly_quota=VALUES(monthly_quota), description=VALUES(description), sort_order=VALUES(sort_order);
