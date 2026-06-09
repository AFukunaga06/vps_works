CREATE TABLE IF NOT EXISTS `personal_info` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `password`       VARCHAR(255) NOT NULL,
  `name`           VARCHAR(100) NOT NULL,
  `furigana`       VARCHAR(100) NOT NULL,
  `gender`         VARCHAR(10) DEFAULT '',
  `birthday`       DATE DEFAULT NULL,
  `email`          VARCHAR(200) DEFAULT '',
  `phone`          VARCHAR(20) DEFAULT '',
  `address`        VARCHAR(300) DEFAULT '',
  `student_worker` VARCHAR(20) DEFAULT '',
  `marital_status` VARCHAR(20) DEFAULT '',
  `requests`       TEXT DEFAULT NULL,
  `consent`        TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
