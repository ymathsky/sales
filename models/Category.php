<?php
/**
 * Category Model
 * Handles transaction category operations
 */

require_once __DIR__ . '/../config/database.php';

class Category {
    
    /**
     * Get all categories
     * 
     * @param bool $activeOnly Only get active categories
     * @param string|null $type Filter by type (in/out/both)
     * @param int|null $companyId Filter by company (NULL for global categories)
     * @return array List of categories
     */
    public static function getAll($activeOnly = true, $type = null, $companyId = null) {
        $sql = "SELECT c.*, co.name as company_name 
                FROM categories c
                LEFT JOIN companies co ON c.company_id = co.company_id
                WHERE 1=1";
        $params = [];
        
        if ($activeOnly) {
            $sql .= " AND c.is_active = 1";
        }
        
        if ($type) {
            $sql .= " AND (c.type = ? OR c.type = 'both')";
            $params[] = $type;
        }
        
        // Filter by company: show global (NULL) and company-specific
        if ($companyId !== null) {
            $sql .= " AND (c.company_id IS NULL OR c.company_id = ?)";
            $params[] = $companyId;
        }
        
        $sql .= " ORDER BY c.name ASC";
        
        $stmt = executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get category by ID
     * 
     * @param int $categoryId Category ID
     * @return array|false Category data or false
     */
    public static function getById($categoryId) {
        $sql = "SELECT * FROM categories WHERE category_id = ?";
        $stmt = executeQuery($sql, [$categoryId]);
        return $stmt->fetch();
    }
    
    /**
     * Create new category
     * 
     * @param array $data Category data
     * @return int New category ID
     */
    public static function create($data) {
        $sql = "INSERT INTO categories (company_id, name, type, description, is_active) 
                VALUES (?, ?, ?, ?, ?)";
        
        executeQuery($sql, [
            $data['company_id'] ?? null,
            $data['name'],
            $data['type'] ?? 'both',
            $data['description'] ?? null,
            $data['is_active'] ?? 1
        ]);
        
        $pdo = getDBConnection();
        return $pdo->lastInsertId();
    }
    
    /**
     * Update category
     * 
     * @param int $categoryId Category ID
     * @param array $data Category data
     * @return bool Success status
     */
    public static function update($categoryId, $data) {
        $sql = "UPDATE categories 
                SET company_id = ?, name = ?, type = ?, description = ?, is_active = ?
                WHERE category_id = ?";
        
        executeQuery($sql, [
            $data['company_id'] ?? null,
            $data['name'],
            $data['type'] ?? 'both',
            $data['description'] ?? null,
            $data['is_active'] ?? 1,
            $categoryId
        ]);
        
        return true;
    }
    
    /**
     * Delete category
     * 
     * @param int $categoryId Category ID
     * @return bool Success status
     */
    public static function delete($categoryId) {
        $sql = "DELETE FROM categories WHERE category_id = ?";
        executeQuery($sql, [$categoryId]);
        return true;
    }
    
    /**
     * Check if category is in use
     * 
     * @param int $categoryId Category ID
     * @return int Count of transactions using this category
     */
    public static function getUsageCount($categoryId) {
        $category = self::getById($categoryId);
        if (!$category) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as count FROM transactions WHERE category = ?";
        $stmt = executeQuery($sql, [$category['name']]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }
}
