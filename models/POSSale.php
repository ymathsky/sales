<?php
/**
 * POS Sale Model
 * Handles point of sale transactions
 */

require_once __DIR__ . '/../config/database.php';

class POSSale {
    
    /**
     * Create new sale
     */
    public static function create($saleData, $items) {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        try {
            // Generate sale number
            $saleNumber = self::generateSaleNumber($saleData['company_id']);
            
            // Insert sale
            $sql = "INSERT INTO pos_sales 
                    (company_id, sale_number, sale_date, subtotal, tax_amount, 
                     discount_amount, total_amount, payment_method, payment_received, 
                     change_amount, customer_name, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            executeQuery($sql, [
                $saleData['company_id'],
                $saleNumber,
                $saleData['sale_date'],
                $saleData['subtotal'],
                $saleData['tax_amount'] ?? 0,
                $saleData['discount_amount'] ?? 0,
                $saleData['total_amount'],
                $saleData['payment_method'],
                $saleData['payment_received'],
                $saleData['change_amount'],
                $saleData['customer_name'] ?? null,
                $saleData['notes'] ?? null,
                $saleData['created_by']
            ]);
            
            $saleId = $pdo->lastInsertId();
            
            // Insert sale items
            foreach ($items as $item) {
                $sql = "INSERT INTO pos_sale_items 
                        (sale_id, product_id, product_name, quantity, unit_price, 
                         discount_amount, line_total) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                executeQuery($sql, [
                    $saleId,
                    $item['product_id'] ?? null,
                    $item['product_name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['discount_amount'] ?? 0,
                    $item['line_total']
                ]);
                
                // Update inventory
                if (!empty($item['product_id'])) {
                    require_once __DIR__ . '/Product.php';
                    Product::updateStock($item['product_id'], $saleData['company_id'], $item['quantity'], 'subtract');
                }
            }
            
            $pdo->commit();
            return ['sale_id' => $saleId, 'sale_number' => $saleNumber];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Generate unique sale number
     */
    private static function generateSaleNumber($companyId) {
        $prefix = 'POS-' . date('Ymd') . '-';
        
        $sql = "SELECT sale_number FROM pos_sales 
                WHERE company_id = ? AND sale_number LIKE ? 
                ORDER BY sale_id DESC LIMIT 1";
        
        $stmt = executeQuery($sql, [$companyId, $prefix . '%']);
        $lastSale = $stmt->fetch();
        
        if ($lastSale) {
            $lastNumber = intval(substr($lastSale['sale_number'], -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get sale by ID with items
     */
    public static function getById($saleId, $companyId) {
        $sql = "SELECT ps.*, u.full_name as cashier_name 
                FROM pos_sales ps
                LEFT JOIN users u ON ps.created_by = u.user_id
                WHERE ps.sale_id = ? AND ps.company_id = ?";
        $stmt = executeQuery($sql, [$saleId, $companyId]);
        $sale = $stmt->fetch();
        
        if ($sale) {
            $sql = "SELECT * FROM pos_sale_items WHERE sale_id = ?";
            $stmt = executeQuery($sql, [$saleId]);
            $sale['items'] = $stmt->fetchAll();
        }
        
        return $sale;
    }
    
    /**
     * Get sales by company
     */
    public static function getByCompany($companyId, $filters = [], $limit = 50) {
        $sql = "SELECT ps.*, u.full_name as cashier_name,
                    COUNT(psi.sale_item_id) as item_count
                FROM pos_sales ps
                LEFT JOIN users u ON ps.created_by = u.user_id
                LEFT JOIN pos_sale_items psi ON ps.sale_id = psi.sale_id
                WHERE ps.company_id = ?";
        
        $params = [$companyId];
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(ps.sale_date) >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(ps.sale_date) <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (ps.sale_number LIKE ? OR ps.customer_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['payment_method'])) {
            $sql .= " AND ps.payment_method = ?";
            $params[] = $filters['payment_method'];
        }
        
        $sql .= " GROUP BY ps.sale_id ORDER BY ps.sale_date DESC, ps.sale_id DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Link sale to transaction
     */
    public static function linkTransaction($saleId, $companyId, $transactionId) {
        $sql = "UPDATE pos_sales SET transaction_id = ? 
                WHERE sale_id = ? AND company_id = ?";
        return executeQuery($sql, [$transactionId, $saleId, $companyId]);
    }

    /**
     * Delete sale and restore inventory
     */
    public static function delete($saleId, $companyId) {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        try {
            // Verify sale belongs to company
            $sql = "SELECT sale_id FROM pos_sales WHERE sale_id = ? AND company_id = ?";
            $stmt = executeQuery($sql, [$saleId, $companyId]);
            if (!$stmt->fetch()) {
                 throw new Exception("Sale not found or access denied.");
            }

            // Get items to restore inventory
            $sql = "SELECT * FROM pos_sale_items WHERE sale_id = ?";
            $stmt = executeQuery($sql, [$saleId]);
            $items = $stmt->fetchAll();
            
            // Restore inventory
            require_once __DIR__ . '/Product.php';
            foreach ($items as $item) {
                if (!empty($item['product_id'])) {
                    Product::updateStock($item['product_id'], $companyId, $item['quantity'], 'add');
                }
            }
            
            // Delete items
            $sql = "DELETE FROM pos_sale_items WHERE sale_id = ?";
            executeQuery($sql, [$saleId]);
            
            // Delete sale
            $sql = "DELETE FROM pos_sales WHERE sale_id = ? AND company_id = ?";
            executeQuery($sql, [$saleId, $companyId]);
            
            $pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
