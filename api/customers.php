<?php
/**
 * API Customers Endpoint
 * GET: list customers for active company
 * POST: create customer
 * PUT: update customer
 */

require_once __DIR__ . '/../includes/session.php';
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
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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
    $customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($customerId > 0) {
        $customer = Customer::getById($customerId, $companyId);

        if (!$customer) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Customer not found']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'data' => $customer,
        ]);
        exit;
    }

    $activeOnly = !isset($_GET['active']) || $_GET['active'] !== '0';
    $customers = Customer::getByCompany($companyId, $activeOnly);

    echo json_encode([
        'success' => true,
        'data' => $customers,
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

    $customerName = trim($input['customer_name'] ?? '');
    if ($customerName === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Customer name is required']);
        exit;
    }

    try {
        $customerId = Customer::create([
            'company_id' => $companyId,
            'customer_name' => $customerName,
            'contact_person' => trim($input['contact_person'] ?? '') ?: null,
            'email' => trim($input['email'] ?? '') ?: null,
            'phone' => trim($input['phone'] ?? '') ?: null,
            'address' => trim($input['address'] ?? '') ?: null,
            'tax_id' => trim($input['tax_id'] ?? '') ?: null,
            'payment_terms' => isset($input['payment_terms']) ? (int)$input['payment_terms'] : 30,
            'credit_limit' => isset($input['credit_limit']) ? (float)$input['credit_limit'] : 0,
            'is_active' => 1,
            'notes' => trim($input['notes'] ?? '') ?: null,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Customer created',
            'customer_id' => $customerId,
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!userHasWriteAccess(getCurrentUserId(), $companyId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Write access denied']);
        exit;
    }

    $customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($customerId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Customer ID is required']);
        exit;
    }

    $existingCustomer = Customer::getById($customerId, $companyId);
    if (!$existingCustomer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Customer not found']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $customerName = trim($input['customer_name'] ?? '');
    if ($customerName === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Customer name is required']);
        exit;
    }

    try {
        Customer::update($customerId, $companyId, [
            'customer_name' => $customerName,
            'contact_person' => trim($input['contact_person'] ?? '') ?: null,
            'email' => trim($input['email'] ?? '') ?: null,
            'phone' => trim($input['phone'] ?? '') ?: null,
            'address' => trim($input['address'] ?? '') ?: null,
            'tax_id' => trim($input['tax_id'] ?? '') ?: null,
            'payment_terms' => isset($input['payment_terms']) ? (int)$input['payment_terms'] : 30,
            'credit_limit' => isset($input['credit_limit']) ? (float)$input['credit_limit'] : 0,
            'is_active' => isset($input['is_active']) ? (int)$input['is_active'] : 1,
            'notes' => trim($input['notes'] ?? '') ?: null,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Customer updated',
            'customer_id' => $customerId,
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
