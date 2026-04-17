-- Add is_paid column to invoice_items
-- Run this once on your production database

ALTER TABLE invoice_items
    ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 0 AFTER amount;
