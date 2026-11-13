-- BoardingHub database for XAMPP (MySQL / MariaDB)
-- Import with phpMyAdmin: Import > choose boardinghub.sql > Go
CREATE DATABASE IF NOT EXISTS boardinghub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE boardinghub;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS email_log, notifications, messages, threads, payments, reservations, beds, rooms, boarding_houses, owner_profiles, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(40) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('tenant','admin') NOT NULL DEFAULT 'tenant',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL
) ENGINE=InnoDB;

-- A tenant becomes an owner when an admin approves this profile.
CREATE TABLE owner_profiles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  display_name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  photo VARCHAR(255) DEFAULT NULL,
  national_id_image VARCHAR(255) NOT NULL,
  ownership_proof_image VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(500) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME DEFAULT NULL,
  reviewed_by INT UNSIGNED DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE boarding_houses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  name VARCHAR(140) NOT NULL,
  address VARCHAR(255) NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  category VARCHAR(60) NOT NULL,
  description TEXT,
  rules TEXT,
  contract TEXT,
  contact_phone VARCHAR(40) DEFAULT NULL,
  contact_email VARCHAR(160) DEFAULT NULL,
  photo VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- price is charged per bed for every "stay_days" block (e.g. 30 days).
CREATE TABLE rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  house_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  stay_days INT UNSIGNED NOT NULL DEFAULT 30,
  description VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (house_id) REFERENCES boarding_houses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE beds (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  label VARCHAR(40) NOT NULL,
  status ENUM('available','reserved','occupied') NOT NULL DEFAULT 'available',
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- A stay. Created online by a tenant (source=online) or by the owner for a walk-in (source=owner).
CREATE TABLE reservations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source ENUM('online','owner') NOT NULL DEFAULT 'online',
  tenant_id INT UNSIGNED DEFAULT NULL,
  guest_name VARCHAR(120) NOT NULL,
  guest_email VARCHAR(160) NOT NULL,
  guest_phone VARCHAR(40) DEFAULT NULL,
  house_id INT UNSIGNED NOT NULL,
  room_id INT UNSIGNED NOT NULL,
  bed_id INT UNSIGNED NOT NULL,
  move_in_date DATE NOT NULL,
  status ENUM('pending','accepted','declined','cancelled','ended') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME DEFAULT NULL,
  ended_at DATETIME DEFAULT NULL,
  FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (house_id) REFERENCES boarding_houses(id) ON DELETE CASCADE,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (bed_id) REFERENCES beds(id) ON DELETE CASCADE,
  INDEX (tenant_id), INDEX (house_id), INDEX (status)
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reservation_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED DEFAULT NULL,
  house_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  days INT UNSIGNED NOT NULL,
  method VARCHAR(30) NOT NULL DEFAULT 'gcash',
  provider_ref VARCHAR(120) DEFAULT NULL,
  provider_payment_id VARCHAR(120) DEFAULT NULL,
  status ENUM('pending','paid','refund_requested','refunded','failed') NOT NULL DEFAULT 'pending',
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  receipt_code VARCHAR(30) DEFAULT NULL,
  receipt_token CHAR(32) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at DATETIME DEFAULT NULL,
  refunded_at DATETIME DEFAULT NULL,
  FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
  INDEX (owner_id), INDEX (status), UNIQUE (receipt_token)
) ENGINE=InnoDB;

CREATE TABLE threads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  target ENUM('owner','admin') NOT NULL,
  owner_id INT UNSIGNED DEFAULT NULL,
  house_id INT UNSIGNED DEFAULT NULL,
  category ENUM('inquiry','complaint','report') NOT NULL DEFAULT 'inquiry',
  subject VARCHAR(160) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (owner_id), INDEX (target)
) ENGINE=InnoDB;

CREATE TABLE messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  thread_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  sender_role ENUM('tenant','owner','admin') NOT NULL,
  body TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  text VARCHAR(255) NOT NULL,
  link VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (user_id, is_read)
) ENGINE=InnoDB;

-- Every email the system sends is recorded here (useful when SMTP is not set up yet).
CREATE TABLE email_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(160) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  status ENUM('sent','failed','logged') NOT NULL DEFAULT 'logged',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
