<?php
/**
 * Transaction Model
 * Handles cash in/out transaction operations
 */

require_once __DIR__ . '/../config/database.php';

class Transaction {
    
    /**
     * Get transactions by company
     * 
     * @param int $companyId Company ID
     * @param array $filters Optional filters (type, start_date, end_date, category)
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array List of transactions
     */
    public static function getByCompany($companyId, $filters = [], $limit = 50, $offset = 0) {
        $sql = "SELECT t.*, u.full_name as created_by_name,
                (SELECT COUNT(*) FROM transaction_receipts WHERE transaction_id = t.transaction_id) as receipt_count
                FROM transactions t
                LEFT JOIN users u ON t.created_by = u.user_id
                WHERE t.company_id = ?";
        
        $params = [$companyId];
        
        // Apply filters
        if (!empty($filters['type'])) {
            $sql .= " AND t.type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND t.transaction_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND t.transaction_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND t.category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['account'])) {
            $sql .= " AND t.transaction_account = ?";
            $params[] = $filters['account'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (t.description LIKE ? OR t.reference_number LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY t.transaction_date DESC, t.created_at DESC";
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get transaction by ID
     * 
     * @param int $transactionId Transaction ID
     * @param int $companyId Company ID (for security check)
     * @return array|false Transaction data or false
     */
    public static function getById($transactionId, $companyId) {
        $sql = "SELECT t.*, u.full_name as created_by_name
                FROM transactions t
                LEFT JOIN users u ON t.created_by = u.user_id
                WHERE t.transaction_id = ? AND t.company_id = ?";
        
        $stmt = executeQuery($sql, [$transactionId, $companyId]);
        return $stmt->fetch();
    }
    
    /**
     * Create new transaction
     * 
     * @param array $data Transaction data
     * @return int New transaction ID
     */
    public static function create($data) {
        $sql = "INSERT INTO transactions 
                (company_id, type, amount, transaction_date, category, description, 
                 reference_number, payment_method, transaction_account, receipt_path, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        executeQuery($sql, [
            $data['company_id'],
            $data['type'],
            $data['amount'],
            $data['transaction_date'],
            $data['category'] ?? null,
            $data['description'] ?? null,
            $data['reference_number'] ?? null,
            $data['payment_method'] ?? 'cash',
            $data['transaction_account'] ?? 'cash',
            $data['receipt_path'] ?? null,
            $data['created_by']
        ]);
        
        $pdo = getDBConnection();
        return $pdo->lastInsertId();
    }
    
    /**
     * Update transaction
     * 
     * @param int $transactionId Transaction ID
     * @param int $companyId Company ID (for security check)
     * @param array $data Transaction data
     * @return bool Success
     */
    public static function update($transactionId, $companyId, $data) {
        $sql = "UPDATE transactions 
                SET type = ?, amount = ?, transaction_date = ?, category = ?, 
                    description = ?, reference_number = ?, payment_method = ?, 
                    transaction_account = ?, receipt_path = ?
                WHERE transaction_id = ? AND company_id = ?";
        
        executeQuery($sql, [
            $data['type'],
            $data['amount'],
            $data['transaction_date'],
            $data['category'] ?? null,
            $data['description'] ?? null,
            $data['reference_number'] ?? null,
            $data['payment_method'] ?? 'cash',
            $data['transaction_account'] ?? 'cash',
            $data['receipt_path'] ?? null,
            $transactionId,
            $companyId
        ]);
        
        return true;
    }
    
    /**
     * Delete transaction
     * 
     * @param int $transactionId Transaction ID
     * @param int $companyId Company ID (for security check)
     * @return bool Success
     */
    public static function delete($transactionId, $companyId) {
        // Delete all receipt files first
        $receipts = self::getReceipts($transactionId);
        foreach ($receipts as $receipt) {
            if (file_exists(__DIR__ . '/../' . $receipt['file_path'])) {
                unlink(__DIR__ . '/../' . $receipt['file_path']);
            }
        }
        
        // Delete legacy receipt file if exists
        $sql = "SELECT receipt_path FROM transactions WHERE transaction_id = ? AND company_id = ?";
        $stmt = executeQuery($sql, [$transactionId, $companyId]);
        $trx = $stmt->fetch();
        if ($trx && !empty($trx['receipt_path']) && file_exists(__DIR__ . '/../' . $trx['receipt_path'])) {
            unlink(__DIR__ . '/../' . $trx['receipt_path']);
        }

        $sql = "DELETE FROM transactions WHERE transaction_id = ? AND company_id = ?";
        executeQuery($sql, [$transactionId, $companyId]);
        return true;
    }

    /**
     * Get receipts for a transaction
     * 
     * @param int $transactionId Transaction ID
     * @return array List of receipts
     */
    public static function getReceipts($transactionId) {
        $sql = "SELECT * FROM transaction_receipts WHERE transaction_id = ? ORDER BY created_at DESC";
        return executeQuery($sql, [$transactionId])->fetchAll();
    }

    /**
     * Add receipt to transaction
     * 
     * @param int $transactionId Transaction ID
     * @param string $filePath File path relative to project root
     * @param string|null $originalName Original filename
     * @return mixed Last insert ID
     */
    public static function addReceipt($transactionId, $filePath, $originalName = null) {
        $sql = "INSERT INTO transaction_receipts (transaction_id, file_path, original_name) VALUES (?, ?, ?)";
        executeQuery($sql, [$transactionId, $filePath, $originalName]);
        $id = getDBConnection()->lastInsertId();

        // Update main record for backward compatibility (shows in list view)
        $sql = "UPDATE transactions SET receipt_path = ? WHERE transaction_id = ?";
        executeQuery($sql, [$filePath, $transactionId]);

        return $id;
    }

    /**
     * Delete specific receipt
     * 
     * @param int $receiptId Receipt ID
     * @param int $transactionId Transaction ID (for verification)
     * @return bool Success
     */
    public static function deleteReceipt($receiptId, $transactionId) {
        // Verify receipt belongs to transaction
        $sql = "SELECT file_path FROM transaction_receipts WHERE id = ? AND transaction_id = ?";
        $stmt = executeQuery($sql, [$receiptId, $transactionId]);
        $receipt = $stmt->fetch();
        
        if ($receipt) {
            $sql = "DELETE FROM transaction_receipts WHERE id = ?";
            executeQuery($sql, [$receiptId]);
            
            // Delete file
            if (file_exists(__DIR__ . '/../' . $receipt['file_path'])) {
                unlink(__DIR__ . '/../' . $receipt['file_path']);
            }
            return true;
        }
        return false;
    }
    
    /**
     * Get transaction summary by date range
     * 
     * @param int $companyId Company ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Summary data
     */
    public static function getSummary($companyId, $startDate, $endDate) {
        $sql = "SELECT 
                    type,
                    COUNT(*) as count,
                    SUM(amount) as total,
                    category,
                    DATE(transaction_date) as date
                FROM transactions
                WHERE company_id = ? 
                AND transaction_date BETWEEN ? AND ?
                GROUP BY type, category, DATE(transaction_date)
                ORDER BY transaction_date DESC";
        
        $stmt = executeQuery($sql, [$companyId, $startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get categories for company
     * 
     * @param int $companyId Company ID
     * @param string|null $type Filter by type (in/out)
     * @return array List of categories
     */
    public static function getCategories($companyId, $type = null) {
        $sql = "SELECT DISTINCT category 
                FROM transactions 
                WHERE company_id = ? AND category IS NOT NULL";
        
        $params = [$companyId];
        
        if ($type) {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY category";
        
        $stmt = executeQuery($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Mark transaction as reconciled
     * 
     * @param int $transactionId Transaction ID
     * @param int $companyId Company ID (for security check)
     * @param int $userId User ID who reconciled
     * @param string|null $date Reconciliation date (defaults to today)
     * @return bool Success
     */
    public static function reconcile($transactionId, $companyId, $userId, $date = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $sql = "UPDATE transactions 
                SET is_reconciled = 1, reconciled_date = ?, reconciled_by = ?
                WHERE transaction_id = ? AND company_id = ?";
        
        executeQuery($sql, [$date, $userId, $transactionId, $companyId]);
        return true;
    }
    
    /**
     * Unreconcile transaction
     * 
     * @param int $transactionId Transaction ID
     * @param int $companyId Company ID (for security check)
     * @return bool Success
     */
    public static function unreconcile($transactionId, $companyId) {
        $sql = "UPDATE transactions 
                SET is_reconciled = 0, reconciled_date = NULL, reconciled_by = NULL
                WHERE transaction_id = ? AND company_id = ?";
        
        executeQuery($sql, [$transactionId, $companyId]);
        return true;
    }
    
    /**
     * Bulk reconcile multiple transactions
     * 
     * @param array $transactionIds Array of transaction IDs
     * @param int $companyId Company ID (for security check)
     * @param int $userId User ID who reconciled
     * @param string|null $date Reconciliation date (defaults to today)
     * @return int Number of transactions reconciled
     */
    public static function bulkReconcile($transactionIds, $companyId, $userId, $date = null) {
        if (empty($transactionIds)) {
            return 0;
        }
        
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
        $sql = "UPDATE transactions 
                SET is_reconciled = 1, reconciled_date = ?, reconciled_by = ?
                WHERE transaction_id IN ($placeholders) AND company_id = ?";
        
        $params = array_merge([$date, $userId], $transactionIds, [$companyId]);
        $stmt = executeQuery($sql, $params);
        
        return $stmt->rowCount();
    }
    
    /**
     * Get unreconciled transactions
     * 
     * @param int $companyId Company ID
     * @param array $filters Optional filters
     * @return array List of unreconciled transactions
     */
    public static function getUnreconciled($companyId, $filters = []) {
        $sql = "SELECT t.*, u.full_name as created_by_name
                FROM transactions t
                LEFT JOIN users u ON t.created_by = u.user_id
                WHERE t.company_id = ? AND t.is_reconciled = 0";
        
        $params = [$companyId];
        
        if (!empty($filters['type'])) {
            $sql .= " AND t.type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND t.transaction_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND t.transaction_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        $sql .= " ORDER BY t.transaction_date ASC, t.created_at ASC";
        
        $stmt = executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get reconciliation statistics
     * 
     * @param int $companyId Company ID
     * @param string|null $startDate Start date filter
     * @param string|null $endDate End date filter
     * @return array Reconciliation statistics
     */
    public static function getReconciliationStats($companyId, $startDate = null, $endDate = null) {
        $sql = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN is_reconciled = 1 THEN 1 ELSE 0 END) as reconciled_count,
                    SUM(CASE WHEN is_reconciled = 0 THEN 1 ELSE 0 END) as unreconciled_count,
                    SUM(CASE WHEN is_reconciled = 1 AND type = 'in' THEN amount ELSE 0 END) as reconciled_in,
                    SUM(CASE WHEN is_reconciled = 1 AND type = 'out' THEN amount ELSE 0 END) as reconciled_out,
                    SUM(CASE WHEN is_reconciled = 0 AND type = 'in' THEN amount ELSE 0 END) as unreconciled_in,
                    SUM(CASE WHEN is_reconciled = 0 AND type = 'out' THEN amount ELSE 0 END) as unreconciled_out
                FROM transactions
                WHERE company_id = ?";
        
        $params = [$companyId];
        
        if ($startDate) {
            $sql .= " AND transaction_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND transaction_date <= ?";
            $params[] = $endDate;
        }
        
        $stmt = executeQuery($sql, $params);
        $result = $stmt->fetch();
        
        $result['reconciled_net'] = $result['reconciled_in'] - $result['reconciled_out'];
        $result['unreconciled_net'] = $result['unreconciled_in'] - $result['unreconciled_out'];
        $result['reconciliation_rate'] = $result['total_transactions'] > 0 
            ? round(($result['reconciled_count'] / $result['total_transactions']) * 100, 2)
            : 0;
        
        return $result;
    }
    
    /**
     * Move transaction to another company
     * 
     * @param int $transactionId Transaction ID
     * @param int $fromCompanyId Source company ID (for verification)
     * @param int $toCompanyId Target company ID
     * @return bool Success
     */
    public static function moveToCompany($transactionId, $fromCompanyId, $toCompanyId) {
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
            // Verify transaction belongs to source company
            $stmt = $pdo->prepare("SELECT transaction_id FROM transactions WHERE transaction_id = ? AND company_id = ?");
            $stmt->execute([$transactionId, $fromCompanyId]);
            
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                return false;
            }
            
            // Update transaction's company_id
            $stmt = $pdo->prepare("UPDATE transactions SET company_id = ? WHERE transaction_id = ?");
            $stmt->execute([$toCompanyId, $transactionId]);
            
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error moving transaction: " . $e->getMessage());
            return false;
        }
    }
}
