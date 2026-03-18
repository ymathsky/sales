<?php
/**
 * Product Model
 * Handles products and services management for POS
 */

require_once __DIR__ . '/../config/database.php';

class Product {
    
    /**
     * Get all products for a company
     */
    public static function getByCompany($companyId, $activeOnly = true, $category = null) {
        $sql = "SELECT * FROM products WHERE company_id = ?";
        $params = [$companyId];
        
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY product_name ASC";
        
        $stmt = executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get product by ID
     */
    public static function getById($productId, $companyId) {
        $sql = "SELECT * FROM products WHERE product_id = ? AND company_id = ?";
        $stmt = executeQuery($sql, [$productId, $companyId]);
        return $stmt->fetch();
    }
    
    /**
     * Create new product
     */
    public static function create($data) {
        $sql = "INSERT INTO products 
                (company_id, product_name, description, sku, price, cost, 
                 stock_quantity, track_inventory, is_service, category, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        executeQuery($sql, [
            $data['company_id'],
            $data['product_name'],
            $data['description'] ?? null,
            $data['sku'] ?? null,
            $data['price'],
            $data['cost'] ?? 0,
            $data['stock_quantity'] ?? 0,
            $data['track_inventory'] ?? 0,
            $data['is_service'] ?? 0,
            $data['category'] ?? null,
            $data['is_active'] ?? 1
        ]);
        
        $pdo = getDBConnection();
        return $pdo->lastInsertId();
    }
    
    /**
     * Update product
     */
    public static function update($productId, $companyId, $data) {
        $sql = "UPDATE products SET 
                product_name = ?, description = ?, sku = ?, price = ?, cost = ?,
                stock_quantity = ?, track_inventory = ?, is_service = ?, 
                category = ?, is_active = ?
                WHERE product_id = ? AND company_id = ?";
        
        return executeQuery($sql, [
            $data['product_name'],
            $data['description'] ?? null,
            $data['sku'] ?? null,
            $data['price'],
            $data['cost'] ?? 0,
            $data['stock_quantity'] ?? 0,
            $data['track_inventory'] ?? 0,
            $data['is_service'] ?? 0,
            $data['category'] ?? null,
            $data['is_active'] ?? 1,
            $productId,
            $companyId
        ]);
    }
    
    /**
     * Delete product
     */
    public static function delete($productId, $companyId) {
        $sql = "DELETE FROM products WHERE product_id = ? AND company_id = ?";
        return executeQuery($sql, [$productId, $companyId]);
    }
    
    /**
     * Update stock quantity
     */
    public static function updateStock($productId, $companyId, $quantity, $operation = 'subtract') {
        if ($operation === 'subtract') {
            $sql = "UPDATE products SET stock_quantity = stock_quantity - ? 
                    WHERE product_id = ? AND company_id = ? AND track_inventory = 1";
        } else {
            $sql = "UPDATE products SET stock_quantity = stock_quantity + ? 
                    WHERE product_id = ? AND company_id = ? AND track_inventory = 1";
        }
        
        return executeQuery($sql, [$quantity, $productId, $companyId]);
    }
    
    /**
     * Get all categories for a company
     */
    public static function getCategories($companyId) {
        $sql = "SELECT DISTINCT category FROM products 
                WHERE company_id = ? AND category IS NOT NULL 
                ORDER BY category ASC";
        $stmt = executeQuery($sql, [$companyId]);
        return array_column($stmt->fetchAll(), 'category');
    }
    
    /**
     * Search products
     */
    public static function search($companyId, $searchTerm) {
        $sql = "SELECT * FROM products 
                WHERE company_id = ? AND is_active = 1
                AND (product_name LIKE ? OR sku LIKE ? OR description LIKE ?)
                ORDER BY product_name ASC
                LIMIT 20";
        
        $term = "%$searchTerm%";
        $stmt = executeQuery($sql, [$companyId, $term, $term, $term]);
        return $stmt->fetchAll();
    }
}
