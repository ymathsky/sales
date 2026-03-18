<?php
/**
 * Company Model
 * Handles company data operations
 */

require_once __DIR__ . '/../config/database.php';

class Company {
    
    /**
     * Get all companies accessible by user
     * 
     * @param int $userId User ID
     * @return array List of companies
     */
    public static function getByUser($userId) {
        $sql = "SELECT c.*, uc.access_level 
                FROM companies c
                INNER JOIN user_companies uc ON c.company_id = uc.company_id
                WHERE uc.user_id = ? AND c.status = 'active'
                ORDER BY c.name";
        
        $stmt = executeQuery($sql, [$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get company by ID
     * 
     * @param int $companyId Company ID
     * @return array|false Company data or false
     */
    public static function getById($companyId) {
        $sql = "SELECT * FROM companies WHERE company_id = ?";
        $stmt = executeQuery($sql, [$companyId]);
        return $stmt->fetch();
    }
    
    /**
     * Create new company
     * 
     * @param array $data Company data
     * @return int New company ID
     */
    public static function create($data) {
        $sql = "INSERT INTO companies (name, address, phone, email, tax_id) 
                VALUES (?, ?, ?, ?, ?)";
        
        executeQuery($sql, [
            $data['name'],
            $data['address'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['tax_id'] ?? null
        ]);
        
        $pdo = getDBConnection();
        return $pdo->lastInsertId();
    }
    
    /**
     * Update company
     * 
     * @param int $companyId Company ID
     * @param array $data Company data
     * @return bool Success
     */
    public static function update($companyId, $data) {
        $sql = "UPDATE companies 
                SET name = ?, address = ?, phone = ?, email = ?, tax_id = ?
                WHERE company_id = ?";
        
        executeQuery($sql, [
            $data['name'],
            $data['address'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['tax_id'] ?? null,
            $companyId
        ]);
        
        return true;
    }
    
    /**
     * Get company balance (cash in - cash out)
     * 
     * @param int $companyId Company ID
     * @param string|null $startDate Start date filter
     * @param string|null $endDate End date filter
     * @return array Balance summary
     */
    public static function getBalance($companyId, $startDate = null, $endDate = null) {
        $sql = "SELECT 
                    SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as total_in,
                    SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as total_out,
                    COUNT(CASE WHEN type = 'in' THEN 1 END) as count_in,
                    COUNT(CASE WHEN type = 'out' THEN 1 END) as count_out
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
        
        $result['balance'] = $result['total_in'] - $result['total_out'];
        return $result;
    }
    
    /**
     * Get company cash on hand (all-time total balance)
     * 
     * @param int $companyId Company ID
     * @return float Total cash on hand
     */
    public static function getCashOnHand($companyId) {
        $sql = "SELECT 
                    SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as total_in,
                    SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as total_out
                FROM transactions 
                WHERE company_id = ?";
        
        $stmt = executeQuery($sql, [$companyId]);
        $result = $stmt->fetch();
        
        return ($result['total_in'] ?? 0) - ($result['total_out'] ?? 0);
    }
    
    /**
     * Update opening balance for a company
     * 
     * @param int $companyId Company ID
     * @param float $cashAmount Cash opening balance amount
     * @param string $cashDate Cash opening balance date
     * @param float $bankAmount Bank opening balance amount
     * @param string $bankDate Bank opening balance date
     * @return bool Success
     */
    public static function updateOpeningBalance($companyId, $cashAmount, $cashDate, $bankAmount = 0, $bankDate = null) {
        $sql = "UPDATE companies 
                SET opening_balance = ?, opening_balance_date = ?,
                    bank_opening_balance = ?, bank_opening_balance_date = ?
                WHERE company_id = ?";
        
        executeQuery($sql, [$cashAmount, $cashDate, $bankAmount, $bankDate, $companyId]);
        return true;
    }
    
    /**
     * Get opening balance for a company
     * 
     * @param int $companyId Company ID
     * @return array Opening balance data (cash and bank amounts with dates)
     */
    public static function getOpeningBalance($companyId) {
        $sql = "SELECT opening_balance, opening_balance_date,
                       bank_opening_balance, bank_opening_balance_date 
                FROM companies 
                WHERE company_id = ?";
        
        $stmt = executeQuery($sql, [$companyId]);
        $result = $stmt->fetch();
        
        return [
            'cash_amount' => $result['opening_balance'] ?? 0,
            'cash_date' => $result['opening_balance_date'] ?? null,
            'bank_amount' => $result['bank_opening_balance'] ?? 0,
            'bank_date' => $result['bank_opening_balance_date'] ?? null
        ];
    }
    
    /**
     * Get book balance (opening balance + transactions - fund transfers)
     * 
     * @param int $companyId Company ID
     * @param string|null $asOfDate Calculate balance as of this date
     * @param string|null $account Filter by account type ('cash' or 'bank')
     * @return array Book balance details
     */
    public static function getBookBalance($companyId, $asOfDate = null, $account = null) {
        $opening = self::getOpeningBalance($companyId);
        
        $sql = "SELECT 
                    SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as total_in,
                    SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as total_out
                FROM transactions 
                WHERE company_id = ?";
        
        $params = [$companyId];
        
        // Filter by account type
        if ($account) {
            $sql .= " AND transaction_account = ?";
            $params[] = $account;
        }
        
        // Get appropriate opening balance and date
        $openingAmount = ($account === 'bank') ? $opening['bank_amount'] : 
                        (($account === 'cash') ? $opening['cash_amount'] : 
                        ($opening['cash_amount'] + $opening['bank_amount']));
        $openingDate = ($account === 'bank') ? $opening['bank_date'] : $opening['cash_date'];
        
        // Only include transactions after opening balance date
        if ($openingDate) {
            $sql .= " AND transaction_date >= ?";
            $params[] = $openingDate;
        }
        
        // Filter by as-of date if provided
        if ($asOfDate) {
            $sql .= " AND transaction_date <= ?";
            $params[] = $asOfDate;
        }
        
        $stmt = executeQuery($sql, $params);
        $result = $stmt->fetch();
        
        $totalIn = $result['total_in'] ?? 0;
        $totalOut = $result['total_out'] ?? 0;
        
        // Get fund transfers (deposits reduce cash, increase bank; withdrawals increase cash, reduce bank)
        $transferSql = "SELECT 
                            SUM(CASE WHEN transfer_type = 'deposit' OR transfer_type = 'check' THEN amount ELSE 0 END) as total_deposits,
                            SUM(CASE WHEN transfer_type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawals
                        FROM fund_transfers
                        WHERE company_id = ?";
        
        $transferParams = [$companyId];
        
        if ($openingDate) {
            $transferSql .= " AND transfer_date >= ?";
            $transferParams[] = $openingDate;
        }
        
        if ($asOfDate) {
            $transferSql .= " AND transfer_date <= ?";
            $transferParams[] = $asOfDate;
        }
        
        $transferStmt = executeQuery($transferSql, $transferParams);
        $transfers = $transferStmt->fetch();
        
        $totalDeposits = $transfers['total_deposits'] ?? 0;
        $totalWithdrawals = $transfers['total_withdrawals'] ?? 0;
        
        // Calculate balance based on account type
        if ($account === 'cash') {
            // Cash: opening + in - out - deposits + withdrawals
            $netMovement = $totalIn - $totalOut - $totalDeposits + $totalWithdrawals;
        } elseif ($account === 'bank') {
            // Bank: opening + in - out + deposits - withdrawals
            $netMovement = $totalIn - $totalOut + $totalDeposits - $totalWithdrawals;
        } else {
            // Combined: transfers cancel out
            $netMovement = $totalIn - $totalOut;
        }
        
        $bookBalance = $openingAmount + $netMovement;
        
        return [
            'opening_balance' => $openingAmount,
            'opening_date' => $openingDate,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'total_deposits' => $totalDeposits,
            'total_withdrawals' => $totalWithdrawals,
            'net_movement' => $netMovement,
            'book_balance' => $bookBalance,
            'account_type' => $account ?? 'combined'
        ];
    }
    
    /**
     * Get bank balance (reconciled bank transactions only)
     * 
     * @param int $companyId Company ID
     * @param string|null $asOfDate Calculate balance as of this date
     * @return array Bank balance details
     */
    public static function getBankBalance($companyId, $asOfDate = null) {
        $opening = self::getOpeningBalance($companyId);
        
        $sql = "SELECT 
                    SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as total_in,
                    SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as total_out
                FROM transactions 
                WHERE company_id = ? AND transaction_account = 'bank' AND is_reconciled = 1";
        
        $params = [$companyId];
        
        // Only include transactions after bank opening balance date
        if ($opening['bank_date']) {
            $sql .= " AND transaction_date >= ?";
            $params[] = $opening['bank_date'];
        }
        
        // Filter by as-of date if provided
        if ($asOfDate) {
            $sql .= " AND reconciled_date <= ?";
            $params[] = $asOfDate;
        }
        
        $stmt = executeQuery($sql, $params);
        $result = $stmt->fetch();
        
        $totalIn = $result['total_in'] ?? 0;
        $totalOut = $result['total_out'] ?? 0;
        $netMovement = $totalIn - $totalOut;
        $bankBalance = $opening['bank_amount'] + $netMovement;
        
        return [
            'opening_balance' => $opening['bank_amount'],
            'opening_date' => $opening['bank_date'],
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'net_movement' => $netMovement,
            'bank_balance' => $bankBalance
        ];
    }
}
