<?php
/**
 * Session Management
 * Handles user authentication and session state
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration if available
if (file_exists(__DIR__ . '/../config/database.php')) {
    require_once __DIR__ . '/../config/database.php';
}

// Fallback for WEB_ROOT if not defined (e.g. config missing)
if (!defined('WEB_ROOT')) {
    define('WEB_ROOT', '');
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireLogin() {
    // Ensure WEB_ROOT is defined
    $webRoot = defined('WEB_ROOT') ? WEB_ROOT : '';
    
    if (!isLoggedIn()) {
        header('Location: ' . $webRoot . '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Get current user ID
 * 
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 * 
 * @return string|null User role or null if not logged in
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user full name or username
 * 
 * @return string|null User name or null
 */
function getCurrentUserName() {
    return $_SESSION['full_name'] ?? $_SESSION['username'] ?? null;
}

/**
 * Get current active company ID
 * 
 * @return int|null Company ID or null if not set
 */
function getCurrentCompanyId() {
    return $_SESSION['active_company_id'] ?? null;
}

/**
 * Set active company
 * 
 * @param int $companyId Company ID to set as active
 */
function setActiveCompany($companyId) {
    $_SESSION['active_company_id'] = (int)$companyId;
}

/**
 * Check if user has access to a company
 * 
 * @param int $userId User ID
 * @param int $companyId Company ID
 * @return bool True if user has access
 */
function userHasAccessToCompany($userId, $companyId) {
    require_once __DIR__ . '/../config/database.php';
    
    $sql = "SELECT COUNT(*) as count FROM user_companies 
            WHERE user_id = ? AND company_id = ?";
    $stmt = executeQuery($sql, [$userId, $companyId]);
    $result = $stmt->fetch();
    
    return $result['count'] > 0;
}

/**
 * Check if user has write access to a company
 * 
 * @param int $userId User ID
 * @param int $companyId Company ID
 * @return bool True if user has write access
 */
function userHasWriteAccess($userId, $companyId) {
    require_once __DIR__ . '/../config/database.php';
    
    $sql = "SELECT access_level FROM user_companies 
            WHERE user_id = ? AND company_id = ?";
    $stmt = executeQuery($sql, [$userId, $companyId]);
    $result = $stmt->fetch();
    
    return $result && in_array($result['access_level'], ['write', 'admin']);
}

/**
 * Verify current user has access to company or die
 * 
 * @param int $companyId Company ID to verify
 */
function requireCompanyAccess($companyId) {
    $userId = getCurrentUserId();
    if (!$userId || !userHasAccessToCompany($userId, $companyId)) {
        http_response_code(403);
        die('Access denied to this company');
    }
}

/**
 * Require admin role - redirect or die if not admin
 */
function requireAdmin() {
    requireLogin();
    if (getCurrentUserRole() !== 'admin') {
        http_response_code(403);
        die('Access denied. Admin privileges required.');
    }
}

// Global Company Switcher Logic
// Automatically handles company switching when ?company=ID is present in URL
if (isset($_GET['company']) && isLoggedIn()) {
    $requestedCompanyId = (int)$_GET['company'];
    // Only switch if different from current and user has access
    if ($requestedCompanyId !== getCurrentCompanyId() && userHasAccessToCompany(getCurrentUserId(), $requestedCompanyId)) {
        setActiveCompany($requestedCompanyId);
    }
}

/**
 * Check if current user has specific permission
 * 
 * @param string $permission Permission key
 * @return bool
 */
function hasPermission($permission) {
    $role = getCurrentUserRole();
    if (!$role) return false;
    
    // Admin role always has access (hard override)
    if ($role === 'admin') return true;
    
    require_once __DIR__ . '/../config/database.php';
    
    // Static cache processing per request to avoid multiple DB calls
    static $permissionsCache = [];
    if (isset($permissionsCache[$role][$permission])) {
        return $permissionsCache[$role][$permission];
    }

    $sql = "SELECT is_granted FROM role_permissions WHERE role = ? AND permission_key = ?";
    $stmt = executeQuery($sql, [$role, $permission]);
    $result = $stmt->fetch();
    
    $isGranted = ($result && $result['is_granted'] == 1);
    $permissionsCache[$role][$permission] = $isGranted;
    
    return $isGranted;
}

/**
 * Require specific permission
 * 
 * @param string $permission Permission key
 */
function requirePermission($permission) {
    requireLogin();
    if (!hasPermission($permission)) {
        http_response_code(403);
        die("Access denied. Permission required: $permission");
    }
}
