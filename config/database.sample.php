<?php
/**
 * Database Configuration
 * Multi-Company Cash Flow Tracking System
 *
 * HOW TO USE ON YOUR SERVER:
 * 1. Copy this file to config/database.php
 * 2. Fill in your DB credentials and password below
 * 3. WEB_ROOT: leave '' for subdomain root, use '/folder' if in a subfolder
 */

// =============================================
// EDIT THESE VALUES FOR YOUR SERVER
// =============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'mdoffic1_sales_cash_flow');
define('DB_USER', 'mdoffic1_sales');
define('DB_PASS', 'YOUR_PASSWORD_HERE');

// Leave '' for subdomain root (e.g. cashflow.example.com)
// Use '/sales' if installed in a subfolder (e.g. example.com/sales)
if (!defined('WEB_ROOT')) {
    define('WEB_ROOT', '');
}
// =============================================

// Database connection options
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_unicode_ci');

/**
 * Get PDO database connection
 */
function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please check configuration.");
        }
    }

    return $pdo;
}

/**
 * Execute a prepared statement with parameters
 */
function executeQuery($sql, $params = []) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

