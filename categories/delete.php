<?php
/**
 * Delete Category
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Category.php';

requireLogin();
requireAdmin();

$categoryId = (int)($_GET['id'] ?? 0);
$category = Category::getById($categoryId);

if (!$category) {
    setFlashMessage('Category not found', 'error');
    header('Location: <?= WEB_ROOT ?>/categories/list.php');
    exit;
}

// Check if category is in use
$usageCount = Category::getUsageCount($categoryId);
if ($usageCount > 0) {
    setFlashMessage("Cannot delete category '{$category['name']}'. It is being used in {$usageCount} transaction(s).", 'error');
    header('Location: <?= WEB_ROOT ?>/categories/list.php');
    exit;
}

// Delete category
try {
    Category::delete($categoryId);
    setFlashMessage("Category '{$category['name']}' deleted successfully", 'success');
} catch (Exception $e) {
    setFlashMessage('Error deleting category: ' . $e->getMessage(), 'error');
}

header('Location: <?= WEB_ROOT ?>/categories/list.php');
exit;
