-- Invoice Module Migration
-- Creates customers, invoices, and invoice_items tables
-- Run this once on the production server to enable the invoice module.

-- ── Customers ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS customers (
    customer_id    INT AUTO_INCREMENT PRIMARY KEY,
    company_id     INT NOT NULL,
    customer_name  VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    email          VARCHAR(255),
    phone          VARCHAR(50),
    address        TEXT,
    tax_id         VARCHAR(100),
    payment_terms  INT NOT NULL DEFAULT 30,         -- days until invoice due
    credit_limit   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    notes          TEXT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    INDEX idx_company     (company_id),
    INDEX idx_active      (company_id, is_active),
    INDEX idx_name        (customer_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Invoices ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS invoices (
    invoice_id     INT AUTO_INCREMENT PRIMARY KEY,
    company_id     INT NOT NULL,
    customer_id    INT NOT NULL,
    invoice_number VARCHAR(30) NOT NULL,
    invoice_date   DATE NOT NULL,
    due_date       DATE NOT NULL,
    subtotal       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax_amount     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_amount   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    amount_paid    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    amount_due     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status         ENUM('draft','sent','partial','overdue','paid','cancelled') NOT NULL DEFAULT 'draft',
    notes          TEXT,
    terms          TEXT,
    created_by     INT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id)  REFERENCES companies(company_id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by)  REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_invoice_number (company_id, invoice_number),
    INDEX idx_company        (company_id),
    INDEX idx_customer       (customer_id),
    INDEX idx_status         (company_id, status),
    INDEX idx_due_date       (company_id, due_date),
    INDEX idx_invoice_date   (company_id, invoice_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Invoice Items ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS invoice_items (
    item_id      INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id   INT NOT NULL,
    description  TEXT NOT NULL,
    quantity     DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    unit_price   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    amount       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    sort_order   INT NOT NULL DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE,
    INDEX idx_invoice (invoice_id),
    INDEX idx_sort    (invoice_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
