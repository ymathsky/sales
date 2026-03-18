<?php
/**
 * Delete POS Sale
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/POSSale.php';

requireLogin();

$companyId = (int)($_GET['company'] ?? 0);
$saleId = (int)($_GET['id'] ?? 0);

if (!$companyId || !$saleId) {
    header('Location: sales.php?company=' . $companyId);
    exit;
}

requireCompanyAccess($companyId);
requirePermission('sale_delete');

try {
    POSSale::delete($saleId, $companyId);
    setFlashMessage('Sale deleted successfully. Inventory has been restored.', 'success');
} catch (Exception $e) {
    setFlashMessage('Error deleting sale: ' . $e->getMessage(), 'error');
}

header('Location: sales.php?company=' . $companyId);
exit;
