<?php
/**
 * API Dashboard Endpoint
 * Returns key metrics for the mobile app home screen
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/Invoice.php';

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
$summary = Transaction::getFinancialSummary($companyId);
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$monthSummary = Transaction::getFinancialSummary($companyId, $monthStart, $monthEnd);

// 2. Get Recent Transactions (Last 5)
$recentTransactions = Transaction::getByCompany($companyId, [], 5, 0);

// 3. Get cash in/out trend (last 6 months)
$monthlyTrend = Transaction::getMonthlyTrend($companyId, 6);

// 4. Enhanced analytics
$dailySalesTrend = Transaction::getDailySalesTrend($companyId, 14);
$topCategories = Transaction::getTopCategories($companyId, $monthStart, $monthEnd, 5);
$monthlyComparison = Transaction::getMonthComparison($companyId);

$topCustomers = [];
$unpaidInvoices = [
    'unpaid_count' => 0,
    'unpaid_total' => 0,
    'overdue_count' => 0,
    'overdue_total' => 0,
];

try {
    $topCustomers = Invoice::getTopCustomersByRevenue($companyId, $monthStart, $monthEnd, 5);
    $unpaidInvoices = Invoice::getUnpaidSummary($companyId);
} catch (Throwable $e) {
    // Keep dashboard resilient even if invoice modules/tables are unavailable.
}

// 5. Get Company Info
$company = Company::getById($companyId);
$permissions = getCurrentPermissions();

echo json_encode([
    'success' => true,
    'user' => [
        'user_id' => getCurrentUserId(),
        'username' => $_SESSION['username'] ?? null,
        'full_name' => getCurrentUserName(),
        'role' => getCurrentUserRole(),
    ],
    'permissions' => $permissions,
    'company' => [
        'company_id' => $company['company_id'],
        'name' => $company['name'],
        'currency' => '₱'
    ],
    'period' => date('F Y'),
    'summary' => [
        'total_income' => (float)$summary['total_income'],
        'total_expense' => (float)$summary['total_expense'],
        'net_balance' => (float)$summary['net_balance'],
        'month_income' => (float)$monthSummary['total_income'],
        'month_expense' => (float)$monthSummary['total_expense']
    ],
    'monthly_trend' => $monthlyTrend,
    'daily_sales_trend' => $dailySalesTrend,
    'top_categories' => $topCategories,
    'top_customers' => $topCustomers,
    'monthly_comparison' => $monthlyComparison,
    'unpaid_invoices' => $unpaidInvoices,
    'recent_transactions' => $recentTransactions
]);
