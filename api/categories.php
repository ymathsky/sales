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

requireCompanyAccess($companyId);

if ($type && !in_array($type, ['in', 'out'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid type']);
    exit;
}

$db = getDBConnection();

function tableExists(PDO $db, $tableName) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$tableName]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(PDO $db, $tableName, $columnName) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$tableName, $columnName]);
    return (int)$stmt->fetchColumn() > 0;
}

$categories = [];

try {
    if (tableExists($db, 'categories')) {
        $sql = "SELECT category_id, name, type FROM categories WHERE (company_id IS NULL OR company_id = ?)";
        $params = [$companyId];

        if (columnExists($db, 'categories', 'is_active')) {
            $sql .= " AND is_active = 1";
        }

        if ($type) {
            $sql .= " AND (type = ? OR type = 'both')";
            $params[] = $type;
        }

        $sql .= " ORDER BY name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (tableExists($db, 'transaction_categories')) {
        $sql = "SELECT category_id, name, type FROM transaction_categories WHERE company_id = ?";
        $params = [$companyId];

        if ($type) {
            $sql .= " AND (type = ? OR type = 'both')";
            $params[] = $type;
        }

        $sql .= " ORDER BY name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('categories.php error: ' . $e->getMessage());
    $categories = [];
}

if (empty($categories)) {
    $incomeDefaults = ['Sales', 'Service Fee', 'Rental', 'Investment', 'Refund', 'Other'];
    $expenseDefaults = ['Supplies', 'Utilities', 'Salary', 'Rent', 'Marketing', 'Transport', 'Maintenance', 'Other'];

    $defaultsByType = [];
    if ($type === 'in') {
        foreach ($incomeDefaults as $name) {
            $defaultsByType[] = ['category_id' => null, 'name' => $name, 'type' => 'in'];
        }
    } elseif ($type === 'out') {
        foreach ($expenseDefaults as $name) {
            $defaultsByType[] = ['category_id' => null, 'name' => $name, 'type' => 'out'];
        }
    } else {
        foreach ($incomeDefaults as $name) {
            $defaultsByType[] = ['category_id' => null, 'name' => $name, 'type' => 'in'];
        }
        foreach ($expenseDefaults as $name) {
            $defaultsByType[] = ['category_id' => null, 'name' => $name, 'type' => 'out'];
        }
    }

    $categories = $defaultsByType;
}

echo json_encode([
    'success' => true,
    'data'    => array_values($categories),
]);
