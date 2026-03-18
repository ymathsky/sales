<?php
/**
 * Delete Client
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Customer.php';

requireLogin();

$customerId = (int)($_GET['id'] ?? 0);
$companyId = (int)($_GET['company'] ?? getCurrentCompanyId());

requireCompanyAccess($companyId);

if (!$customerId) {
    die('Invalid client ID');
}

// Get customer to verify it belongs to this company
$customer = Customer::getById($customerId, $companyId);

if (!$customer) {
    die('Client not found or access denied');
}

// Check if customer has invoices
$stmt = executeQuery("SELECT COUNT(*) as count FROM invoices WHERE customer_id = ?", [$customerId]);
$result = $stmt->fetch();
$hasInvoices = $result['count'] > 0;

if ($hasInvoices) {
    setFlashMessage('Cannot delete client with existing invoices. Please delete or reassign invoices first.', 'error');
    redirect("<?= WEB_ROOT ?>/customers/list.php?company={$companyId}");
}

// Delete customer
if (Customer::delete($customerId, $companyId)) {
    setFlashMessage('Client deleted successfully', 'success');
} else {
    setFlashMessage('Failed to delete client', 'error');
}

redirect("<?= WEB_ROOT ?>/customers/list.php?company={$companyId}");
