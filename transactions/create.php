<?php
/**
 * Create Transaction
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/Category.php';

requireLogin();

$pageTitle = 'Add Transaction';

// Get user's accessible companies
$userCompanies = Company::getByUser(getCurrentUserId());

// Initialize variables
$errors = [];
$companyId = null;
$company = null;
$showForm = false;
$categories = [];

// Step 1: Company Selection
if (!empty($_GET['company'])) {
    $companyId = (int)$_GET['company'];
    requireCompanyAccess($companyId);
    $company = Company::getById($companyId);
    $showForm = true;
} elseif (!empty($_POST['company_id'])) {
    $companyId = (int)$_POST['company_id'];
    requireCompanyAccess($companyId);
    $company = Company::getById($companyId);
    $showForm = true;
}

// Load categories for dropdown
if ($showForm) {
    // Load all active categories (both in and out types)
    $allCategories = Category::getAll(true, null, $companyId);
    $categoriesJson = json_encode($allCategories);
}

// Pre-fill from URL parameters (e.g., from fund-transfers page)
$formData = [
    'type' => $_POST['type'] ?? $_GET['type'] ?? 'in',
    'transaction_date' => $_POST['transaction_date'] ?? date('Y-m-d'),
    'payment_method' => $_POST['payment_method'] ?? 'cash',
    'transaction_account' => $_POST['transaction_account'] ?? $_GET['account'] ?? 'cash',
    'amount' => $_POST['amount'] ?? '',
    'category' => $_POST['category'] ?? '',
    'description' => $_POST['description'] ?? (isset($_GET['from']) && $_GET['from'] === 'fund-transfers' ? 'Client deposit to bank' : ''),
    'reference_number' => $_POST['reference_number'] ?? ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm) {
    $receiptPath = null;
    
    // Handle receipt upload
    if (!empty($_FILES['receipt']['tmp_name'])) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        $fileType = $_FILES['receipt']['type'];
        $fileSize = $_FILES['receipt']['size'];
        $fileError = $_FILES['receipt']['error'];
        
        if ($fileError === UPLOAD_ERR_OK) {
            if (in_array($fileType, $allowedTypes)) {
                if ($fileSize <= $maxSize) {
                    $extension = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
                    $fileName = 'receipt_' . $companyId . '_' . time() . '_' . uniqid() . '.' . $extension;
                    $uploadDir = __DIR__ . '/../uploads/receipts/';
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true)) {
                            $errors[] = 'Failed to create uploads directory. Please contact administrator.';
                        }
                    }
                    
                    // Check if directory is writable
                    if (!is_writable($uploadDir)) {
                        $errors[] = 'Uploads directory is not writable. Please check permissions.';
                    }
                    
                    $uploadPath = $uploadDir . $fileName;
                    
                    if (empty($errors) && move_uploaded_file($_FILES['receipt']['tmp_name'], $uploadPath)) {
                        $receiptPath = 'uploads/receipts/' . $fileName;
                    } elseif (empty($errors)) {
                        $errors[] = 'Failed to upload receipt file. Please check directory permissions.';
                    }
                } else {
                    $errors[] = 'Receipt file is too large (max 5MB)';
                }
            } else {
                $errors[] = 'Invalid file type. Only JPG, PNG, GIF, and PDF are allowed';
            }
        } elseif ($fileError !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Error uploading receipt: ' . $fileError;
        }
    }
    
    $formData = [
        'company_id' => $companyId,
        'type' => $_POST['type'] ?? 'in',
        'amount' => $_POST['amount'] ?? '',
        'transaction_date' => $_POST['transaction_date'] ?? '',
        'category' => sanitizeInput($_POST['category'] ?? ''),
        'description' => sanitizeInput($_POST['description'] ?? ''),
        'reference_number' => sanitizeInput($_POST['reference_number'] ?? ''),
        'payment_method' => $_POST['payment_method'] ?? 'cash',
        'receipt_path' => $receiptPath,
        'created_by' => getCurrentUserId()
    ];
    
    // Validation
    if (!in_array($formData['type'], ['in', 'out'])) {
        $errors[] = 'Invalid transaction type';
    }
    
    if (!isValidAmount($formData['amount'])) {
        $errors[] = 'Invalid amount';
    }
    
    if (!isValidDate($formData['transaction_date'])) {
        $errors[] = 'Invalid date';
    }
    
    // Create transaction if no errors
    if (empty($errors)) {
        try {
            $transactionId = Transaction::create($formData);
            setFlashMessage('✓ Transaction created successfully! Amount: ₱' . number_format($formData['amount'], 2), 'success');
            redirect(WEB_ROOT . "/transactions/list.php?company={$companyId}");
        } catch (Exception $e) {
            $errors[] = 'Failed to create transaction: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../views/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>
            <svg style="width: 32px; height: 32px; margin-right: 10px; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Transaction
        </h1>
        <div>
            <?php if ($showForm): ?>
                <?php if (isset($_GET['from']) && $_GET['from'] === 'fund-transfers'): ?>
                    <a href="<?= WEB_ROOT ?>/reconciliation/fund-transfers.php?company=<?= $companyId ?>" class="btn btn-secondary">← Back to Fund Transfers</a>
                <?php else: ?>
                    <a href="<?= WEB_ROOT ?>/transactions/create.php" class="btn btn-secondary">Change Company</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?= WEB_ROOT ?>/index.php" class="btn btn-secondary">Back to Dashboard</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>Please fix the following errors:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$showForm): ?>
        <!-- Step 1: Select Company -->
        <div class="form-container">
            <h2 style="margin-bottom: 20px; color: var(--text-dark);">
                <svg style="width: 28px; height: 28px; margin-right: 10px; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                Select Company
            </h2>
            <p style="color: var(--text-light); margin-bottom: 30px;">Choose which company this transaction belongs to:</p>
            
            <?php if (empty($userCompanies)): ?>
                <div class="empty-state">
                    <svg style="width: 64px; height: 64px; margin: 0 auto 20px; color: var(--text-light);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <p>You don't have access to any companies yet.</p>
                    <?php if (getCurrentUserRole() === 'admin'): ?>
                        <a href="<?= WEB_ROOT ?>/companies/create.php" class="btn btn-primary">Create Company</a>
                    <?php else: ?>
                        <p style="font-size: 0.9rem; color: var(--text-light);">Please contact your administrator to grant you access.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                    <?php foreach ($userCompanies as $comp): ?>
                        <a href="<?= WEB_ROOT ?>/transactions/create.php?company=<?= $comp['company_id'] ?>" 
                           class="company-card" style="text-decoration: none; display: block;">
                            <div style="background: white; border: 2px solid var(--border-color); border-radius: 12px; padding: 25px; transition: all 0.3s; cursor: pointer; height: 100%;"
                                 onmouseover="this.style.borderColor='var(--primary-color)'; this.style.boxShadow='0 4px 12px rgba(37, 99, 235, 0.2)'; this.style.transform='translateY(-4px)';"
                                 onmouseout="this.style.borderColor='var(--border-color)'; this.style.boxShadow='none'; this.style.transform='translateY(0)';">
                                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, var(--primary-color), #1d4ed8); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                        <span style="color: white; font-size: 1.5rem; font-weight: bold;">
                                            <?= strtoupper(substr($comp['name'], 0, 1)) ?>
                                        </span>
                                    </div>
                                    <div style="flex: 1;">
                                        <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-dark);">
                                            <?= htmlspecialchars($comp['name']) ?>
                                        </h3>
                                        <span class="badge badge-success" style="margin-top: 5px; font-size: 0.7rem;">
                                            <?= htmlspecialchars(ucfirst($comp['access_level'])) ?> Access
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($comp['address'] || $comp['phone']): ?>
                                    <div style="border-top: 1px solid var(--border-color); padding-top: 12px; margin-top: 12px;">
                                        <?php if ($comp['address']): ?>
                                            <p style="margin: 5px 0; font-size: 0.85rem; color: var(--text-light); display: flex; align-items: start;">
                                                <svg style="width: 14px; height: 14px; margin-right: 6px; margin-top: 2px; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                                <?= htmlspecialchars(substr($comp['address'], 0, 50)) ?><?= strlen($comp['address']) > 50 ? '...' : '' ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($comp['phone']): ?>
                                            <p style="margin: 5px 0; font-size: 0.85rem; color: var(--text-light); display: flex; align-items: center;">
                                                <svg style="width: 14px; height: 14px; margin-right: 6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                </svg>
                                                <?= htmlspecialchars($comp['phone']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 15px; text-align: center;">
                                    <span style="color: var(--primary-color); font-weight: 600; font-size: 0.9rem;">
                                        Select & Continue →
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
    <?php else: ?>
        <!-- Step 2: Transaction Form -->
        <!-- Company Badge -->
        <div style="background: linear-gradient(135deg, var(--primary-color), #1d4ed8); color: white; padding: 20px 25px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <div style="display: flex; align-items: center;">
                    <div style="width: 42px; height: 42px; background: rgba(255,255,255,0.2); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                        <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 2px;">Recording transaction for:</div>
                        <div style="font-size: 1.2rem; font-weight: 600;"><?= htmlspecialchars($company['name']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Type Selection -->
        <div style="margin-bottom: 30px;">
            <div style="display: flex; gap: 15px; max-width: 600px;">
                <button type="button" onclick="selectTransactionType('in')" id="btn-type-in" 
                        class="btn btn-success" style="flex: 1; padding: 20px; font-size: 1.1rem; transition: all 0.3s;">
                    <svg style="width: 24px; height: 24px; margin-right: 8px; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Income
                </button>
                <button type="button" onclick="selectTransactionType('out')" id="btn-type-out" 
                        class="btn btn-secondary" style="flex: 1; padding: 20px; font-size: 1.1rem; transition: all 0.3s;">
                    <svg style="width: 24px; height: 24px; margin-right: 8px; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                    </svg>
                    Expense
                </button>
            </div>
        </div>

        <div class="form-container">

        <form method="POST" action="" id="transactionForm" enctype="multipart/form-data">
            <!-- Hidden fields -->
            <input type="hidden" name="company_id" value="<?= $companyId ?>">
            <input type="hidden" id="type" name="type" value="<?= $formData['type'] ?>">

            <!-- Amount (Large & Prominent) -->
            <div class="form-group" style="margin-bottom: 30px;">
                <label for="amount" style="font-size: 1.2rem; font-weight: 600; color: var(--text-dark);">
                    Amount *
                </label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); font-size: 1.8rem; color: var(--text-light); font-weight: bold;">₱</span>
                    <input type="number" id="amount" name="amount" step="0.01" min="0.01" required
                           style="font-size: 2rem; padding: 20px 20px 20px 45px; font-weight: 600; width: 100%; max-width: 400px;"
                           value="<?= htmlspecialchars($formData['amount'] ?? '') ?>"
                           placeholder="0.00" autofocus>
                </div>
            </div>

            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="form-group">
                    <label for="transaction_date">Date *</label>
                    <input type="date" id="transaction_date" name="transaction_date" required
                           value="<?= htmlspecialchars($formData['transaction_date']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method">
                        <option value="cash" <?= $formData['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="bank_transfer" <?= $formData['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="check" <?= $formData['payment_method'] === 'check' ? 'selected' : '' ?>>Check</option>
                        <option value="credit_card" <?= $formData['payment_method'] === 'credit_card' ? 'selected' : '' ?>>Credit Card</option>
                        <option value="other" <?= $formData['payment_method'] === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="transaction_account">Account Type *</label>
                    <select id="transaction_account" name="transaction_account" required>
                        <option value="cash" <?= ($formData['transaction_account'] ?? 'cash') === 'cash' ? 'selected' : '' ?>>💵 Cash on Hand</option>
                        <option value="bank" <?= ($formData['transaction_account'] ?? 'cash') === 'bank' ? 'selected' : '' ?>>🏦 Bank Account</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name']) ?>" 
                                    data-type="<?= htmlspecialchars($cat['type']) ?>"
                                    <?= ($formData['category'] ?? '') === $cat['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="reference_number">Reference Number</label>
                    <input type="text" id="reference_number" name="reference_number"
                           placeholder="Invoice #, Receipt #, etc."
                           value="<?= htmlspecialchars($formData['reference_number'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3" 
                          placeholder="Add notes or details about this transaction..."><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
            </div>
            
            <!-- Receipt Upload -->
            <div class="form-group">
                <label style="font-weight: 600; margin-bottom: 10px; display: block;">Receipt / Attachment</label>
                <div style="border: 2px dashed var(--border-color); border-radius: 8px; padding: 15px; background: var(--light-bg); transition: all 0.3s;" 
                     id="receiptDropZone"
                     ondragover="event.preventDefault(); this.style.borderColor='var(--primary-color)'; this.style.background='rgba(37, 99, 235, 0.05)';"
                     ondragleave="this.style.borderColor='var(--border-color)'; this.style.background='var(--light-bg)';"
                     ondrop="handleDrop(event)">
                    
                    <div style="text-align: center;">
                        <svg style="width: 40px; height: 40px; margin: 0 auto 12px; color: var(--text-light);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; position: relative; z-index: 1;">
                            <!-- Camera Capture -->
                            <button type="button" id="takeCameraBtn" class="btn btn-primary" style="cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 12px 8px; font-size: 0.9rem; width: 100%; pointer-events: auto; position: relative;">
                                <svg style="width: 18px; height: 18px; margin-right: 6px; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span>Camera</span>
                            </button>
                            
                            <!-- File Upload -->
                            <label for="receiptFile" class="btn btn-secondary" style="cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 12px 8px; font-size: 0.9rem; width: 100%; margin: 0;">
                                <svg style="width: 18px; height: 18px; margin-right: 6px; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                                <span>Browse</span>
                            </label>
                            <input type="file" id="receiptFile" name="receipt" accept="image/*,.pdf" 
                                   style="display: none;" onchange="handleFileSelect(event)">
                        </div>
                        
                        <p style="color: var(--text-light); font-size: 0.8rem; margin: 0; line-height: 1.4;">
                            Drag & drop or click to upload<br>
                            <small style="font-size: 0.75rem;">JPG, PNG, GIF, PDF (max 5MB)</small>
                        </p>
                    </div>
                    
                    <!-- Camera Modal -->
                    <div id="cameraModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.95); z-index: 10000; padding: 10px;">
                        <div style="max-width: 100%; height: 100%; display: flex; flex-direction: column;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding: 0 5px;">
                                <h3 style="color: white; margin: 0; font-size: 1.1rem;">Capture Receipt</h3>
                                <button type="button" id="closeCameraBtn" class="btn btn-danger btn-sm" style="padding: 8px 12px;">
                                    <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                            
                            <div style="flex: 1; background: black; border-radius: 8px; overflow: hidden; position: relative; display: flex; align-items: center; justify-content: center; min-height: 0;">
                                <video id="cameraVideo" autoplay playsinline style="width: 100%; height: 100%; object-fit: contain; display: block;"></video>
                                <canvas id="cameraCanvas" style="display: none;"></canvas>
                            </div>
                            
                            <div style="text-align: center; margin-top: 15px; padding: 0 10px;">
                                <button type="button" id="capturePhotoBtn" class="btn btn-primary" style="padding: 14px 30px; font-size: 1rem; width: 100%; max-width: 300px;">
                                    <svg style="width: 22px; height: 22px; margin-right: 8px; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    Capture Photo
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preview Area -->
                    <div id="receiptPreview" style="margin-top: 15px; display: none;">
                        <div style="background: white; padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 15px;">
                            <img id="previewImage" style="max-width: 120px; max-height: 120px; border-radius: 6px; object-fit: cover; display: none;">
                            <div style="flex: 1;">
                                <p id="fileName" style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;"></p>
                                <p id="fileSize" style="font-size: 0.85rem; color: var(--text-light); margin: 0;"></p>
                            </div>
                            <button type="button" id="clearReceiptBtn" class="btn btn-sm btn-danger">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 1.1rem;">
                    <svg style="width: 20px; height: 20px; margin-right: 8px; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Save Transaction
                </button>
                <a href="<?= WEB_ROOT ?>/transactions/create.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
// Transaction type selection
function selectTransactionType(type) {
    document.getElementById('type').value = type;
    
    // Update button styles
    const btnIn = document.getElementById('btn-type-in');
    const btnOut = document.getElementById('btn-type-out');
    
    if (type === 'in') {
        btnIn.className = 'btn btn-success';
        btnIn.style.transform = 'scale(1.05)';
        btnOut.className = 'btn btn-secondary';
        btnOut.style.transform = 'scale(1)';
    } else {
        btnOut.className = 'btn btn-danger';
        btnOut.style.transform = 'scale(1.05)';
        btnIn.className = 'btn btn-secondary';
        btnIn.style.transform = 'scale(1)';
    }
    
    // Filter categories
    filterCategories(type);
    
    // Focus on amount field
    document.getElementById('amount').focus();
}

function filterCategories(type) {
    const categorySelect = document.getElementById('category');
    if (!categorySelect) return;
    
    const options = categorySelect.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') {
            option.style.display = '';
            return;
        }
        
        const optionType = option.getAttribute('data-type');
        
        if (optionType === type || optionType === 'both') {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    });
    
    const currentOption = categorySelect.options[categorySelect.selectedIndex];
    if (currentOption && currentOption.style.display === 'none') {
        categorySelect.value = '';
    }
}

// ============================================
// CAMERA FUNCTIONALITY
// ============================================
let cameraStream = null;
let capturedImageFile = null;

function openCamera() {
    console.log('Opening camera...');
    const modal = document.getElementById('cameraModal');
    const video = document.getElementById('cameraVideo');
    const captureBtn = document.getElementById('capturePhotoBtn');
    
    if (!modal || !video) {
        console.error('Camera modal or video element not found!');
        alert('Camera interface not found. Please refresh the page.');
        return;
    }
    
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('Camera access is not supported in this browser.\nPlease use Chrome, Firefox, or Edge.');
        console.error('getUserMedia not supported');
        return;
    }
    
    // Disable capture button initially
    if (captureBtn) {
        captureBtn.disabled = true;
        captureBtn.style.opacity = '0.5';
        captureBtn.innerHTML = '<svg style="width: 22px; height: 22px; margin-right: 8px; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>Waiting for camera...';
    }
    
    // Move modal to body for MDI compatibility
    if (modal.parentElement !== document.body) {
        console.log('Moving modal to body...');
        document.body.appendChild(modal);
    }
    
    // Force proper positioning
    modal.style.cssText = 'display: block; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 99999; background: rgba(0,0,0,0.95); padding: 10px;';
    document.body.style.overflow = 'hidden';
    
    console.log('Requesting camera access...');
    navigator.mediaDevices.getUserMedia({ 
        video: { 
            facingMode: 'environment',
            width: { ideal: 1920 },
            height: { ideal: 1080 }
        } 
    })
    .then(function(stream) {
        console.log('Camera access granted!');
        cameraStream = stream;
        video.srcObject = stream;
        
        // Wait for video to actually start playing
        video.onloadedmetadata = function() {
            console.log('Video metadata loaded');
            video.play().then(function() {
                console.log('Video playing, dimensions:', video.videoWidth, 'x', video.videoHeight);
                
                // Wait for video to be fully ready with multiple checks
                let checkCount = 0;
                const checkInterval = setInterval(function() {
                    checkCount++;
                    console.log('Checking video readiness, attempt:', checkCount, 'dimensions:', video.videoWidth, 'x', video.videoHeight);
                    
                    if (video.videoWidth > 0 && video.videoHeight > 0 && video.readyState >= 3) {
                        clearInterval(checkInterval);
                        if (captureBtn) {
                            captureBtn.disabled = false;
                            captureBtn.style.opacity = '1';
                            captureBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
                            captureBtn.innerHTML = '<svg style="width: 22px; height: 22px; margin-right: 8px; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>✓ Ready - Capture Photo';
                            console.log('Camera ready! Dimensions:', video.videoWidth, 'x', video.videoHeight);
                        }
                    } else if (checkCount >= 20) {
                        clearInterval(checkInterval);
                        console.error('Camera failed to become ready after 20 attempts');
                        if (captureBtn) {
                            captureBtn.disabled = false;
                            captureBtn.style.opacity = '0.8';
                            captureBtn.innerHTML = '<svg style="width: 22px; height: 22px; margin-right: 8px; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>Try Capture';
                        }
                    }
                }, 200);
            }).catch(function(err) {
                console.error('Video play error:', err);
                alert('Failed to start video: ' + err.message);
            });
        };
    })
    .catch(function(err) {
        console.error('Camera error:', err);
        modal.style.display = 'none';
        document.body.style.overflow = '';
        alert('Camera access denied or unavailable:\n' + err.message + '\n\nPlease ensure:\n• Camera permissions are granted\n• No other app is using the camera\n• You are using HTTPS or localhost');
    });
}

function closeCamera() {
    console.log('Closing camera...');
    
    // Stop camera stream first
    if (cameraStream) {
        console.log('Stopping camera stream...');
        cameraStream.getTracks().forEach(track => {
            track.stop();
            console.log('Track stopped:', track.kind);
        });
        cameraStream = null;
    }
    
    // Get video and clear it
    const video = document.getElementById('cameraVideo');
    if (video) {
        video.srcObject = null;
        video.pause();
        console.log('Video source cleared');
    }
    
    // Hide all camera modals (in case there are duplicates)
    const allModals = document.querySelectorAll('#cameraModal');
    console.log('Found ' + allModals.length + ' modal(s)');
    allModals.forEach((modal, index) => {
        modal.style.setProperty('display', 'none', 'important');
        modal.style.visibility = 'hidden';
        modal.style.opacity = '0';
        modal.style.pointerEvents = 'none';
        console.log('Modal ' + index + ' hidden');
    });
    
    // Restore body scroll
    document.body.style.overflow = '';
    document.body.style.removeProperty('overflow');
    
    console.log('Camera closed successfully');
}

function capturePhoto() {
    console.log('Capturing photo...');
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    
    if (!video || !canvas) {
        console.error('Video or canvas element not found');
        alert('Error: Camera elements not found');
        return;
    }
    
    // Log video state for debugging
    console.log('Video state - Width:', video.videoWidth, 'Height:', video.videoHeight, 'ReadyState:', video.readyState, 'Paused:', video.paused);
    
    // Double-check video dimensions with fallback
    if (video.videoWidth === 0 || video.videoHeight === 0) {
        console.warn('Video dimensions are zero, waiting and retrying...');
        // Wait a moment and try again
        setTimeout(function() {
            console.log('Retry - Width:', video.videoWidth, 'Height:', video.videoHeight);
            if (video.videoWidth > 0 && video.videoHeight > 0) {
                console.log('Retry successful, capturing now...');
                capturePhotoNow(video, canvas);
            } else {
                console.error('Video still has no dimensions after retry');
                alert('Camera not ready yet!\n\nPlease:\n1. Close the camera\n2. Reopen it\n3. Wait for button to show "✓ Ready - Capture Photo"\n4. Then click to capture');
            }
        }, 1000);
        return;
    }
    
    capturePhotoNow(video, canvas);
}

function capturePhotoNow(video, canvas) {
    
    console.log('Capturing at dimensions:', video.videoWidth, 'x', video.videoHeight);
    
    // Set canvas size to video dimensions
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    // Get context and draw video frame
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    console.log('Frame drawn to canvas');
    
    // Convert to blob and create file
    canvas.toBlob(function(blob) {
        if (!blob) {
            console.error('Failed to create image blob');
            alert('Failed to capture photo. Please try again.');
            return;
        }
        
        console.log('Blob created, size:', blob.size, 'bytes');
        
        const fileName = 'camera_receipt_' + Date.now() + '.jpg';
        const file = new File([blob], fileName, { type: 'image/jpeg' });
        
        // Set file to file input
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        document.getElementById('receiptFile').files = dataTransfer.files;
        
        // Display preview
        displayFilePreview(file);
        
        // Close camera
        closeCamera();
        
        console.log('Photo captured successfully!');
    }, 'image/jpeg', 0.95);
}

// ============================================
// FILE UPLOAD HANDLERS
// ============================================
function handleFileSelect(event) {
    const file = event.target.files[0];
    if (file) {
        displayFilePreview(file);
    }
}

function handleDrop(event) {
    event.preventDefault();
    const dropZone = document.getElementById('receiptDropZone');
    dropZone.style.borderColor = 'var(--border-color)';
    dropZone.style.background = 'var(--light-bg)';
    
    const file = event.dataTransfer.files[0];
    if (file) {
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        document.getElementById('receiptFile').files = dataTransfer.files;
        displayFilePreview(file);
    }
}

function displayFilePreview(file) {
    const preview = document.getElementById('receiptPreview');
    const previewImage = document.getElementById('previewImage');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    
    if (!preview || !fileName || !fileSize) {
        console.error('Preview elements not found');
        return;
    }
    
    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);
    
    // Show preview for images
    if (file.type.startsWith('image/') && previewImage) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            previewImage.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else if (previewImage) {
        previewImage.style.display = 'none';
    }
    
    preview.style.display = 'block';
}

function clearReceipt() {
    const fileInput = document.getElementById('receiptFile');
    const preview = document.getElementById('receiptPreview');
    const previewImage = document.getElementById('previewImage');
    
    if (fileInput) fileInput.value = '';
    if (preview) preview.style.display = 'none';
    if (previewImage) {
        previewImage.src = '';
        previewImage.style.display = 'none';
    }
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// ============================================
// INITIALIZATION
// ============================================
function initializePage() {
    console.log('Initializing Add Transaction page...');
    
    // Initialize transaction type
    const typeField = document.getElementById('type');
    if (typeField) {
        const typeValue = typeField.value;
        filterCategories(typeValue);
        selectTransactionType(typeValue);
    }
    
    // Setup camera button - using direct onclick to ensure it works in tabs
    const takeCameraBtn = document.getElementById('takeCameraBtn');
    if (takeCameraBtn) {
        console.log('Camera button found - attaching handlers');
        // Remove any existing handlers
        takeCameraBtn.onclick = null;
        takeCameraBtn.replaceWith(takeCameraBtn.cloneNode(true));
        
        // Get fresh reference and attach handler
        const freshBtn = document.getElementById('takeCameraBtn');
        freshBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Camera button clicked!');
            openCamera();
        };
    } else {
        console.warn('Camera button not found!');
    }
    
    // Setup close camera button
    const closeCameraBtn = document.getElementById('closeCameraBtn');
    if (closeCameraBtn) {
        console.log('Close camera button found - attaching handler');
        closeCameraBtn.onclick = function(e) {
            console.log('Close button clicked!');
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            closeCamera();
            return false;
        };
    }
    
    // Setup capture button
    const capturePhotoBtn = document.getElementById('capturePhotoBtn');
    if (capturePhotoBtn) {
        capturePhotoBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            capturePhoto();
        };
    }
    
    // Setup clear receipt button
    const clearReceiptBtn = document.getElementById('clearReceiptBtn');
    if (clearReceiptBtn) {
        clearReceiptBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            clearReceipt();
        };
    }
    
    console.log('Page initialization complete!');
}

// Run initialization on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePage);
} else {
    // DOM already loaded (for AJAX/tab loads)
    initializePage();
}

// Also run on window load as fallback
window.addEventListener('load', function() {
    // Only reinitialize if elements exist but handlers aren't attached
    const takeCameraBtn = document.getElementById('takeCameraBtn');
    if (takeCameraBtn && !takeCameraBtn.onclick) {
        console.log('Reinitializing on window load...');
        initializePage();
    }
});
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>
