<?php
/**
 * API Company Endpoint
 * Manage active company context
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/Company.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = getCurrentUserId();

// POST: Switch Company
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $companyId = $input['company_id'] ?? $_POST['company_id'] ?? null;

    if (!$companyId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Company ID required']);
        exit;
    }

    if (userHasAccessToCompany($userId, $companyId)) {
        setActiveCompany($companyId);
        echo json_encode(['success' => true, 'message' => "Switched to company ID $companyId"]);
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied to this company']);
    }
    exit;
}

// GET: List Companies
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $companies = Company::getByUser($userId);
    echo json_encode([
        'success' => true, 
        'active_company_id' => getCurrentCompanyId(),
        'companies' => $companies
    ]);
    exit;
}
