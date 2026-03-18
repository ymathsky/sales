<?php
/**
 * Remove User Access from Company
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$companyId || !$userId) {
    $_SESSION['flash_message'] = 'Invalid parameters';
    header('Location: /sales/companies/list.php');
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("DELETE FROM user_companies WHERE company_id = ? AND user_id = ?");
    $stmt->execute([$companyId, $userId]);
    
    $_SESSION['flash_message'] = 'User access removed successfully';
} catch (PDOException $e) {
    $_SESSION['flash_message'] = 'Error removing user access: ' . $e->getMessage();
}

header('Location: /sales/companies/edit.php?id=' . $companyId);
exit;
