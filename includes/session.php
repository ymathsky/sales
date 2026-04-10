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
    
    $permissions = getCurrentPermissions();
    return !empty($permissions[$permission]);
}

/**
 * Resolve current user permissions from defaults + role table + user overrides.
 *
 * @return array<string,bool>
 */
function getCurrentPermissions() {
    $role = getCurrentUserRole();
    $userId = getCurrentUserId();

    if (!$role || !$userId) {
        return [];
    }

    static $cache = [];
    $cacheKey = $role . ':' . $userId;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $permissions = getDefaultRolePermissions($role);

    try {
        $sql = "SELECT permission_key, is_granted FROM role_permissions WHERE role = ?";
        $rows = executeQuery($sql, [$role])->fetchAll();
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $permissions[$row['permission_key']] = ((int)$row['is_granted'] === 1);
            }
        }
    } catch (Throwable $e) {
        // Ignore role_permissions table issues and keep defaults.
    }

    try {
        $sql = "SELECT permission_key, is_granted FROM user_permissions WHERE user_id = ?";
        $rows = executeQuery($sql, [$userId])->fetchAll();
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $permissions[$row['permission_key']] = ((int)$row['is_granted'] === 1);
            }
        }
    } catch (Throwable $e) {
        // Ignore user override table issues.
    }

    $cache[$cacheKey] = $permissions;
    return $permissions;
}

/**
 * Baseline permission matrix by role.
 *
 * @param string $role
 * @return array<string,bool>
 */
function getDefaultRolePermissions($role) {
    $all = [
        'view_dashboard',
        'create_sales',
        'create_transactions',
        'edit_transactions',
        'delete_transactions',
        'manage_customers',
        'manage_invoices',
        'edit_categories',
        'view_reports',
        'manage_users',
        'manage_settings',
    ];

    $denyAll = array_fill_keys($all, false);

    $roleMap = [
        'owner' => array_fill_keys($all, true),
        'admin' => array_fill_keys($all, true),
        'accounting' => array_merge($denyAll, [
            'view_dashboard' => true,
            'create_sales' => true,
            'create_transactions' => true,
            'edit_transactions' => true,
            'delete_transactions' => true,
            'manage_customers' => true,
            'manage_invoices' => true,
            'edit_categories' => true,
            'view_reports' => true,
        ]),
        'cashier' => array_merge($denyAll, [
            'view_dashboard' => true,
            'create_sales' => true,
            'create_transactions' => true,
        ]),
    ];

    if (isset($roleMap[$role])) {
        return $roleMap[$role];
    }

    return array_merge($denyAll, [
        'view_dashboard' => true,
        'create_transactions' => true,
    ]);
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
