<?php
/**
 * Delete Company
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Company.php';

requireAdmin();

$companyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$companyId) {
    $_SESSION['flash_message'] = 'Invalid company ID';
    header('Location: ' . WEB_ROOT . '/companies/list.php');
    exit;
}

// Check if company has transactions or users
try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transactions WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $transactionCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_companies WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $userCount = $stmt->fetch()['count'];
    
    if ($transactionCount > 0 || $userCount > 0) {
        $_SESSION['flash_message'] = 'Cannot delete company with existing transactions or assigned users';
        header('Location: ' . WEB_ROOT . '/companies/edit.php?id=' . $companyId);
        exit;
    }
    
    // Delete company
    $stmt = $pdo->prepare("DELETE FROM companies WHERE company_id = ?");
    $stmt->execute([$companyId]);
    
    $_SESSION['flash_message'] = 'Company deleted successfully';
    header('Location: ' . WEB_ROOT . '/companies/list.php');
    exit;
    
} catch (PDOException $e) {
    $_SESSION['flash_message'] = 'Error deleting company: ' . $e->getMessage();
    header('Location: ' . WEB_ROOT . '/companies/list.php');
    exit;
}
