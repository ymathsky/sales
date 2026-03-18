<?php
/**
 * Export Transactions to CSV
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();

$companyId = (int)($_GET['company'] ?? getCurrentCompanyId());
requireCompanyAccess($companyId);

$company = Company::getById($companyId);

// Build filters (same as list.php)
$filters = [];
if (!empty($_GET['type'])) {
    $filters['type'] = $_GET['type'];
}
if (!empty($_GET['start_date'])) {
    $filters['start_date'] = $_GET['start_date'];
}
if (!empty($_GET['end_date'])) {
    $filters['end_date'] = $_GET['end_date'];
}
if (!empty($_GET['category'])) {
    $filters['category'] = $_GET['category'];
}
if (!empty($_GET['account'])) {
    $filters['account'] = $_GET['account'];
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Get transactions (no limit for export)
$transactions = Transaction::getByCompany($companyId, $filters, 10000);

// Set headers for CSV download
$filename = 'transactions_' . sanitizeInput($company['name']) . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, [
    'Date',
    'Type',
    'Category',
    'Description',
    'Reference Number',
    'Payment Method',
    'Amount (₱)',
    'Created By',
    'Created At'
]);

// Calculate totals
$totalIn = 0;
$totalOut = 0;

// Add data rows
foreach ($transactions as $trans) {
    if ($trans['type'] === 'in') {
        $totalIn += $trans['amount'];
    } else {
        $totalOut += $trans['amount'];
    }
    
    fputcsv($output, [
        date('M d, Y', strtotime($trans['transaction_date'])),
        $trans['type'] === 'in' ? 'Cash In' : 'Cash Out',
        $trans['category'] ?? '-',
        $trans['description'] ?? '-',
        $trans['reference_number'] ?? '-',
        ucfirst(str_replace('_', ' ', $trans['payment_method'])),
        number_format($trans['amount'], 2),
        $trans['created_by_name'] ?? 'Unknown',
        date('M d, Y h:i A', strtotime($trans['created_at']))
    ]);
}

// Add summary rows
fputcsv($output, []);
fputcsv($output, ['SUMMARY', '', '', '', '', '', '', '', '']);
fputcsv($output, ['Total Cash In', '', '', '', '', '', number_format($totalIn, 2), '', '']);
fputcsv($output, ['Total Cash Out', '', '', '', '', '', number_format($totalOut, 2), '', '']);
fputcsv($output, ['Net Balance', '', '', '', '', '', number_format($totalIn - $totalOut, 2), '', '']);

// Add export info
fputcsv($output, []);
fputcsv($output, ['Exported by', $_SESSION['user_name'] ?? 'Unknown']);
fputcsv($output, ['Exported on', date('M d, Y h:i A')]);
fputcsv($output, ['Company', $company['name']]);

if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
    $dateRange = '';
    if (!empty($filters['start_date'])) {
        $dateRange .= date('M d, Y', strtotime($filters['start_date']));
    }
    if (!empty($filters['end_date'])) {
        $dateRange .= ' to ' . date('M d, Y', strtotime($filters['end_date']));
    }
    fputcsv($output, ['Date Range', $dateRange]);
}

fclose($output);
exit;
