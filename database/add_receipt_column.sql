-- Add receipt_path column to transactions table
-- Run this migration to add receipt upload functionality

USE sales_cash_flow;

ALTER TABLE transactions 
ADD COLUMN receipt_path VARCHAR(255) NULL AFTER payment_method,
ADD INDEX idx_receipt (receipt_path);

-- Update comment
ALTER TABLE transactions COMMENT = 'Transactions table with receipt upload support';
