CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(60) NOT NULL,
  email VARCHAR(160) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  role ENUM('admin','manager','viewer') NOT NULL DEFAULT 'viewer',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  password_hash VARCHAR(255) NOT NULL,
  last_login DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  skey VARCHAR(60) PRIMARY KEY,
  svalue TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_recipients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL,
  rtype ENUM('to','cc','bcc') NOT NULL DEFAULT 'to',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  period_from DATE NULL,
  period_to DATE NULL,
  recipients INT NOT NULL DEFAULT 0,
  subject VARCHAR(255) NOT NULL DEFAULT '',
  filename VARCHAR(190) NOT NULL DEFAULT '',
  status ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  message TEXT NULL,
  sent_by VARCHAR(120) NOT NULL DEFAULT '',
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS violation_notices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  control_no VARCHAR(30) NOT NULL,
  driver_id INT NOT NULL,
  driver_name VARCHAR(160) NOT NULL DEFAULT '',
  unit_no VARCHAR(64) NOT NULL DEFAULT '',
  period_from DATE NULL,
  period_to DATE NULL,
  location VARCHAR(255) NOT NULL DEFAULT '',
  c_speeding INT NOT NULL DEFAULT 0,
  c_restricted INT NOT NULL DEFAULT 0,
  c_no_parking INT NOT NULL DEFAULT 0,
  c_overstay INT NOT NULL DEFAULT 0,
  c_idle INT NOT NULL DEFAULT 0,
  total INT NOT NULL DEFAULT 0,
  counseling_date DATE NULL,
  counseling_time VARCHAR(60) NOT NULL DEFAULT '',
  counseling_venue VARCHAR(160) NOT NULL DEFAULT '',
  status ENUM('pending','scheduled','completed','no_show') NOT NULL DEFAULT 'pending',
  counseled_at DATETIME NULL,
  counseled_by VARCHAR(120) NOT NULL DEFAULT '',
  remarks TEXT NULL,
  issued_by VARCHAR(120) NOT NULL DEFAULT '',
  created_by VARCHAR(120) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_control (control_no),
  UNIQUE KEY uq_driver_period (driver_id, period_from, period_to),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incidents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  incident_date DATETIME NOT NULL,
  driver_id INT NULL,
  truck_code VARCHAR(64) NOT NULL DEFAULT '',
  itype VARCHAR(60) NOT NULL DEFAULT 'Other',
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL,
  address VARCHAR(255) NOT NULL DEFAULT '',
  description TEXT NULL,
  status ENUM('open','investigating','resolved','closed') NOT NULL DEFAULT 'open',
  photo VARCHAR(190) NOT NULL DEFAULT '',
  reported_by VARCHAR(120) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inc_date (incident_date),
  KEY idx_inc_driver (driver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS drivers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(160) NOT NULL DEFAULT '',
  tracker_raw VARCHAR(191) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS imports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL,
  report_type VARCHAR(40) NOT NULL,
  report_title VARCHAR(255) NOT NULL DEFAULT '',
  category VARCHAR(160) NOT NULL DEFAULT '',
  company VARCHAR(160) NOT NULL DEFAULT '',
  period_from DATE NULL,
  period_to DATE NULL,
  event_count INT NOT NULL DEFAULT 0,
  file_hash CHAR(40) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hash (file_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  import_id INT NOT NULL,
  driver_id INT NOT NULL,
  event_type VARCHAR(32) NOT NULL,
  category VARCHAR(160) NOT NULL DEFAULT '',
  zone_name VARCHAR(255) NOT NULL DEFAULT '',
  event_date DATE NULL,
  start_dt DATETIME NULL,
  end_dt DATETIME NULL,
  duration_min DECIMAL(12,2) NOT NULL DEFAULT 0,
  entrance_place VARCHAR(255) NOT NULL DEFAULT '',
  exit_place VARCHAR(255) NOT NULL DEFAULT '',
  metric_num DECIMAL(14,4) NULL,
  metric_num2 DECIMAL(14,4) NULL,
  severity TINYINT NOT NULL DEFAULT 0,
  points INT NOT NULL DEFAULT 0,
  details TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_driver (driver_id),
  KEY idx_type (event_type),
  KEY idx_date (event_date),
  KEY idx_import (import_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
