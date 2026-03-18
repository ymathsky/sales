<?php
/**
 * API Dashboard Endpoint
 * Returns key metrics for the mobile app home screen
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Company.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Auth Check
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

if (!userHasAccessToCompany(getCurrentUserId(), $companyId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// 1. Get Financial Summary (All Time)
$summary = Transaction::getSummary($companyId);
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$monthSummary = Transaction::getSummary($companyId, $monthStart, $monthEnd);

// 2. Get Recent Transactions (Last 5)
$recentTransactions = Transaction::getByCompany($companyId, [], 5, 0);

// 3. Get Company Info
$company = Company::getById($companyId);

echo json_encode([
    'success' => true,
    'company' => [
        'id' => $company['company_id'],
        'name' => $company['name'],
        'currency' => '$' // Hardcoded for now, could be dynamic
    ],
    'period' => date('F Y'),
    'summary' => [
        'total_income' => (float)$summary['total_income'],
        'total_expense' => (float)$summary['total_expense'],
        'net_balance' => (float)$summary['net_balance'],
        'month_income' => (float)$monthSummary['total_income'],
        'month_expense' => (float)$monthSummary['total_expense']
    ],
    'recent_transactions' => $recentTransactions
]);
