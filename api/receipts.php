<?php
/**
 * API Receipts Endpoint
 * GET: list receipts by transaction
 * POST: upload one or more receipt images
 * DELETE: delete a specific receipt
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/Transaction.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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

function buildReceiptUrl($relativePath) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $root = rtrim(defined('WEB_ROOT') ? WEB_ROOT : '', '/');
    $path = '/' . ltrim($relativePath, '/');

    if (!$host) {
        return $path;
    }

    return $scheme . '://' . $host . $root . $path;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $transactionId = (int)($_GET['transaction_id'] ?? 0);
    if (!$transactionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Transaction ID required']);
        exit;
    }

    $transaction = Transaction::getById($transactionId, $companyId);
    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }

    $receipts = Transaction::getReceipts($transactionId);
    $receipts = array_map(function ($row) {
        $row['url'] = buildReceiptUrl($row['file_path']);
        return $row;
    }, $receipts);

    echo json_encode([
        'success' => true,
        'data' => $receipts,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!userHasWriteAccess(getCurrentUserId(), $companyId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Write access denied']);
        exit;
    }

    $transactionId = (int)($_POST['transaction_id'] ?? 0);
    if (!$transactionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Transaction ID required']);
        exit;
    }

    $transaction = Transaction::getById($transactionId, $companyId);
    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }

    if (!isset($_FILES['receipts'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No receipt files provided']);
        exit;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    $maxFiles = 20;

    $files = $_FILES['receipts'];
    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];

    if (count($names) > $maxFiles) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Maximum of 20 receipt photos per upload']);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/receipts/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create uploads directory']);
        exit;
    }

    if (!is_writable($uploadDir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Uploads directory is not writable']);
        exit;
    }

    $uploaded = [];
    $failed = [];

    foreach ($names as $i => $originalName) {
        if ($errors[$i] !== UPLOAD_ERR_OK) {
            $failed[] = ['name' => $originalName, 'error' => 'Upload error code: ' . $errors[$i]];
            continue;
        }

        if ($sizes[$i] > $maxSize) {
            $failed[] = ['name' => $originalName, 'error' => 'File exceeds 5MB limit'];
            continue;
        }

        $mimeType = mime_content_type($tmpNames[$i]) ?: '';
        if (!in_array($mimeType, $allowedTypes, true)) {
            $failed[] = ['name' => $originalName, 'error' => 'Unsupported file type'];
            continue;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!$extension) {
            $extension = $mimeType === 'image/png' ? 'png' : 'jpg';
        }

        $fileName = 'receipt_' . $companyId . '_' . time() . '_' . uniqid() . '.' . $extension;
        $relativePath = 'uploads/receipts/' . $fileName;
        $targetPath = $uploadDir . $fileName;

        if (!move_uploaded_file($tmpNames[$i], $targetPath)) {
            $failed[] = ['name' => $originalName, 'error' => 'Failed to move uploaded file'];
            continue;
        }

        $receiptId = Transaction::addReceipt($transactionId, $relativePath, $originalName);
        $uploaded[] = [
            'id' => $receiptId,
            'name' => $originalName,
            'file_path' => $relativePath,
            'url' => buildReceiptUrl($relativePath),
        ];
    }

    if (empty($uploaded)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No files were uploaded successfully',
            'failed' => $failed,
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'uploaded_count' => count($uploaded),
        'failed_count' => count($failed),
        'data' => $uploaded,
        'failed' => $failed,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!userHasWriteAccess(getCurrentUserId(), $companyId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Write access denied']);
        exit;
    }

    $transactionId = (int)($_GET['transaction_id'] ?? 0);
    $receiptId = (int)($_GET['id'] ?? 0);

    if (!$transactionId || !$receiptId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'transaction_id and id are required']);
        exit;
    }

    $transaction = Transaction::getById($transactionId, $companyId);
    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }

    $ok = Transaction::deleteReceipt($receiptId, $transactionId);
    if (!$ok) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Receipt not found']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Receipt deleted']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
