<?php
/**
 * API Login Endpoint
 * Authenticates users for mobile apps and returns session/user info
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Company.php';

header('Content-Type: application/json');

// specific headers for CORS if testing from localhost during dev
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? $_POST['username'] ?? '';
$password = $input['password'] ?? $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Username and password required']);
    exit;
}

$user = User::authenticate($username, $password);

if ($user) {
    // Start Session (or regenerate ID for security)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);

    // Set session variables (Same as normal login)
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];

    // Get user companies to help app decide context
    $companies = Company::getByUser($user['user_id']);
    
    // Auto-select first company if none selected
    $activeCompanyId = getCurrentCompanyId();
    if (!$activeCompanyId && !empty($companies)) {
        $activeCompanyId = $companies[0]['company_id'];
        setActiveCompany($activeCompanyId);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ],
        'permissions' => getCurrentPermissions(),
        'active_company_id' => $activeCompanyId,
        'companies' => $companies,
        'session_id' => session_id() // Useful for debugging, but cookie usually handles this
    ]);
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
}
