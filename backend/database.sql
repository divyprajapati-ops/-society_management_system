-- Society Management App Database Schema

CREATE DATABASE IF NOT EXISTS society_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE society_app;

CREATE TABLE IF NOT EXISTS society (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  address VARCHAR(255) NULL,
  fund_total DECIMAL(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS buildings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  society_id INT UNSIGNED NOT NULL,
  building_name VARCHAR(150) NOT NULL,
  CONSTRAINT fk_buildings_society FOREIGN KEY (society_id) REFERENCES society(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('society_admin','society_pramukh','building_admin','member') NOT NULL,
  society_id INT UNSIGNED NULL,
  building_id INT UNSIGNED NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME NULL,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role),
  KEY idx_users_society (society_id),
  KEY idx_users_building (building_id),
  KEY idx_users_last_login (last_login),
  CONSTRAINT fk_users_society FOREIGN KEY (society_id) REFERENCES society(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_users_building FOREIGN KEY (building_id) REFERENCES buildings(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS society_fund (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  society_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  type ENUM('income','expense','use_money') NOT NULL,
  description VARCHAR(255) NULL,
  date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_society_fund_society (society_id),
  CONSTRAINT fk_society_fund_society FOREIGN KEY (society_id) REFERENCES society(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS building_fund (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  building_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  type ENUM('income','expense','use_money') NOT NULL,
  date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_building_fund_building (building_id),
  CONSTRAINT fk_building_fund_building FOREIGN KEY (building_id) REFERENCES buildings(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS meetings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  level ENUM('society','building') NOT NULL,
  society_id INT UNSIGNED NULL,
  building_id INT UNSIGNED NULL,
  title VARCHAR(200) NOT NULL,
  meeting_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_meetings_society (society_id),
  KEY idx_meetings_building (building_id),
  CONSTRAINT fk_meetings_society FOREIGN KEY (society_id) REFERENCES society(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_meetings_building FOREIGN KEY (building_id) REFERENCES buildings(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  level ENUM('society','building','personal') NOT NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notes_user (user_id),
  CONSTRAINT fk_notes_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS maintenance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  month CHAR(7) NOT NULL,
  status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_maintenance_member (member_id),
  KEY idx_maintenance_month (month),
  CONSTRAINT fk_maintenance_member FOREIGN KEY (member_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- Activity Logs for audit trail
CREATE TABLE IF NOT EXISTS activity_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_logs_user (user_id),
  KEY idx_logs_action (action),
  KEY idx_logs_created (created_at),
  CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- Seed data
INSERT INTO society (id, name, address, fund_total)
VALUES (1, 'Demo Society', 'Demo Address', 0.00)
ON DUPLICATE KEY UPDATE name=VALUES(name), address=VALUES(address);

INSERT INTO buildings (id, society_id, building_name)
VALUES (1, 1, 'A Wing')
ON DUPLICATE KEY UPDATE building_name=VALUES(building_name);

-- Default admin login:
-- email: admin@society.test
-- password: admin123
-- bcrypt hash generated via PHP password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO users (id, name, email, password, role, society_id, building_id, status)
VALUES (
  1,
  'Society Admin',
  'admin@society.test',
  '$2y$10$YncKGDKZmqW4faWubTNNzO60TQ1Y6R0JHhn4oaguhtF6wejjzF8TG',
  'society_admin',
  1,
  NULL,
  'active'
)
ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role), status=VALUES(status);

-- Demo Society Pramukh:
-- email: pramukh@society.test
-- password: pramukh123
INSERT INTO users (id, name, email, password, role, society_id, building_id, status)
VALUES (
  2,
  'Society Pramukh',
  'pramukh@society.test',
  '$2y$10$C/mc4fx9olG58kxJPdoxYed.da.9ypAkI.IxcEes93KWEe8M5HIYa',
  'society_pramukh',
  1,
  NULL,
  'active'
)
ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role), status=VALUES(status);

-- Demo Building Admin:
-- email: building@society.test
-- password: building123
INSERT INTO users (id, name, email, password, role, society_id, building_id, status)
VALUES (
  3,
  'Building Admin',
  'building@society.test',
  '$2y$10$upRW87EYn2DAETBS9gqX3OMJpaZhXqtI68LALLzNqVjI4M/nDw9uC',
  'building_admin',
  1,
  1,
  'active'
)
ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role), society_id=VALUES(society_id), building_id=VALUES(building_id), status=VALUES(status);

-- Demo Member:
-- email: member@society.test
-- password: member123
INSERT INTO users (id, name, email, password, role, society_id, building_id, status)
VALUES (
  4,
  'Member User',
  'member@society.test',
  '$2y$10$amOXQixWmVR.StYiLEPFjOE9M4RJeH/i.lQQ7qjRcAOmqbwIRdlru',
  'member',
  1,
  1,
  'active'
)
ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role), society_id=VALUES(society_id), building_id=VALUES(building_id), status=VALUES(status);
