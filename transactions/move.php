<?php
/**
 * Move Transaction to Another Company
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();

$userId = getCurrentUserId();
$currentCompanyId = getCurrentCompanyId();
requireCompanyAccess($currentCompanyId);

// Get transaction ID
$transactionId = $_GET['id'] ?? $_POST['id'] ?? null;
if (!$transactionId) {
    setFlashMessage('Transaction ID is required.', 'error');
    header('Location: /sales/transactions/list.php?company=' . $currentCompanyId);
    exit;
}

// Get transaction and verify access
$transaction = Transaction::getById($transactionId, $currentCompanyId);
if (!$transaction) {
    setFlashMessage('Transaction not found or access denied.', 'error');
    header('Location: /sales/transactions/list.php?company=' . $currentCompanyId);
    exit;
}

// Get user's accessible companies (exclude current company)
$allCompanies = Company::getByUser($userId);
$targetCompanies = array_filter($allCompanies, function($company) use ($currentCompanyId) {
    return $company['company_id'] != $currentCompanyId;
});

if (empty($targetCompanies)) {
    setFlashMessage('You do not have access to any other companies.', 'error');
    header('Location: /sales/transactions/list.php?company=' . $currentCompanyId);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetCompanyId = $_POST['target_company_id'] ?? null;
    
    if (!$targetCompanyId) {
        setFlashMessage('Please select a target company.', 'error');
    } else {
        // Verify user has access to target company
        if (!userHasAccessToCompany($userId, $targetCompanyId)) {
            setFlashMessage('You do not have access to the selected company.', 'error');
        } else {
            // Move the transaction
            if (Transaction::moveToCompany($transactionId, $currentCompanyId, $targetCompanyId)) {
                $targetCompany = Company::getById($targetCompanyId);
                setFlashMessage('Transaction successfully moved to ' . htmlspecialchars($targetCompany['name']), 'success');
                header('Location: /sales/transactions/list.php?company=' . $currentCompanyId);
                exit;
            } else {
                setFlashMessage('Failed to move transaction. Please try again.', 'error');
            }
        }
    }
}

$pageTitle = 'Move Transaction';
include __DIR__ . '/../views/header.php';
?>

<div class="page-header">
    <h1><?= $pageTitle ?></h1>
    <a href="/sales/transactions/list.php?company=<?= $currentCompanyId ?>" class="btn btn-secondary">
        <svg style="width: 16px; height: 16px; vertical-align: middle; margin-right: 5px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Back to Transactions
    </a>
</div>

<div class="card">
    <div class="card-body">
        <h2 style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--border-color);">Transaction Details</h2>
        
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <strong style="color: #6c757d; font-size: 12px; text-transform: uppercase;">Date:</strong>
                    <div style="font-size: 16px; margin-top: 5px;"><?= formatDate($transaction['transaction_date'], 'M d, Y') ?></div>
                </div>
                <div>
                    <strong style="color: #6c757d; font-size: 12px; text-transform: uppercase;">Type:</strong>
                    <div style="margin-top: 5px;"><?= getTypeBadge($transaction['type']) ?></div>
                </div>
                <div>
                    <strong style="color: #6c757d; font-size: 12px; text-transform: uppercase;">Category:</strong>
                    <div style="font-size: 16px; margin-top: 5px;"><?= htmlspecialchars($transaction['category'] ?? '-') ?></div>
                </div>
                <div>
                    <strong style="color: #6c757d; font-size: 12px; text-transform: uppercase;">Amount:</strong>
                    <div style="font-size: 20px; font-weight: 700; color: <?= $transaction['type'] === 'in' ? '#10b981' : '#ef4444' ?>; margin-top: 5px;">
                        <?= formatMoney($transaction['amount']) ?>
                    </div>
                </div>
                <div style="grid-column: span 2;">
                    <strong style="color: #6c757d; font-size: 12px; text-transform: uppercase;">Description:</strong>
                    <div style="font-size: 16px; margin-top: 5px;"><?= htmlspecialchars($transaction['description'] ?? '-') ?></div>
                </div>
                <div>
                    <strong style="color: #6c757d; font-size: 12px; text-transform: uppercase;">Reference:</strong>
                    <div style="font-size: 16px; margin-top: 5px;"><?= htmlspecialchars($transaction['reference_number'] ?? '-') ?></div>
                </div>
                <div>
                    <strong style="color: #6c757d; font-size: 12px; text-transform: uppercase;">Payment Method:</strong>
                    <div style="font-size: 16px; margin-top: 5px;"><?= ucfirst(str_replace('_', ' ', $transaction['payment_method'])) ?></div>
                </div>
            </div>
        </div>

        <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 25px;">
            <div style="display: flex; align-items: flex-start; gap: 10px;">
                <svg style="width: 24px; height: 24px; color: #ff9800; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <strong style="color: #856404;">Warning: Moving this transaction</strong>
                    <p style="margin: 5px 0 0 0; color: #856404; font-size: 14px;">
                        This will transfer the transaction record to another company. This action will affect both companies' financial reports and cannot be undone easily. Please ensure this is intentional.
                    </p>
                </div>
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="id" value="<?= $transactionId ?>">
            
            <div class="form-group">
                <label for="target_company_id" style="font-weight: 600; margin-bottom: 8px; display: block;">
                    Select Target Company <span style="color: red;">*</span>
                </label>
                <select name="target_company_id" id="target_company_id" class="form-control" required 
                        style="padding: 12px; font-size: 16px; border: 2px solid var(--border-color); border-radius: 8px;">
                    <option value="">-- Select Company --</option>
                    <?php foreach ($targetCompanies as $company): ?>
                        <option value="<?= $company['company_id'] ?>">
                            <?= htmlspecialchars($company['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color); display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">
                    <svg style="width: 16px; height: 16px; vertical-align: middle; margin-right: 5px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                    </svg>
                    Move Transaction
                </button>
                <a href="/sales/transactions/list.php?company=<?= $currentCompanyId ?>" class="btn btn-secondary" style="padding: 12px 24px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
