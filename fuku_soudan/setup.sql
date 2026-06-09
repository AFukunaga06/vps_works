-- フクの相談窓口 DB セットアップ
CREATE DATABASE IF NOT EXISTS fuku_soudan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fuku_soudan;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_type ENUM('一般相談','法律相談') NOT NULL,
    reserve_date DATE NOT NULL,
    start_time TIME NOT NULL,
    name VARCHAR(100) NOT NULL,
    kana VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    tel VARCHAR(20) NOT NULL,
    is_first TINYINT(1) NOT NULL DEFAULT 1,
    consult_method ENUM('Zoom','電話','対面') NOT NULL,
    content TEXT,
    note TEXT,
    status ENUM('pending','pending_payment','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slot (consultation_type, reserve_date, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blocked_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocked_date DATE NOT NULL UNIQUE,
    reason VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
