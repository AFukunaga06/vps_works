
USE at_system01;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS workdays;
DROP VIEW IF EXISTS v_daily;
DROP VIEW IF EXISTS v_monthly;
DROP VIEW IF EXISTS v_yearly;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE members (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(50)  NOT NULL,
  kana        VARCHAR(50)  NOT NULL DEFAULT '',
  group_name  VARCHAR(50)  NOT NULL DEFAULT '',
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id   INT UNSIGNED NOT NULL,
  attend_date DATE         NOT NULL,
  status      ENUM('出席','欠席','遅刻','早退') NOT NULL DEFAULT '出席',
  note        VARCHAR(200) NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member_date (member_id, attend_date),
  FOREIGN KEY (member_id) REFERENCES members(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE VIEW v_daily AS
  SELECT attend_date,
         COUNT(*) AS total,
         SUM(status='出席') AS present,
         SUM(status='欠席') AS absent,
         SUM(status='遅刻') AS late,
         SUM(status='早退') AS early_leave
  FROM attendance
  GROUP BY attend_date;

CREATE VIEW v_monthly AS
  SELECT DATE_FORMAT(attend_date,'%Y-%m') AS ym,
         COUNT(*) AS total,
         SUM(status='出席') AS present,
         SUM(status='欠席') AS absent,
         SUM(status='遅刻') AS late,
         SUM(status='早退') AS early_leave
  FROM attendance
  GROUP BY ym;

CREATE VIEW v_yearly AS
  SELECT YEAR(attend_date) AS yr,
         COUNT(*) AS total,
         SUM(status='出席') AS present,
         SUM(status='欠席') AS absent,
         SUM(status='遅刻') AS late,
         SUM(status='早退') AS early_leave
  FROM attendance
  GROUP BY yr;
