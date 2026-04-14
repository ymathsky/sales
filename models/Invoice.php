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
     * Update an existing invoice (header + line items).
     * Replaces all existing items with the new set.
     *
     * @param int   $invoiceId Invoice ID
     * @param int   $companyId Company ID (security check)
     * @param array $data      Invoice header fields
     * @param array $items     New line items
     * @return bool
     */
    public static function update($invoiceId, $companyId, $data, $items) {
        // Recalculate totals
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['quantity'] * $item['unit_price'];
        }
        $taxAmount   = $data['tax_amount'] ?? 0;
        $totalAmount = $subtotal + $taxAmount;

        // Preserve amount_paid, recalculate amount_due
        $existing   = self::getById($invoiceId, $companyId);
        $amountPaid = $existing ? (float)$existing['amount_paid'] : 0;
        $amountDue  = max(0, $totalAmount - $amountPaid);

        // Update invoice header
        $sql = "UPDATE invoices
                SET customer_id  = ?,
                    invoice_date = ?,
                    due_date     = ?,
                    subtotal     = ?,
                    tax_amount   = ?,
                    total_amount = ?,
                    amount_due   = ?,
                    notes        = ?,
                    terms        = ?
                WHERE invoice_id = ? AND company_id = ?";

        executeQuery($sql, [
            $data['customer_id'],
            $data['invoice_date'],
            $data['due_date'],
            $subtotal,
            $taxAmount,
            $totalAmount,
            $amountDue,
            $data['notes']  ?? null,
            $data['terms']  ?? null,
            $invoiceId,
            $companyId,
        ]);

        // Replace line items
        executeQuery("DELETE FROM invoice_items WHERE invoice_id = ?", [$invoiceId]);

        $sortOrder = 0;
        foreach ($items as $item) {
            $amount = $item['quantity'] * $item['unit_price'];
            executeQuery(
                "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, amount, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$invoiceId, $item['description'], $item['quantity'], $item['unit_price'], $amount, $sortOrder++]
            );
        }

        return true;
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

    /**
     * Get top customers by invoiced amount within a period
     *
     * @param int $companyId Company ID
     * @param string|null $startDate Start date
     * @param string|null $endDate End date
     * @param int $limit Max rows
     * @return array
     */
    public static function getTopCustomersByRevenue($companyId, $startDate = null, $endDate = null, $limit = 5) {
        $limit = max(1, (int)$limit);

        $sql = "SELECT
                    c.customer_id,
                    c.customer_name,
                    COUNT(i.invoice_id) as invoice_count,
                    SUM(i.total_amount) as total_invoiced,
                    SUM(i.amount_due) as total_due
                FROM invoices i
                INNER JOIN customers c ON c.customer_id = i.customer_id
                WHERE i.company_id = ?";

        $params = [$companyId];

        if ($startDate) {
            $sql .= " AND i.invoice_date >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $sql .= " AND i.invoice_date <= ?";
            $params[] = $endDate;
        }

        $sql .= " GROUP BY c.customer_id, c.customer_name
                  ORDER BY total_invoiced DESC
                  LIMIT ?";
        $params[] = $limit;

        return executeQuery($sql, $params)->fetchAll();
    }

    /**
     * Get unpaid invoice summary for a company
     *
     * @param int $companyId Company ID
     * @return array
     */
    public static function getUnpaidSummary($companyId) {
        $sql = "SELECT
                    COUNT(CASE WHEN amount_due > 0.009 THEN 1 END) as unpaid_count,
                    COALESCE(SUM(CASE WHEN amount_due > 0.009 THEN amount_due ELSE 0 END), 0) as unpaid_total,
                    COUNT(CASE WHEN amount_due > 0.009 AND due_date < CURDATE() THEN 1 END) as overdue_count,
                    COALESCE(SUM(CASE WHEN amount_due > 0.009 AND due_date < CURDATE() THEN amount_due ELSE 0 END), 0) as overdue_total
                FROM invoices
                WHERE company_id = ?";

        $row = executeQuery($sql, [$companyId])->fetch();

        return [
            'unpaid_count' => (int)($row['unpaid_count'] ?? 0),
            'unpaid_total' => (float)($row['unpaid_total'] ?? 0),
            'overdue_count' => (int)($row['overdue_count'] ?? 0),
            'overdue_total' => (float)($row['overdue_total'] ?? 0),
        ];
    }

    /**
     * Move an invoice (and its items) to a different company.
     * Generates a new invoice number in the target company to avoid conflicts.
     *
     * @param int $invoiceId      Invoice to move
     * @param int $fromCompanyId  Source company (security check)
     * @param int $toCompanyId    Destination company
     * @return bool
     */
    public static function moveToCompany($invoiceId, $fromCompanyId, $toCompanyId) {
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();

            // Verify the invoice belongs to the source company
            $stmt = $pdo->prepare("SELECT invoice_id FROM invoices WHERE invoice_id = ? AND company_id = ?");
            $stmt->execute([$invoiceId, $fromCompanyId]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                return false;
            }

            // Generate a fresh invoice number for the target company
            $newNumber = self::generateInvoiceNumber($toCompanyId);

            // Move the invoice
            $stmt = $pdo->prepare(
                "UPDATE invoices SET company_id = ?, invoice_number = ? WHERE invoice_id = ?"
            );
            $stmt->execute([$toCompanyId, $newNumber, $invoiceId]);

            $pdo->commit();
            return $newNumber;
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Error moving invoice: ' . $e->getMessage());
            return false;
        }
    }
}
