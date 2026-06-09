-- フクのAI寺子屋 スキーマ
CREATE DATABASE IF NOT EXISTS fuku_ai_terakoya
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE fuku_ai_terakoya;

CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(64)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(255) DEFAULT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_login_at DATETIME     DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  email       VARCHAR(255) NOT NULL,
  phone       VARCHAR(40)  DEFAULT NULL,
  note        TEXT         DEFAULT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_students_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id   INT UNSIGNED DEFAULT NULL,
  type         ENUM('orientation','session') NOT NULL DEFAULT 'orientation',
  reserve_date DATE         NOT NULL,
  reserve_time VARCHAR(20)  NOT NULL,
  name         VARCHAR(100) NOT NULL,
  email        VARCHAR(255) NOT NULL,
  goal         TEXT         DEFAULT NULL,
  status       ENUM('pending','confirmed','done','cancelled') NOT NULL DEFAULT 'pending',
  zoom_url     VARCHAR(500) DEFAULT NULL,
  admin_note   TEXT         DEFAULT NULL,
  source_ip    VARCHAR(45)  DEFAULT NULL,
  user_agent   VARCHAR(255) DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_reservations_date (reserve_date),
  KEY idx_reservations_status (status),
  KEY idx_reservations_student (student_id),
  CONSTRAINT fk_reservations_student FOREIGN KEY (student_id)
    REFERENCES students(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS instructor_applications (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100) NOT NULL,
  email           VARCHAR(255) NOT NULL,
  phone           VARCHAR(40)  DEFAULT NULL,
  profile         TEXT         DEFAULT NULL,
  skills          TEXT         DEFAULT NULL,
  available_days  VARCHAR(255) DEFAULT NULL,
  motivation      TEXT         DEFAULT NULL,
  status          ENUM('new','reviewing','accepted','rejected') NOT NULL DEFAULT 'new',
  admin_note      TEXT         DEFAULT NULL,
  source_ip       VARCHAR(45)  DEFAULT NULL,
  user_agent      VARCHAR(255) DEFAULT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ia_status (status),
  KEY idx_ia_email  (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_logs (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  related_type  VARCHAR(32)  DEFAULT NULL,
  related_id    INT UNSIGNED DEFAULT NULL,
  to_email      VARCHAR(255) NOT NULL,
  subject       VARCHAR(255) NOT NULL,
  body          MEDIUMTEXT   DEFAULT NULL,
  status        ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  error_message TEXT         DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ml_related (related_type, related_id),
  KEY idx_ml_status  (status),
  KEY idx_ml_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
