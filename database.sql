-- database.sql: new database built from the Tech SG Studio frontend
CREATE DATABASE IF NOT EXISTS techsg_invoice CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE techsg_invoice;

-- Counters for numbers that must never repeat: 'invoice' (reset each year), 'client', 'user'
CREATE TABLE sequences (
  name VARCHAR(20) NOT NULL,
  year SMALLINT NOT NULL DEFAULT 0,               -- 0 = no yearly reset
  last_number INT NOT NULL DEFAULT 0,
  PRIMARY KEY (name, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,               -- U-0001, the id the frontend shows
  name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(150) NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  active TINYINT(1) NOT NULL DEFAULT 1,
  password_hash VARCHAR(255) NOT NULL,            -- never the real password
  last_login DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,               -- CL-0001
  name VARCHAR(150) NOT NULL,
  contact VARCHAR(150) NULL,
  email VARCHAR(150) NULL,
  phone VARCHAR(40) NULL,
  address TEXT NULL,
  ntn VARCHAR(30) NULL,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,      -- archive instead of delete
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (email), INDEX (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(30) NOT NULL UNIQUE,         -- TSG-INV-2026-0001
  client_id INT NOT NULL,
  issue_date DATE NOT NULL,
  terms INT NOT NULL DEFAULT 14,                  -- payment terms in days
  due_date DATE NOT NULL,
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  discount DECIMAL(14,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,      -- calculated by the server
  tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0,   -- balance due = total - amount_paid
  status ENUM('Draft','Pending','Paid','Cancelled') NOT NULL DEFAULT 'Pending',  -- Overdue is computed, not stored
  paid_at DATE NULL,
  notes TEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (status), INDEX (due_date),
  FOREIGN KEY (client_id) REFERENCES clients(id),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  description VARCHAR(255) NOT NULL,              -- the app calls this "d"
  details TEXT NULL,                              -- the app calls this "s"
  qty DECIMAL(12,2) NOT NULL DEFAULT 1,           -- "q"
  price DECIMAL(14,2) NOT NULL DEFAULT 0,         -- "p"
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE items (                              -- the items catalogue page
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(40) NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(80) NULL,
  unit VARCHAR(30) NULL,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  cost_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  low_stock INT NOT NULL DEFAULT 0,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  receipt_no VARCHAR(30) NULL UNIQUE,             -- RCT-2026-0001
  invoice_id INT NULL,
  customer VARCHAR(150) NULL,
  amount DECIMAL(14,2) NOT NULL,
  method VARCHAR(40) NULL,
  reference VARCHAR(100) NULL,
  payment_date DATE NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Completed',
  notes TEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (                           -- company profile and other settings
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(50) NOT NULL,
  entity VARCHAR(50) NOT NULL,
  entity_id VARCHAR(40) NULL,
  details TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;