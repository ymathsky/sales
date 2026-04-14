<?php
/**
 * API Invoice Detail Endpoint
 * GET  ?id=N          – fetch invoice header + items
 * POST {action, ...}  – update status or record payment
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/Invoice.php';

ob_start();
register_shutdown_function(function () {
    $buffer = ob_get_contents();
    if ($buffer === false) return;
    $trimmed = trim($buffer);
    if ($trimmed === '') { ob_end_flush(); return; }
    json_decode($trimmed, true);
    if (json_last_error() === JSON_ERROR_NONE) { ob_end_flush(); return; }
    if (!headers_sent()) {
        if (http_response_code() < 400) http_response_code(500);
        header('Content-Type: application/json');
    }
    ob_clean();
    echo json_encode(['success' => false, 'error' => $trimmed]);
    ob_end_flush();
});

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

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

if (!hasPermission('manage_invoices')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

// ── GET: fetch invoice + items ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $invoiceId = (int)($_GET['id'] ?? 0);
    if (!$invoiceId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invoice ID required']);
        exit;
    }

    $invoice = Invoice::getById($invoiceId, $companyId);
    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Invoice not found']);
        exit;
    }

    $items = Invoice::getItems($invoiceId);

    echo json_encode([
        'success' => true,
        'data' => [
            'invoice' => $invoice,
            'items'   => $items,
        ],
    ]);
    exit;
}

// ── POST: status actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!userHasWriteAccess(getCurrentUserId(), $companyId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Write access denied']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
        exit;
    }

    $invoiceId = (int)($input['invoice_id'] ?? 0);
    if (!$invoiceId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'invoice_id required']);
        exit;
    }

    $invoice = Invoice::getById($invoiceId, $companyId);
    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Invoice not found']);
        exit;
    }

    $action = $input['action'] ?? '';

    if ($action === 'update_status') {
        $allowedStatuses = ['draft', 'sent', 'paid', 'cancelled'];
        $newStatus = $input['status'] ?? '';
        if (!in_array($newStatus, $allowedStatuses, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            exit;
        }
        Invoice::updateStatus($invoiceId, $companyId, $newStatus);
        $updated = Invoice::getById($invoiceId, $companyId);
        echo json_encode(['success' => true, 'data' => $updated]);
        exit;
    }

    if ($action === 'record_payment') {
        $amount = floatval($input['amount'] ?? 0);
        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Payment amount must be greater than zero']);
            exit;
        }
        if ($amount > floatval($invoice['amount_due']) + 0.01) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Payment exceeds balance due']);
            exit;
        }
        Invoice::recordPayment($invoiceId, $companyId, $amount);
        $updated = Invoice::getById($invoiceId, $companyId);
        $items   = Invoice::getItems($invoiceId);
        echo json_encode([
            'success' => true,
            'data' => ['invoice' => $updated, 'items' => $items],
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
