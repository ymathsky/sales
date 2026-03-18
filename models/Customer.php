<?php
/**
 * Customer Model
 * Handles customer operations for Accounts Receivable
 */

require_once __DIR__ . '/../config/database.php';

class Customer {
    
    /**
     * Get all customers for a company
     * 
     * @param int $companyId Company ID
     * @param bool $activeOnly Only get active customers
     * @return array List of customers
     */
    public static function getByCompany($companyId, $activeOnly = true) {
        $sql = "SELECT c.*, 
                    COUNT(DISTINCT i.invoice_id) as invoice_count,
                    COALESCE(SUM(i.amount_due), 0) as total_outstanding
                FROM customers c
                LEFT JOIN invoices i ON c.customer_id = i.customer_id AND i.status IN ('sent', 'partial', 'overdue')
                WHERE c.company_id = ?";
        
        $params = [$companyId];
        
        if ($activeOnly) {
            $sql .= " AND c.is_active = 1";
        }
        
        $sql .= " GROUP BY c.customer_id ORDER BY c.customer_name ASC";
        
        $stmt = executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get customer by ID
     * 
     * @param int $customerId Customer ID
     * @param int $companyId Company ID (for security)
     * @return array|false Customer data or false
     */
    public static function getById($customerId, $companyId) {
        $sql = "SELECT * FROM customers WHERE customer_id = ? AND company_id = ?";
        $stmt = executeQuery($sql, [$customerId, $companyId]);
        return $stmt->fetch();
    }
    
    /**
     * Create new customer
     * 
     * @param array $data Customer data
     * @return int New customer ID
     */
    public static function create($data) {
        $sql = "INSERT INTO customers 
                (company_id, customer_name, contact_person, email, phone, address, 
                 tax_id, payment_terms, credit_limit, is_active, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        executeQuery($sql, [
            $data['company_id'],
            $data['customer_name'],
            $data['contact_person'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['tax_id'] ?? null,
            $data['payment_terms'] ?? 30,
            $data['credit_limit'] ?? 0,
            $data['is_active'] ?? 1,
            $data['notes'] ?? null
        ]);
        
        $pdo = getDBConnection();
        return $pdo->lastInsertId();
    }
    
    /**
     * Update customer
     * 
     * @param int $customerId Customer ID
     * @param int $companyId Company ID (for security)
     * @param array $data Customer data
     * @return bool Success status
     */
    public static function update($customerId, $companyId, $data) {
        $sql = "UPDATE customers 
                SET customer_name = ?, contact_person = ?, email = ?, phone = ?, 
                    address = ?, tax_id = ?, payment_terms = ?, credit_limit = ?, 
                    is_active = ?, notes = ?
                WHERE customer_id = ? AND company_id = ?";
        
        executeQuery($sql, [
            $data['customer_name'],
            $data['contact_person'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['tax_id'] ?? null,
            $data['payment_terms'] ?? 30,
            $data['credit_limit'] ?? 0,
            $data['is_active'] ?? 1,
            $data['notes'] ?? null,
            $customerId,
            $companyId
        ]);
        
        return true;
    }
    
    /**
     * Delete customer
     * 
     * @param int $customerId Customer ID
     * @param int $companyId Company ID (for security)
     * @return bool Success status
     */
    public static function delete($customerId, $companyId) {
        $sql = "DELETE FROM customers WHERE customer_id = ? AND company_id = ?";
        executeQuery($sql, [$customerId, $companyId]);
        return true;
    }
    
    /**
     * Get customer statistics
     * 
     * @param int $customerId Customer ID
     * @param int $companyId Company ID
     * @return array Statistics
     */
    public static function getStatistics($customerId, $companyId) {
        $sql = "SELECT 
                    COUNT(CASE WHEN status IN ('sent', 'partial', 'overdue') THEN 1 END) as open_invoices,
                    COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_invoices,
                    COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_invoices,
                    COALESCE(SUM(CASE WHEN status IN ('sent', 'partial', 'overdue') THEN amount_due ELSE 0 END), 0) as total_outstanding,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as total_paid
                FROM invoices 
                WHERE customer_id = ? AND company_id = ?";
        
        $stmt = executeQuery($sql, [$customerId, $companyId]);
        return $stmt->fetch();
    }
}
