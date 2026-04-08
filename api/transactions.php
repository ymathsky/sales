<?php
/**
 * API Transactions Endpoint
 * List and create transactions
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/Transaction.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$companyId = getCurrentCompanyId();
if (!$companyId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No active company selected']);
    exit;
}

// Ensure user still has access to this company
if (!userHasAccessToCompany(getCurrentUserId(), $companyId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// GET: List Transactions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 20;
    $offset = ($page - 1) * $limit;
    
    // Filters
    $filters = [
        'type' => $_GET['type'] ?? null,
        'search' => $_GET['search'] ?? null,
        'category' => $_GET['category'] ?? null,
        'start_date' => $_GET['start_date'] ?? null,
        'end_date' => $_GET['end_date'] ?? null
    ];

    $transactions = Transaction::getByCompany($companyId, $filters, $limit, $offset);
    
    echo json_encode([
        'success' => true,
        'page' => (int)$page,
        'limit' => (int)$limit,
        'count' => count($transactions),
        'data' => $transactions
    ]);
    exit;
}

// POST: Create Transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validation
    $required = ['type', 'amount', 'transaction_date'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Field '$field' is required"]);
            exit;
        }
    }

    // Check write access
    if (!userHasWriteAccess(getCurrentUserId(), $companyId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Write access denied']);
        exit;
    }

    try {
        $data = [
            'company_id' => $companyId,
            'type' => $input['type'],
            'amount' => $input['amount'],
            'transaction_date' => $input['transaction_date'],
            'category' => $input['category'] ?? null,
            'description' => $input['description'] ?? null,
            'reference_number' => $input['reference_number'] ?? null,
            'payment_method' => $input['payment_method'] ?? 'cash',
            'transaction_account' => $input['transaction_account'] ?? 'cash',
            'created_by' => getCurrentUserId()
        ];

        $id = Transaction::create($data);
        
        echo json_encode([
            'success' => true,
            'message' => 'Transaction created',
            'transaction_id' => $id
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// PUT: Update Transaction
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $transactionId = $_GET['id'] ?? null;
    if (!$transactionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Transaction ID required']);
        exit;
    }

    // Verify ownership
    $existing = Transaction::getById($transactionId, $companyId);
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }

    if (!userHasWriteAccess(getCurrentUserId(), $companyId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Write access denied']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $required = ['type', 'amount', 'transaction_date'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Field '$field' is required"]);
            exit;
        }
    }

    try {
        Transaction::update($transactionId, $companyId, [
            'type'                => $input['type'],
            'amount'              => $input['amount'],
            'transaction_date'    => $input['transaction_date'],
            'category'            => $input['category'] ?? null,
            'description'         => $input['description'] ?? null,
            'reference_number'    => $input['reference_number'] ?? null,
            'payment_method'      => $input['payment_method'] ?? 'cash',
            'transaction_account' => $input['transaction_account'] ?? 'cash',
            'receipt_path'        => $existing['receipt_path'] ?? null,
        ]);
        echo json_encode(['success' => true, 'message' => 'Transaction updated']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// DELETE: Delete Transaction
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $transactionId = $_GET['id'] ?? null;
    if (!$transactionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Transaction ID required']);
        exit;
    }

    $existing = Transaction::getById($transactionId, $companyId);
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }

    if (!userHasWriteAccess(getCurrentUserId(), $companyId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Write access denied']);
        exit;
    }

    try {
        Transaction::delete($transactionId, $companyId);
        echo json_encode(['success' => true, 'message' => 'Transaction deleted']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
