<?php
/**
 * API endpoint to get receipts for a transaction
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/Transaction.php';

header('Content-Type: application/json');

requireLogin();

$transactionId = $_GET['transaction_id'] ?? null;

if (!$transactionId) {
    echo json_encode(['success' => false, 'error' => 'Transaction ID required']);
    exit;
}

$companyId = getCurrentCompanyId();

// Verify transaction belongs to current company
$transaction = Transaction::getById($transactionId, $companyId);
if (!$transaction) {
    echo json_encode(['success' => false, 'error' => 'Transaction not found or access denied']);
    exit;
}

// Get all receipts for this transaction
$receipts = Transaction::getReceipts($transactionId);

echo json_encode([
    'success' => true,
    'receipts' => $receipts
]);
