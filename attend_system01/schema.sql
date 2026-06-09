-- schema.sql
CREATE DATABASE IF NOT EXISTS at_system01
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE at_system01;
-- 名簿（年/男女/並び順）
CREATE TABLE IF NOT EXISTS members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  year INT NOT NULL,
  gender ENUM('male','female') NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_members (year, gender, name),
  KEY idx_members (year, gender, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 出勤日（年/日付）
CREATE TABLE IF NOT EXISTS workdays (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  year INT NOT NULL,
  date DATE NOT NULL,
  is_workday TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_workdays (year, date),
  KEY idx_workdays (year, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 出欠（年/日付/男女/名前で一意）
CREATE TABLE IF NOT EXISTS attendance (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  year INT NOT NULL,
  date DATE NOT NULL,
  gender ENUM('male','female') NOT NULL,
  name VARCHAR(100) NOT NULL,
  present TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_att (year, date, gender, name),
  KEY idx_att_month (year, date),
  KEY idx_att_name (year, gender, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
