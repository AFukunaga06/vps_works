-- ============================================================
-- schema.sql  小規模クリニック予約管理システム
-- ============================================================

CREATE DATABASE IF NOT EXISTS clinic_db
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE clinic_db;

-- ユーザー（スタッフ）
CREATE TABLE IF NOT EXISTS users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  name       VARCHAR(100) NOT NULL,
  role       ENUM('admin','doctor','receptionist') NOT NULL DEFAULT 'receptionist',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 患者台帳
CREATE TABLE IF NOT EXISTS patients (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  patient_no     VARCHAR(20) NOT NULL UNIQUE,
  last_name      VARCHAR(50) NOT NULL,
  first_name     VARCHAR(50) NOT NULL,
  last_name_kana VARCHAR(50),
  first_name_kana VARCHAR(50),
  birth_date     DATE,
  gender         ENUM('male','female','other') DEFAULT 'other',
  phone          VARCHAR(20),
  email          VARCHAR(100),
  postal_code    VARCHAR(10),
  address        TEXT,
  blood_type     ENUM('A','B','O','AB','unknown') DEFAULT 'unknown',
  allergies      TEXT,
  medical_history TEXT,
  note           TEXT,
  insurance_no   VARCHAR(30),
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 診療科目
CREATE TABLE IF NOT EXISTS departments (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  sort INT DEFAULT 0
) ENGINE=InnoDB;

-- 医師
CREATE TABLE IF NOT EXISTS doctors (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT,
  name          VARCHAR(100) NOT NULL,
  department_id INT,
  FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE SET NULL,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 予約枠マスタ（曜日別）
CREATE TABLE IF NOT EXISTS slot_templates (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  doctor_id     INT NOT NULL,
  day_of_week   TINYINT NOT NULL COMMENT '0=日,1=月,...,6=土',
  start_time    TIME NOT NULL,
  end_time      TIME NOT NULL,
  capacity      INT NOT NULL DEFAULT 1,
  is_active     TINYINT(1) DEFAULT 1,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 予約
CREATE TABLE IF NOT EXISTS appointments (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  patient_id   INT NOT NULL,
  doctor_id    INT NOT NULL,
  appt_date    DATE NOT NULL,
  start_time   TIME NOT NULL,
  end_time     TIME NOT NULL,
  status       ENUM('reserved','confirmed','completed','cancelled','no_show') DEFAULT 'reserved',
  reason       TEXT,
  note         TEXT,
  created_by   INT,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id)  REFERENCES doctors(id)  ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- 問診票
CREATE TABLE IF NOT EXISTS questionnaires (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id  INT NOT NULL UNIQUE,
  chief_complaint TEXT,
  symptom_since   VARCHAR(100),
  pain_scale      TINYINT COMMENT '0-10',
  fever           TINYINT(1) DEFAULT 0,
  cough           TINYINT(1) DEFAULT 0,
  nausea          TINYINT(1) DEFAULT 0,
  diarrhea        TINYINT(1) DEFAULT 0,
  fatigue         TINYINT(1) DEFAULT 0,
  other_symptoms  TEXT,
  current_meds    TEXT,
  pregnancy       TINYINT(1) DEFAULT 0,
  smoking         TINYINT(1) DEFAULT 0,
  alcohol         TINYINT(1) DEFAULT 0,
  emergency_contact_name  VARCHAR(100),
  emergency_contact_phone VARCHAR(20),
  submitted_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 診察履歴（SOAP形式）
CREATE TABLE IF NOT EXISTS consultations (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id  INT NOT NULL UNIQUE,
  patient_id      INT NOT NULL,
  doctor_id       INT NOT NULL,
  subjective      TEXT COMMENT '主訴・自覚症状',
  objective       TEXT COMMENT '客観的所見',
  assessment      TEXT COMMENT '診断',
  plan            TEXT COMMENT '治療計画',
  prescription    TEXT,
  next_visit_note TEXT COMMENT '次回受診提案メモ',
  next_visit_days INT  COMMENT '次回受診推奨日数',
  bp_sys          INT  COMMENT '収縮期血圧',
  bp_dia          INT  COMMENT '拡張期血圧',
  temperature     DECIMAL(4,1),
  spo2            INT,
  weight          DECIMAL(5,2),
  height          DECIMAL(5,2),
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id)     REFERENCES patients(id)     ON DELETE CASCADE,
  FOREIGN KEY (doctor_id)      REFERENCES doctors(id)      ON DELETE CASCADE
) ENGINE=InnoDB;
