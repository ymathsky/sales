<?php
/**
 * API endpoint: GET categories filtered by type and company
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

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
$type      = $_GET['type'] ?? null;

if ($type && !in_array($type, ['in', 'out'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid type']);
    exit;
}

$db = getDBConnection();

$sql    = "SELECT category_id, name, type FROM transaction_categories WHERE company_id = ?";
$params = [$companyId];

if ($type) {
    $sql    .= " AND (type = ? OR type = 'both')";
    $params[] = $type;
}

$sql .= " ORDER BY name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'data'    => array_values($categories),
]);
