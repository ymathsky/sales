<?php
/**
 * API endpoint: GET categories filtered by type and company
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/Category.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$companyId = getCurrentCompanyId();
$type      = $_GET['type'] ?? null; // 'in', 'out', or omit for all

// Map mobile type values ('in'/'out') to DB type column ('in'/'out'/'both')
if ($type && !in_array($type, ['in', 'out'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid type']);
    exit;
}

$categories = Category::getAll(true, $type, $companyId);

echo json_encode([
    'success' => true,
    'data'    => array_values(array_map(fn($c) => [
        'category_id' => $c['category_id'],
        'name'        => $c['name'],
        'type'        => $c['type'],
    ], $categories)),
]);
