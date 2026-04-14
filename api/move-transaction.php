<?php
/**
 * API endpoint: Move a transaction to another company
 * POST { transaction_id, target_company_id }
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Company.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$transactionId  = isset($input['transaction_id'])  ? (int)$input['transaction_id']  : 0;
$targetCompanyId = isset($input['target_company_id']) ? (int)$input['target_company_id'] : 0;

if ($transactionId <= 0 || $targetCompanyId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'transaction_id and target_company_id are required']);
    exit;
}

$userId          = getCurrentUserId();
$currentCompanyId = getCurrentCompanyId();

if (!$currentCompanyId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No active company in session']);
    exit;
}

// Verify the transaction belongs to the active company
$transaction = Transaction::getById($transactionId, $currentCompanyId);
if (!$transaction) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Transaction not found or access denied']);
    exit;
}

// Verify the user has access to the target company
if (!userHasAccessToCompany($userId, $targetCompanyId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You do not have access to the target company']);
    exit;
}

// Cannot move to the same company
if ($targetCompanyId === $currentCompanyId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Transaction is already in the selected company']);
    exit;
}

$targetCompany = Company::getById($targetCompanyId);
if (!$targetCompany) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Target company not found']);
    exit;
}

if (Transaction::moveToCompany($transactionId, $currentCompanyId, $targetCompanyId)) {
    echo json_encode([
        'success' => true,
        'message' => 'Transaction moved to ' . $targetCompany['name'],
        'target_company' => $targetCompany['name'],
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to move transaction. Please try again.']);
}
