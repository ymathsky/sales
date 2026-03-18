<?php
/**
 * Opening Balance Settings
 * Set/update opening balance for the company
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();

$userId = getCurrentUserId();

// Handle company selection from URL
if (isset($_GET['company'])) {
    $selectedCompanyId = (int)$_GET['company'];
    if (userHasAccessToCompany($userId, $selectedCompanyId)) {
        setActiveCompany($selectedCompanyId);
    }
}

$companyId = getCurrentCompanyId();

if (!$companyId) {
    header('Location: ../index.php');
    exit;
}

// Get company details
$company = Company::getById($companyId);
if (!$company) {
    die('Company not found');
}

// Get current opening balance
$opening = Company::getOpeningBalance($companyId);

// Handle reset action
if (isset($_GET['reset'])) {
    $resetType = $_GET['reset'];
    if ($resetType === 'bank') {
        Company::updateOpeningBalance($companyId, $opening['cash_amount'], $opening['cash_date'], 0, null);
        header("Location: opening-balance.php?company=$companyId&reset_success=bank");
        exit;
    } elseif ($resetType === 'cash') {
        Company::updateOpeningBalance($companyId, 0, null, $opening['bank_amount'], $opening['bank_date']);
        header("Location: opening-balance.php?company=$companyId&reset_success=cash");
        exit;
    }
}

// Handle form submission
$message = '';
$messageType = '';

if (isset($_GET['reset_success'])) {
    $resetType = $_GET['reset_success'];
    $message = ucfirst($resetType) . ' opening balance has been reset to ₱0.00';
    $messageType = 'success';
    // Reload opening balance after reset
    $opening = Company::getOpeningBalance($companyId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cashAmount = floatval($_POST['cash_opening_balance'] ?? 0);
    $cashDate = $_POST['cash_opening_balance_date'] ?? date('Y-m-d');
    $bankAmount = floatval($_POST['bank_opening_balance'] ?? 0);
    $bankDate = $_POST['bank_opening_balance_date'] ?? null;
    
    Company::updateOpeningBalance($companyId, $cashAmount, $cashDate, $bankAmount, $bankDate);
    $opening = Company::getOpeningBalance($companyId);
    $message = 'Opening balances updated successfully';
    $messageType = 'success';
}

// Get current book balances
$cashBalance = Company::getBookBalance($companyId, null, 'cash');
$bankBalance = Company::getBookBalance($companyId, null, 'bank');
$combinedBalance = Company::getBookBalance($companyId);

// Set page title
$pageTitle = 'Opening Balance Settings';
require_once __DIR__ . '/../views/header.php';
?>

<style>
    .settings-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .settings-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }
        
        .settings-card h2 {
            margin: 0 0 8px 0;
            font-size: 24px;
            color: #1f2937;
        }
        
        .settings-card .subtitle {
            color: #6b7280;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-group .help-text {
            margin-top: 6px;
            font-size: 13px;
            color: #6b7280;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        .balance-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }
        
        .balance-item {
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        
        .balance-item .label {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        .balance-item .value {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
        }
        
        .balance-item.positive .value {
            color: #059669;
        }
        
        .balance-item.negative .value {
            color: #dc2626;
        }
        
        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 16px;
            margin-top: 24px;
        }
        
        .info-box h3 {
            margin: 0 0 8px 0;
            color: #1e40af;
            font-size: 16px;
        }
        
        .info-box ul {
            margin: 8px 0 0 20px;
            color: #1e3a8a;
            font-size: 14px;
            line-height: 1.6;
        }
    </style>

    <div class="content-wrapper">
        <div class="settings-container">
            <div class="settings-card">
                <h2>Opening Balance Settings</h2>
                <p class="subtitle">Set the starting balance for <?= htmlspecialchars($company['name']) ?></p>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <h3 style="margin: 0 0 20px 0; padding-bottom: 12px; border-bottom: 2px solid #f3f4f6; color: #374151;">
                        💵 Cash on Hand Opening Balance
                    </h3>
                    
                    <div class="form-group">
                        <label for="cash_opening_balance">Cash Opening Balance (₱)</label>
                        <input 
                            type="number" 
                            id="cash_opening_balance" 
                            name="cash_opening_balance" 
                            step="0.01" 
                            value="<?= number_format($opening['cash_amount'], 2, '.', '') ?>"
                        >
                        <div class="help-text">
                            Enter the physical cash amount when you started tracking (leave 0 if starting fresh)
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cash_opening_balance_date">Cash Opening Balance Date</label>
                        <input 
                            type="date" 
                            id="cash_opening_balance_date" 
                            name="cash_opening_balance_date" 
                            value="<?= $opening['cash_date'] ?: date('Y-m-d') ?>"
                            max="<?= date('Y-m-d') ?>"
                        >
                        <div class="help-text">
                            The date when this cash opening balance was valid (defaults to today)
                            <?php if ($opening['cash_amount'] > 0): ?>
                                <a href="?company=<?= $companyId ?>&reset=cash" 
                                   onclick="return confirm('Reset cash opening balance to ₱0.00?')" 
                                   style="color: #dc2626; margin-left: 10px;">
                                    Reset to ₱0.00
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h3 style="margin: 30px 0 20px 0; padding-bottom: 12px; border-bottom: 2px solid #f3f4f6; color: #374151;">
                        🏦 Bank Account Opening Balance
                    </h3>
                    
                    <div class="form-group">
                        <label for="bank_opening_balance">Bank Opening Balance (₱)</label>
                        <input 
                            type="number" 
                            id="bank_opening_balance" 
                            name="bank_opening_balance" 
                            step="0.01" 
                            value="<?= number_format($opening['bank_amount'], 2, '.', '') ?>"
                        >
                        <div class="help-text">
                            Enter the bank balance when you started tracking
                            <?php if ($opening['bank_amount'] > 0): ?>
                                <a href="?company=<?= $companyId ?>&reset=bank" 
                                   onclick="return confirm('Reset bank opening balance to ₱0.00?')" 
                                   style="color: #dc2626; margin-left: 10px;">
                                    Reset to ₱0.00
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="bank_opening_balance_date">Bank Opening Balance Date</label>
                        <input 
                            type="date" 
                            id="bank_opening_balance_date" 
                            name="bank_opening_balance_date" 
                            value="<?= $opening['bank_date'] ?>"
                            max="<?= date('Y-m-d') ?>"
                        >
                        <div class="help-text">
                            The date when this bank opening balance was valid
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="vertical-align: -2px; margin-right: 6px;">
                            <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022Z"/>
                        </svg>
                        Save Opening Balances
                    </button>
                </form>
                
                <div class="balance-summary">
                    <div class="balance-item">
                        <div class="label">💵 Cash Opening Balance</div>
                        <div class="value">₱<?= number_format($cashBalance['opening_balance'], 2) ?></div>
                    </div>
                    
                    <div class="balance-item">
                        <div class="label">💵 Current Cash Balance</div>
                        <div class="value" style="color: <?= $cashBalance['book_balance'] >= 0 ? '#059669' : '#dc2626' ?>;">
                            ₱<?= number_format($cashBalance['book_balance'], 2) ?>
                        </div>
                    </div>
                    
                    <div class="balance-item">
                        <div class="label">🏦 Bank Opening Balance</div>
                        <div class="value">₱<?= number_format($bankBalance['opening_balance'], 2) ?></div>
                    </div>
                    
                    <div class="balance-item">
                        <div class="label">🏦 Current Bank Balance</div>
                        <div class="value" style="color: <?= $bankBalance['book_balance'] >= 0 ? '#059669' : '#dc2626' ?>;">
                            ₱<?= number_format($bankBalance['book_balance'], 2) ?>
                        </div>
                    </div>
                    
                    <div class="balance-item <?= $combinedBalance['book_balance'] >= 0 ? 'positive' : 'negative' ?>" style="grid-column: 1 / -1; background: #f9fafb; border: 2px solid #e5e7eb;">
                        <div class="label">💰 Total Balance (Cash + Bank)</div>
                        <div class="value" style="font-size: 28px;">₱<?= number_format($combinedBalance['book_balance'], 2) ?></div>
                    </div>
                </div>
                
                <div class="info-box">
                    <h3>📌 About Opening Balance</h3>
                    <ul>
                        <li><strong>What is it?</strong> The opening balance is your cash on hand when you started using this system.</li>
                        <li><strong>Why set it?</strong> It ensures your book balance matches your actual cash position.</li>
                        <li><strong>When to update?</strong> Usually set once at the beginning. Change only if you made an error in the initial setup.</li>
                        <li><strong>Impact:</strong> All balance calculations will start from this opening balance + subsequent transactions.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
