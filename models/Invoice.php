<?php
/**
 * Invoice Model
 * Handles invoice operations for Accounts Receivable
 */

require_once __DIR__ . '/../config/database.php';

class Invoice {
    
    /**
     * Get all invoices for a company
     * 
     * @param int $companyId Company ID
     * @param array $filters Optional filters
     * @return array List of invoices
     */
    public static function getByCompany($companyId, $filters = []) {
        $sql = "SELECT i.*, c.customer_name, u.full_name as created_by_name
                FROM invoices i
                INNER JOIN customers c ON i.customer_id = c.customer_id
                LEFT JOIN users u ON i.created_by = u.user_id
                WHERE i.company_id = ?";
        
        $params = [$companyId];
        
        if (!empty($filters['status'])) {
            $sql .= " AND i.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['customer_id'])) {
            $sql .= " AND i.customer_id = ?";
            $params[] = $filters['customer_id'];
        }
        
        $sql .= " ORDER BY i.invoice_date DESC, i.invoice_number DESC";
        
        $stmt = executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get invoice by ID
     * 
     * @param int $invoiceId Invoice ID
     * @param int $companyId Company ID (for security)
     * @return array|false Invoice data or false
     */
    public static function getById($invoiceId, $companyId) {
        $sql = "SELECT i.*, c.customer_name, c.contact_person, c.email, c.phone, 
                       c.address, c.tax_id, u.full_name as created_by_name
                FROM invoices i
                INNER JOIN customers c ON i.customer_id = c.customer_id
                LEFT JOIN users u ON i.created_by = u.user_id
                WHERE i.invoice_id = ? AND i.company_id = ?";
        
        $stmt = executeQuery($sql, [$invoiceId, $companyId]);
        return $stmt->fetch();
    }
    
    /**
     * Get invoice items
     * 
     * @param int $invoiceId Invoice ID
     * @return array List of invoice items
     */
    public static function getItems($invoiceId) {
        $sql = "SELECT * FROM invoice_items 
                WHERE invoice_id = ? 
                ORDER BY sort_order ASC, item_id ASC";
        
        $stmt = executeQuery($sql, [$invoiceId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Create new invoice
     * 
     * @param array $data Invoice data
     * @param array $items Invoice items
     * @return int New invoice ID
     */
    public static function create($data, $items) {
        // Generate invoice number
        $invoiceNumber = self::generateInvoiceNumber($data['company_id']);
        
        // Calculate totals
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['quantity'] * $item['unit_price'];
        }
        
        $taxAmount = $data['tax_amount'] ?? 0;
        $totalAmount = $subtotal + $taxAmount;
        
        // Create invoice
        $sql = "INSERT INTO invoices 
                (company_id, customer_id, invoice_number, invoice_date, due_date, 
                 subtotal, tax_amount, total_amount, amount_paid, amount_due, 
                 status, notes, terms, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'draft', ?, ?, ?)";
        
        executeQuery($sql, [
            $data['company_id'],
            $data['customer_id'],
            $invoiceNumber,
            $data['invoice_date'],
            $data['due_date'],
            $subtotal,
            $taxAmount,
            $totalAmount,
            $totalAmount, // amount_due = total_amount initially
            $data['notes'] ?? null,
            $data['terms'] ?? null,
            getCurrentUserId()
        ]);
        
        $pdo = getDBConnection();
        $invoiceId = $pdo->lastInsertId();
        
        // Create invoice items
        $sortOrder = 0;
        foreach ($items as $item) {
            $amount = $item['quantity'] * $item['unit_price'];
            $sql = "INSERT INTO invoice_items 
                    (invoice_id, description, quantity, unit_price, amount, sort_order) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            executeQuery($sql, [
                $invoiceId,
                $item['description'],
                $item['quantity'],
                $item['unit_price'],
                $amount,
                $sortOrder++
            ]);
        }
        
        return $invoiceId;
    }
    
    /**
     * Update invoice status
     * 
     * @param int $invoiceId Invoice ID
     * @param int $companyId Company ID
     * @param string $status New status
     * @return bool Success status
     */
    public static function updateStatus($invoiceId, $companyId, $status) {
        $sql = "UPDATE invoices SET status = ? WHERE invoice_id = ? AND company_id = ?";
        executeQuery($sql, [$status, $invoiceId, $companyId]);
        
        // Auto-update overdue status
        self::updateOverdueStatus($companyId);
        
        return true;
    }
    
    /**
     * Record payment for invoice
     * 
     * @param int $invoiceId Invoice ID
     * @param int $companyId Company ID
     * @param float $amount Payment amount
     * @return bool Success status
     */
    public static function recordPayment($invoiceId, $companyId, $amount) {
        $invoice = self::getById($invoiceId, $companyId);
        if (!$invoice) return false;
        
        $newAmountPaid = $invoice['amount_paid'] + $amount;
        $newAmountDue = $invoice['total_amount'] - $newAmountPaid;
        
        // Determine new status
        $newStatus = 'paid';
        if ($newAmountDue > 0.01) {
            $newStatus = 'partial';
        } elseif ($newAmountDue < -0.01) {
            // Overpaid
            $newStatus = 'paid';
        }
        
        $sql = "UPDATE invoices 
                SET amount_paid = ?, amount_due = ?, status = ?
                WHERE invoice_id = ? AND company_id = ?";
        
        executeQuery($sql, [
            $newAmountPaid,
            $newAmountDue,
            $newStatus,
            $invoiceId,
            $companyId
        ]);
        
        return true;
    }
    
    /**
     * Update overdue invoices
     * 
     * @param int $companyId Company ID
     * @return bool Success status
     */
    public static function updateOverdueStatus($companyId) {
        $sql = "UPDATE invoices 
                SET status = 'overdue' 
                WHERE company_id = ? 
                AND status IN ('sent', 'partial') 
                AND due_date < CURDATE()";
        
        executeQuery($sql, [$companyId]);
        return true;
    }
    
    /**
     * Get AR aging report
     * 
     * @param int $companyId Company ID
     * @return array Aging data
     */
    public static function getAgingReport($companyId) {
        $sql = "SELECT 
                    c.customer_id,
                    c.customer_name,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) <= 0 THEN i.amount_due ELSE 0 END) as current_amount,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 1 AND 30 THEN i.amount_due ELSE 0 END) as days_1_30,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 31 AND 60 THEN i.amount_due ELSE 0 END) as days_31_60,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 61 AND 90 THEN i.amount_due ELSE 0 END) as days_61_90,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) > 90 THEN i.amount_due ELSE 0 END) as days_over_90,
                    SUM(i.amount_due) as total_due
                FROM customers c
                INNER JOIN invoices i ON c.customer_id = i.customer_id
                WHERE i.company_id = ? 
                AND i.status IN ('sent', 'partial', 'overdue')
                GROUP BY c.customer_id, c.customer_name
                HAVING total_due > 0
                ORDER BY total_due DESC";
        
        $stmt = executeQuery($sql, [$companyId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Generate next invoice number
     * 
     * @param int $companyId Company ID
     * @return string Next invoice number
     */
    public static function generateInvoiceNumber($companyId) {
        $sql = "SELECT invoice_number FROM invoices 
                WHERE company_id = ? 
                ORDER BY invoice_id DESC LIMIT 1";
        
        $stmt = executeQuery($sql, [$companyId]);
        $last = $stmt->fetch();
        
        if ($last && preg_match('/INV-(\d+)/', $last['invoice_number'], $matches)) {
            $number = intval($matches[1]) + 1;
        } else {
            $number = 1;
        }
        
        return 'INV-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
