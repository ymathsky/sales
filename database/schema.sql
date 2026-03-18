-- Multi-Company Cash Flow Tracking System Database Schema
-- Created: January 28, 2026

CREATE DATABASE IF NOT EXISTS sales_cash_flow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sales_cash_flow;

-- Companies table
CREATE TABLE companies (
    company_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(255),
    tax_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',
    INDEX idx_status (status),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    role ENUM('admin', 'manager', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    INDEX idx_username (username),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User-Company access control (many-to-many)
CREATE TABLE user_companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_id INT NOT NULL,
    access_level ENUM('read', 'write', 'admin') DEFAULT 'read',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_company (user_id, company_id),
    INDEX idx_user (user_id),
    INDEX idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions table (core cash in/out tracking)
CREATE TABLE transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    type ENUM('in', 'out') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    transaction_date DATE NOT NULL,
    category VARCHAR(100),
    description TEXT,
    reference_number VARCHAR(100),
    payment_method ENUM('cash', 'bank_transfer', 'check', 'other') DEFAULT 'cash',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    INDEX idx_company_date (company_id, transaction_date),
    INDEX idx_company_type (company_id, type),
    INDEX idx_date (transaction_date),
    INDEX idx_type (type),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transaction categories for better reporting
CREATE TABLE transaction_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('in', 'out', 'both') DEFAULT 'both',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_category (company_id, name),
    INDEX idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: admin123 - CHANGE IN PRODUCTION)
INSERT INTO users (username, password_hash, full_name, email, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@example.com', 'admin');

-- Insert sample companies
INSERT INTO companies (name, address, phone, email) VALUES
('Company A', '123 Main St, City, Country', '+1-555-0101', 'info@companya.com'),
('Company B', '456 Oak Ave, City, Country', '+1-555-0102', 'info@companyb.com'),
('Company C', '789 Pine Rd, City, Country', '+1-555-0103', 'info@companyc.com');

-- Grant admin access to all companies
INSERT INTO user_companies (user_id, company_id, access_level)
SELECT 1, company_id, 'admin' FROM companies;

-- Insert sample categories for Company A
INSERT INTO transaction_categories (company_id, name, type) VALUES
(1, 'Sales Revenue', 'in'),
(1, 'Service Income', 'in'),
(1, 'Rent Payment', 'out'),
(1, 'Utilities', 'out'),
(1, 'Salaries', 'out'),
(1, 'Office Supplies', 'out');

-- Insert sample transactions for Company A
INSERT INTO transactions (company_id, type, amount, transaction_date, category, description, created_by) VALUES
(1, 'in', 5000.00, '2026-01-15', 'Sales Revenue', 'Product sales - January week 2', 1),
(1, 'in', 3500.00, '2026-01-20', 'Service Income', 'Consulting services rendered', 1),
(1, 'out', 2000.00, '2026-01-01', 'Rent Payment', 'Monthly office rent', 1),
(1, 'out', 350.00, '2026-01-05', 'Utilities', 'Electricity and water bill', 1),
(1, 'out', 4500.00, '2026-01-25', 'Salaries', 'Staff salaries - January', 1);
