<?php
/**
 * API endpoint to switch company in session
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$companyId = (int)($data['company_id'] ?? 0);

if ($companyId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid company ID']);
    exit;
}

// Check if user has access to this company
if (!userHasAccessToCompany(getCurrentUserId(), $companyId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Update session
setActiveCompany($companyId);

echo json_encode([
    'success' => true, 
    'message' => 'Company switched successfully',
    'company_id' => $companyId
]);
