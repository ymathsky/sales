<?php
/**
 * Fund Transfer Model
 * Handles deposits, withdrawals, and check transactions between cash and bank
 */

require_once __DIR__ . '/../config/database.php';

class FundTransfer {
    
    /**
     * Create new fund transfer
     * 
     * @param array $data Transfer data
     * @return int New transfer ID
     */
    public static function create($data) {
        $sql = "INSERT INTO fund_transfers 
                (company_id, transfer_type, amount, transfer_date, check_number, description, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        executeQuery($sql, [
            $data['company_id'],
            $data['transfer_type'],
            $data['amount'],
            $data['transfer_date'],
            $data['check_number'] ?? null,
            $data['description'] ?? null,
            $data['created_by']
        ]);
        
        $pdo = getDBConnection();
        return $pdo->lastInsertId();
    }
    
    /**
     * Get fund transfers by company
     * 
     * @param int $companyId Company ID
     * @param array $filters Optional filters
     * @return array List of transfers
     */
    public static function getByCompany($companyId, $filters = []) {
        $sql = "SELECT ft.*, u.full_name as created_by_name
                FROM fund_transfers ft
                LEFT JOIN users u ON ft.created_by = u.user_id
                WHERE ft.company_id = ?";
        
        $params = [$companyId];
        
        if (!empty($filters['transfer_type'])) {
            $sql .= " AND ft.transfer_type = ?";
            $params[] = $filters['transfer_type'];
        }
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND ft.transfer_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND ft.transfer_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        $sql .= " ORDER BY ft.transfer_date DESC, ft.created_at DESC";
        
        $stmt = executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get transfer by ID
     * 
     * @param int $transferId Transfer ID
     * @param int $companyId Company ID (for security check)
     * @return array|false Transfer data or false
     */
    public static function getById($transferId, $companyId) {
        $sql = "SELECT ft.*, u.full_name as created_by_name
                FROM fund_transfers ft
                LEFT JOIN users u ON ft.created_by = u.user_id
                WHERE ft.transfer_id = ? AND ft.company_id = ?";
        
        $stmt = executeQuery($sql, [$transferId, $companyId]);
        return $stmt->fetch();
    }
    
    /**
     * Delete transfer
     * 
     * @param int $transferId Transfer ID
     * @param int $companyId Company ID (for security check)
     * @return bool Success
     */
    public static function delete($transferId, $companyId) {
        $sql = "DELETE FROM fund_transfers WHERE transfer_id = ? AND company_id = ?";
        executeQuery($sql, [$transferId, $companyId]);
        return true;
    }
    
    /**
     * Get transfer totals
     * 
     * @param int $companyId Company ID
     * @param string|null $startDate Start date filter
     * @param string|null $endDate End date filter
     * @return array Transfer totals by type
     */
    public static function getTotals($companyId, $startDate = null, $endDate = null) {
        $sql = "SELECT 
                    transfer_type,
                    SUM(amount) as total_amount,
                    COUNT(*) as count
                FROM fund_transfers
                WHERE company_id = ?";
        
        $params = [$companyId];
        
        if ($startDate) {
            $sql .= " AND transfer_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND transfer_date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " GROUP BY transfer_type";
        
        $stmt = executeQuery($sql, $params);
        $results = $stmt->fetchAll();
        
        $totals = [
            'deposit' => 0,
            'withdrawal' => 0,
            'check' => 0
        ];
        
        foreach ($results as $row) {
            $totals[$row['transfer_type']] = $row['total_amount'];
        }
        
        return $totals;
    }
}
