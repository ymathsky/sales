<?php
/**
 * API Invoices Endpoint
 * GET: list invoices for active company
 * POST: create invoice
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/Invoice.php';
require_once __DIR__ . '/../models/Customer.php';

ob_start();
register_shutdown_function(function () {
    $buffer = ob_get_contents();
    if ($buffer === false) {
        return;
    }

    $trimmed = trim($buffer);
    if ($trimmed === '') {
        ob_end_flush();
        return;
    }

    json_decode($trimmed, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        ob_end_flush();
        return;
    }

    if (!headers_sent()) {
        if (http_response_code() < 400) {
            http_response_code(500);
        }
        header('Content-Type: application/json');
    }

    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $trimmed,
    ]);
    ob_end_flush();
});

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

if (!userHasAccessToCompany(getCurrentUserId(), $companyId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filters = [
        'status' => $_GET['status'] ?? null,
        'customer_id' => $_GET['customer_id'] ?? null,
    ];

    $invoices = Invoice::getByCompany($companyId, $filters);

    echo json_encode([
        'success' => true,
        'data' => $invoices,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!userHasWriteAccess(getCurrentUserId(), $companyId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Write access denied']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $customerId = (int)($input['customer_id'] ?? 0);
    $invoiceDate = $input['invoice_date'] ?? null;
    $dueDate = $input['due_date'] ?? null;
    $items = $input['items'] ?? [];

    if (!$customerId || !$invoiceDate || !$dueDate) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'customer_id, invoice_date, and due_date are required']);
        exit;
    }

    $customer = Customer::getById($customerId, $companyId);
    if (!$customer) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid customer']);
        exit;
    }

    if (!is_array($items) || count($items) === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'At least one invoice item is required']);
        exit;
    }

    $normalizedItems = [];
    foreach ($items as $item) {
        $description = trim($item['description'] ?? '');
        $quantity = (float)($item['quantity'] ?? 0);
        $unitPrice = (float)($item['unit_price'] ?? 0);

        if ($description === '' || $quantity <= 0 || $unitPrice < 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Each item must have description, quantity > 0, and unit_price >= 0']);
            exit;
        }

        $normalizedItems[] = [
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ];
    }

    try {
        $invoiceId = Invoice::create([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'tax_amount' => isset($input['tax_amount']) ? (float)$input['tax_amount'] : 0,
            'notes' => trim($input['notes'] ?? '') ?: null,
            'terms' => trim($input['terms'] ?? '') ?: null,
        ], $normalizedItems);

        echo json_encode([
            'success' => true,
            'message' => 'Invoice created',
            'invoice_id' => $invoiceId,
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
