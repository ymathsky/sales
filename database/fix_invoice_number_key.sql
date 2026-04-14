-- Fix invoice_number unique key
-- The invoice_number must be unique per-company, NOT globally.
-- Run this if you get "Duplicate entry 'INV-000001' for key 'invoice_number'" errors.
--
-- Step 1: Drop the global unique key (ignore error if it doesn't exist)
ALTER TABLE invoices DROP INDEX `invoice_number`;

-- Step 2: Drop the per-company key if it already exists (ignore error if it doesn't)
ALTER TABLE invoices DROP INDEX `unique_invoice_number`;

-- Step 3: Add the correct per-company unique key
ALTER TABLE invoices ADD UNIQUE KEY `unique_invoice_number` (`company_id`, `invoice_number`);
