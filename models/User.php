<?php
/**
 * User Model
 * Handles user authentication and management
 */

require_once __DIR__ . '/../config/database.php';

class User {
    
    /**
     * Authenticate user with username and password
     * 
     * @param string $username Username
     * @param string $password Password
     * @return array|false User data or false
     */
    public static function authenticate($username, $password) {
        $sql = "SELECT * FROM users WHERE username = ? AND status = 'active'";
        $stmt = executeQuery($sql, [$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Update last login
            $updateSql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
            executeQuery($updateSql, [$user['user_id']]);
            
            return $user;
        }
        
        return false;
    }
    
    /**
     * Get user by ID
     * 
     * @param int $userId User ID
     * @return array|false User data or false
     */
    public static function getById($userId) {
        $sql = "SELECT * FROM users WHERE user_id = ?";
        $stmt = executeQuery($sql, [$userId]);
        return $stmt->fetch();
    }
    
    /**
     * Create new user
     * 
     * @param array $data User data
     * @return int New user ID
     */
    public static function create($data) {
        $sql = "INSERT INTO users (username, password_hash, full_name, email, role) 
                VALUES (?, ?, ?, ?, ?)";
        
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        executeQuery($sql, [
            $data['username'],
            $passwordHash,
            $data['full_name'],
            $data['email'],
            $data['role'] ?? 'user'
        ]);
        
        $pdo = getDBConnection();
        return $pdo->lastInsertId();
    }
    
    /**
     * Update user
     * 
     * @param int $userId User ID
     * @param array $data User data
     * @return bool Success
     */
    public static function update($userId, $data) {
        $sql = "UPDATE users SET full_name = ?, email = ?, role = ? WHERE user_id = ?";
        
        executeQuery($sql, [
            $data['full_name'],
            $data['email'],
            $data['role'],
            $userId
        ]);
        
        return true;
    }
    
    /**
     * Change user password
     * 
     * @param int $userId User ID
     * @param string $newPassword New password
     * @return bool Success
     */
    public static function changePassword($userId, $newPassword) {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password_hash = ? WHERE user_id = ?";
        executeQuery($sql, [$passwordHash, $userId]);
        return true;
    }
    
    /**
     * Grant user access to company
     * 
     * @param int $userId User ID
     * @param int $companyId Company ID
     * @param string $accessLevel Access level (read/write/admin)
     * @return bool Success
     */
    public static function grantCompanyAccess($userId, $companyId, $accessLevel = 'read') {
        $sql = "INSERT INTO user_companies (user_id, company_id, access_level) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE access_level = ?";
        
        executeQuery($sql, [$userId, $companyId, $accessLevel, $accessLevel]);
        return true;
    }
}
