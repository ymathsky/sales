-- Add transaction_account column to transactions table
-- This tracks whether a transaction affects cash on hand or bank account

ALTER TABLE transactions 
ADD COLUMN transaction_account ENUM('cash', 'bank') DEFAULT 'cash' AFTER payment_method;

-- Add index for better query performance
ALTER TABLE transactions 
ADD INDEX idx_transaction_account (transaction_account);

-- Update existing transactions to set account type based on payment method
-- Bank transfers, checks, and similar should be bank account transactions
UPDATE transactions 
SET transaction_account = 'bank' 
WHERE payment_method IN ('bank_transfer', 'check');

-- Cash payments should be cash account transactions
UPDATE transactions 
SET transaction_account = 'cash' 
WHERE payment_method = 'cash' OR payment_method = 'other';
