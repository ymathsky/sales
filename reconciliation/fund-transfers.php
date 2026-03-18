<?php
/**
 * Fund Transfers - Manage deposits, withdrawals, and checks
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/FundTransfer.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Customer.php';

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

// Get all companies user has access to (for inter-company transfers)
$userCompanies = Company::getByUser($userId);

// Get customers for client deposit
$customers = Customer::getByCompany($companyId, true);

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $transferDate = $_POST['transfer_date'] ?? date('Y-m-d');
    $checkNumber = $_POST['check_number'] ?? null;
    $description = $_POST['description'] ?? null;
    
    if ($amount <= 0) {
        $message = 'Please enter a valid amount';
        $messageType = 'error';
    } elseif ($_POST['action'] === 'create_transfer') {
        // Fund transfer between cash and bank
        $transferType = $_POST['transfer_type'] ?? '';
        
        if (!in_array($transferType, ['deposit', 'withdrawal'])) {
            $message = 'Invalid transfer type';
            $messageType = 'error';
        } else {
            try {
                FundTransfer::create([
                    'company_id' => $companyId,
                    'transfer_type' => $transferType,
                    'amount' => $amount,
                    'transfer_date' => $transferDate,
                    'check_number' => $checkNumber,
                    'description' => $description,
                    'created_by' => $userId
                ]);
                $message = 'Fund transfer recorded successfully';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($_POST['action'] === 'create_check') {
        // Check issuance - creates a transaction (deducts from bank)
        if (empty($checkNumber)) {
            $message = 'Check number is required';
            $messageType = 'error';
        } else {
            try {
                Transaction::create([
                    'company_id' => $companyId,
                    'type' => 'out',
                    'amount' => $amount,
                    'transaction_date' => $transferDate,
                    'category' => 'Check Payment',
                    'description' => $description,
                    'reference_number' => $checkNumber,
                    'payment_method' => 'check',
                    'transaction_account' => 'bank',
                    'created_by' => $userId
                ]);
                $message = 'Check issuance recorded successfully';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($_POST['action'] === 'create_client_deposit') {
        // Client deposit - creates an incoming bank transaction
        $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $customerName = '';
        
        if ($customerId) {
            $customer = Customer::getById($customerId, $companyId);
            $customerName = $customer ? $customer['customer_name'] : 'Unknown Customer';
        }
        
        try {
            Transaction::create([
                'company_id' => $companyId,
                'type' => 'in',
                'amount' => $amount,
                'transaction_date' => $transferDate,
                'category' => 'Client Payment',
                'description' => ($customerName ? 'Payment from ' . $customerName : 'Client deposit') . ($description ? ' - ' . $description : ''),
                'payment_method' => 'bank_transfer',
                'transaction_account' => 'bank',
                'created_by' => $userId
            ]);
            $message = 'Client deposit recorded successfully';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'create_intercompany') {
        // Inter-company bank transfer
        $toCompanyId = (int)($_POST['to_company_id'] ?? 0);
        
        if ($toCompanyId === $companyId) {
            $message = 'Cannot transfer to the same company';
            $messageType = 'error';
        } elseif (!userHasAccessToCompany($userId, $toCompanyId)) {
            $message = 'You do not have access to the destination company';
            $messageType = 'error';
        } else {
            try {
                $pdo = getDBConnection();
                $pdo->beginTransaction();
                
                $toCompany = Company::getById($toCompanyId);
                
                // Create outgoing transaction from source company
                Transaction::create([
                    'company_id' => $companyId,
                    'type' => 'out',
                    'amount' => $amount,
                    'transaction_date' => $transferDate,
                    'category' => 'Inter-Company Transfer',
                    'description' => 'Transfer to ' . $toCompany['name'] . ($description ? ' - ' . $description : ''),
                    'payment_method' => 'bank_transfer',
                    'transaction_account' => 'bank',
                    'created_by' => $userId
                ]);
                
                // Create incoming transaction to destination company
                Transaction::create([
                    'company_id' => $toCompanyId,
                    'type' => 'in',
                    'amount' => $amount,
                    'transaction_date' => $transferDate,
                    'category' => 'Inter-Company Transfer',
                    'description' => 'Transfer from ' . $company['name'] . ($description ? ' - ' . $description : ''),
                    'payment_method' => 'bank_transfer',
                    'transaction_account' => 'bank',
                    'created_by' => $userId
                ]);
                
                $pdo->commit();
                $message = 'Inter-company transfer completed successfully';
                $messageType = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Handle delete transfer
if (isset($_GET['delete'])) {
    $transferId = (int)$_GET['delete'];
    try {
        FundTransfer::delete($transferId, $companyId);
        header("Location: fund-transfers.php?company=$companyId&deleted=1");
        exit;
    } catch (Exception $e) {
        $message = 'Error deleting transfer: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle delete transaction (issued check)
if (isset($_GET['delete_transaction'])) {
    $transactionId = (int)$_GET['delete_transaction'];
    try {
        Transaction::delete($transactionId, $companyId);
        header("Location: fund-transfers.php?company=$companyId&deleted=1");
        exit;
    } catch (Exception $e) {
        $message = 'Error deleting transaction: ' . $e->getMessage();
        $messageType = 'error';
    }
}

if (isset($_GET['deleted'])) {
    $message = 'Record deleted successfully';
    $messageType = 'success';
}

// Get filters
$filters = [
    'transfer_type' => $_GET['type'] ?? '',
    'start_date' => $_GET['start_date'] ?? date('Y-m-01'),
    'end_date' => $_GET['end_date'] ?? date('Y-m-d'),
];

// Get transfers
$transfers = FundTransfer::getByCompany($companyId, $filters);
$totals = FundTransfer::getTotals($companyId, $filters['start_date'], $filters['end_date']);

// Get issued checks from transactions
$checkSql = "SELECT t.*, u.full_name as created_by_name
             FROM transactions t
             LEFT JOIN users u ON t.created_by = u.user_id
             WHERE t.company_id = ? 
             AND t.payment_method = 'check' 
             AND t.transaction_account = 'bank'
             AND t.type = 'out'";

$checkParams = [$companyId];

if (!empty($filters['start_date'])) {
    $checkSql .= " AND t.transaction_date >= ?";
    $checkParams[] = $filters['start_date'];
}

if (!empty($filters['end_date'])) {
    $checkSql .= " AND t.transaction_date <= ?";
    $checkParams[] = $filters['end_date'];
}

$checkSql .= " ORDER BY t.transaction_date DESC, t.created_at DESC";

$checkStmt = executeQuery($checkSql, $checkParams);
$issuedChecks = $checkStmt->fetchAll();

// Get total issued checks
$checkTotalSql = "SELECT SUM(amount) as total FROM transactions 
                  WHERE company_id = ? AND payment_method = 'check' 
                  AND transaction_account = 'bank' AND type = 'out'";
$checkTotalParams = [$companyId];

if (!empty($filters['start_date'])) {
    $checkTotalSql .= " AND transaction_date >= ?";
    $checkTotalParams[] = $filters['start_date'];
}

if (!empty($filters['end_date'])) {
    $checkTotalSql .= " AND transaction_date <= ?";
    $checkTotalParams[] = $filters['end_date'];
}

$checkTotalStmt = executeQuery($checkTotalSql, $checkTotalParams);
$checkTotal = $checkTotalStmt->fetch()['total'] ?? 0;

// Get balances
$cashBalance = Company::getBookBalance($companyId, null, 'cash');
$bankBalance = Company::getBookBalance($companyId, null, 'bank');

// Set page title
$pageTitle = 'Fund Transfers';
require_once __DIR__ . '/../views/header.php';
?>
    <style>
        .transfer-container {
            padding: 0;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 28px;
            color: #1f2937;
        }
        
        .balance-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .balance-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .balance-card .label {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .balance-card .amount {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
        }
        
        .balance-card.cash {
            border-left: 4px solid #10b981;
        }
        
        .balance-card.bank {
            border-left: 4px solid #3b82f6;
        }
        
        .transfer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .transfer-form {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .transfer-form h2 {
            margin: 0 0 20px 0;
            font-size: 20px;
            color: #1f2937;
        }
        
        .transfer-types {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .transfer-type-btn {
            padding: 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        
        .transfer-type-btn:hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        
        .transfer-type-btn.active {
            border-color: #3b82f6;
            background: #3b82f6;
            color: white;
        }
        
        .transfer-type-btn .icon {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .transfer-type-btn .title {
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        .totals-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .totals-card h2 {
            margin: 0 0 20px 0;
            font-size: 20px;
            color: #1f2937;
        }
        
        .total-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .total-item:last-child {
            border-bottom: none;
        }
        
        .total-item .label {
            font-size: 14px;
            color: #6b7280;
        }
        
        .total-item .value {
            font-size: 16px;
            font-weight: 600;
        }
        
        .transfers-list {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .filters-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filters-bar input,
        .filters-bar select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .filters-bar button {
            padding: 8px 16px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 12px;
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        
        tbody tr:hover {
            background: #f9fafb;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-deposit {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-withdrawal {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-check {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .amount {
            font-weight: 600;
        }
        
        .amount.positive {
            color: #10b981;
        }
        
        .amount.negative {
            color: #f59e0b;
        }
        
        .btn-delete {
            padding: 6px 12px;
            background: #fee2e2;
            color: #991b1b;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }
        
        .btn-delete:hover {
            background: #fecaca;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
    </style>

    <div class="content-wrapper">
        <div class="page-header" style="margin-bottom: 24px;">
            <div>
                <h1 style="margin: 0 0 8px 0; font-size: 28px; color: #1f2937;">Fund Transfers</h1>
                <p style="margin: 0; color: #6b7280;">Move money between Cash and Bank accounts</p>
            </div>
        </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="balance-cards">
                        <div class="balance-card cash">
                            <div class="label">💵 Cash on Hand</div>
                            <div class="amount">₱<?= number_format($cashBalance['book_balance'], 2) ?></div>
                        </div>
                        
                        <div class="balance-card bank">
                            <div class="label">🏦 Bank Balance</div>
                            <div class="amount">₱<?= number_format($bankBalance['book_balance'], 2) ?></div>
                        </div>
                        
                        <div class="balance-card">
                            <div class="label">💰 Total Balance</div>
                            <div class="amount">₱<?= number_format($cashBalance['book_balance'] + $bankBalance['book_balance'], 2) ?></div>
                        </div>
                    </div>
                    
                    <div class="transfer-grid">
                        <div class="transfer-form">
                            <h2>New Fund Transfer / Check Issuance</h2>
                            
                            <div class="transfer-types" style="grid-template-columns: repeat(5, 1fr); margin-bottom: 24px;">
                                <div class="transfer-type-btn active" onclick="showForm('deposit', this)">
                                    <div class="icon">💵➡️🏦</div>
                                    <div class="title">Deposit</div>
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Cash to Bank</div>
                                </div>
                                
                                <div class="transfer-type-btn" onclick="showForm('withdrawal', this)">
                                    <div class="icon">🏦➡️💵</div>
                                    <div class="title">Withdrawal</div>
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Bank to Cash</div>
                                </div>
                                
                                <div class="transfer-type-btn" onclick="showForm('check', this)">
                                    <div class="icon">📝</div>
                                    <div class="title">Check Issue</div>
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Issue Check</div>
                                </div>
                                
                                <div class="transfer-type-btn" onclick="showForm('intercompany', this)">
                                    <div class="icon">🏢↔️🏢</div>
                                    <div class="title">Inter-Company</div>
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Bank Transfer</div>
                                </div>
                                
                                <div class="transfer-type-btn" onclick="showForm('client_deposit', this)">
                                    <div class="icon">👤➡️🏦</div>
                                    <div class="title">Client Deposit</div>
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Customer Payment</div>
                                </div>
                            </div>
                            
                            <!-- Fund Transfer Form (Deposit/Withdrawal) -->
                            <form method="POST" action="" id="transferForm" style="display: block;">
                                <input type="hidden" name="action" value="create_transfer">
                                <input type="hidden" name="transfer_type" id="transfer_type" value="deposit">
                                
                                <div class="form-group">
                                    <label for="amount">Amount (₱) *</label>
                                    <input type="number" id="amount" name="amount" step="0.01" min="0.01" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="transfer_date">Date *</label>
                                    <input type="date" id="transfer_date" name="transfer_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" rows="3" placeholder="Optional notes..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn-primary">Record Transfer</button>
                            </form>
                            
                            <!-- Check Issuance Form -->
                            <form method="POST" action="" id="checkForm" style="display: none;">
                                <input type="hidden" name="action" value="create_check">
                                
                                <div class="form-group">
                                    <label for="check_amount">Amount (₱) *</label>
                                    <input type="number" id="check_amount" name="amount" step="0.01" min="0.01" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="check_date">Date *</label>
                                    <input type="date" id="check_date" name="transfer_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="check_num">Check Number *</label>
                                    <input type="text" id="check_num" name="check_number" required placeholder="e.g., 001234">
                                </div>
                                
                                <div class="form-group">
                                    <label for="check_description">Description / Payee</label>
                                    <textarea id="check_description" name="description" rows="3" placeholder="e.g., Payment to supplier, Payroll check..." required></textarea>
                                </div>
                                
                                <button type="submit" class="btn-primary">Issue Check</button>
                            </form>
                            
                            <!-- Inter-Company Transfer Form -->
                            <form method="POST" action="" id="intercompanyForm" style="display: none;">
                                <input type="hidden" name="action" value="create_intercompany">
                                
                                <div class="form-group">
                                    <label for="ic_amount">Amount (₱) *</label>
                                    <input type="number" id="ic_amount" name="amount" step="0.01" min="0.01" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="ic_date">Date *</label>
                                    <input type="date" id="ic_date" name="transfer_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="to_company_id">Transfer To Company *</label>
                                    <select id="to_company_id" name="to_company_id" required style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                        <option value="">-- Select Company --</option>
                                        <?php foreach ($userCompanies as $comp): ?>
                                            <?php if ($comp['company_id'] != $companyId): ?>
                                                <option value="<?= $comp['company_id'] ?>"><?= htmlspecialchars($comp['name']) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="ic_description">Description / Purpose</label>
                                    <textarea id="ic_description" name="description" rows="3" placeholder="e.g., Capital transfer, Shared expense..." required></textarea>
                                </div>
                                
                                <div style="padding: 12px; background: #fffbeb; border: 1px solid #fef08a; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
                                    <strong>⚠️ Note:</strong> This will deduct from <strong><?= htmlspecialchars($company['name']) ?></strong> bank and add to the destination company's bank.
                                </div>
                                
                                <button type="submit" class="btn-primary">Transfer Between Companies</button>
                            </form>
                            
                            <!-- Client Deposit Form -->
                            <form method="POST" action="" id="clientDepositForm" style="display: none;">
                                <input type="hidden" name="action" value="create_client_deposit">
                                
                                <div class="form-group">
                                    <label for="cd_amount">Amount (₱) *</label>
                                    <input type="number" id="cd_amount" name="amount" step="0.01" min="0.01" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cd_date">Date *</label>
                                    <input type="date" id="cd_date" name="transfer_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="customer_id">Client / Customer</label>
                                    <select id="customer_id" name="customer_id" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                        <option value="">-- Select Client (Optional) --</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?= $customer['customer_id'] ?>">
                                                <?= htmlspecialchars($customer['customer_name']) ?>
                                                <?php if ($customer['total_outstanding'] > 0): ?>
                                                    (Outstanding: ₱<?= number_format($customer['total_outstanding'], 2) ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small style="color: #6b7280; font-size: 12px; margin-top: 4px; display: block;">
                                        Select a client from your customer list or leave blank for general deposit
                                    </small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cd_description">Additional Notes</label>
                                    <textarea id="cd_description" name="description" rows="3" placeholder="e.g., Invoice #123, Payment for services..."></textarea>
                                </div>
                                
                                <div style="padding: 12px; background: #d1fae5; border: 1px solid #a7f3d0; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
                                    <strong>✓ Incoming:</strong> This will be added to <strong><?= htmlspecialchars($company['name']) ?></strong> bank balance as cash in.
                                </div>
                                
                                <button type="submit" class="btn-primary">Record Client Deposit</button>
                            </form>
                        </div>
                        
                        <div class="totals-card">
                            <h2>Period Totals</h2>
                            <div class="total-item">
                                <span class="label">💵➡️🏦 Deposits</span>
                                <span class="value amount positive">₱<?= number_format($totals['deposit'], 2) ?></span>
                            </div>
                            <div class="total-item">
                                <span class="label">🏦➡️💵 Withdrawals</span>
                                <span class="value amount negative">₱<?= number_format($totals['withdrawal'], 2) ?></span>
                            </div>
                            <div class="total-item">
                                <span class="label">📝 Checks Issued</span>
                                <span class="value amount negative">₱<?= number_format($checkTotal, 2) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="transfers-list">
                        <h2>Transfer & Check History</h2>
                        
                        <form method="GET" class="filters-bar">
                            <input type="hidden" name="company" value="<?= $companyId ?>">
                            <select name="type">
                                <option value="">All Types</option>
                                <option value="deposit" <?= $filters['transfer_type'] === 'deposit' ? 'selected' : '' ?>>Deposits</option>
                                <option value="withdrawal" <?= $filters['transfer_type'] === 'withdrawal' ? 'selected' : '' ?>>Withdrawals</option>
                                <option value="check_issued" <?= $filters['transfer_type'] === 'check_issued' ? 'selected' : '' ?>>Checks Issued</option>
                            </select>
                            <input type="date" name="start_date" value="<?= htmlspecialchars($filters['start_date']) ?>">
                            <input type="date" name="end_date" value="<?= htmlspecialchars($filters['end_date']) ?>">
                            <button type="submit">Filter</button>
                            <a href="?company=<?= $companyId ?>" style="padding: 8px 16px; background: #6b7280; color: white; text-decoration: none; border-radius: 6px;">Reset</a>
                        </form>
                        
                        <?php 
                        // Combine transfers and checks into one array
                        $allTransactions = [];
                        
                        // Add transfers
                        if (empty($filters['transfer_type']) || in_array($filters['transfer_type'], ['deposit', 'withdrawal'])) {
                            foreach ($transfers as $transfer) {
                                $allTransactions[] = [
                                    'date' => $transfer['transfer_date'],
                                    'type' => $transfer['transfer_type'],
                                    'amount' => $transfer['amount'],
                                    'check_number' => $transfer['check_number'],
                                    'description' => $transfer['description'],
                                    'created_by' => $transfer['created_by_name'],
                                    'id' => $transfer['transfer_id'],
                                    'is_transfer' => true
                                ];
                            }
                        }
                        
                        // Add issued checks
                        if (empty($filters['transfer_type']) || $filters['transfer_type'] === 'check_issued') {
                            foreach ($issuedChecks as $check) {
                                $allTransactions[] = [
                                    'date' => $check['transaction_date'],
                                    'type' => 'check_issued',
                                    'amount' => $check['amount'],
                                    'check_number' => $check['reference_number'],
                                    'description' => $check['description'],
                                    'created_by' => $check['created_by_name'],
                                    'id' => $check['transaction_id'],
                                    'is_transfer' => false
                                ];
                            }
                        }
                        
                        // Sort by date descending
                        usort($allTransactions, function($a, $b) {
                            return strtotime($b['date']) - strtotime($a['date']);
                        });
                        ?>
                        
                        <?php if (empty($allTransactions)): ?>
                            <div class="empty-state">
                                <p>No transactions found.</p>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Check #</th>
                                        <th>Description</th>
                                        <th>Created By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allTransactions as $transaction): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($transaction['date'])) ?></td>
                                            <td>
                                                <?php if ($transaction['type'] === 'deposit'): ?>
                                                    <span class="badge badge-deposit">💵➡️🏦 Deposit</span>
                                                <?php elseif ($transaction['type'] === 'withdrawal'): ?>
                                                    <span class="badge badge-withdrawal">🏦➡️💵 Withdrawal</span>
                                                <?php else: ?>
                                                    <span class="badge badge-check">📝 Check Issued</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="amount <?= $transaction['type'] === 'deposit' ? 'positive' : 'negative' ?>">
                                                ₱<?= number_format($transaction['amount'], 2) ?>
                                            </td>
                                            <td><?= htmlspecialchars($transaction['check_number'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($transaction['description'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($transaction['created_by'] ?? 'Unknown') ?></td>
                                            <td>
                                                <?php if ($transaction['is_transfer']): ?>
                                                    <button class="btn-delete" onclick="if(confirm('Delete this transfer?')) window.location.href='?company=<?= $companyId ?>&delete=<?= $transaction['id'] ?>'">
                                                        Delete
                                                    </button>
                                                <?php else: ?>
                                                    <div style="display: flex; gap: 8px;">
                                                        <a href="../transactions/edit.php?id=<?= $transaction['id'] ?>&company=<?= $companyId ?>" style="padding: 6px 12px; background: #dbeafe; color: #1e40af; text-decoration: none; border-radius: 6px; font-size: 13px;">
                                                            Edit
                                                        </a>
                                                        <button class="btn-delete" onclick="if(confirm('Delete this check issuance?')) window.location.href='?company=<?= $companyId ?>&delete_transaction=<?= $transaction['id'] ?>'">
                                                            Delete
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
    </div>
    
    <script>
        function showForm(type, element) {
            const transferForm = document.getElementById('transferForm');
            const checkForm = document.getElementById('checkForm');
            const intercompanyForm = document.getElementById('intercompanyForm');
            const clientDepositForm = document.getElementById('clientDepositForm');
            
            // Update button states
            document.querySelectorAll('.transfer-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            if (element) {
                element.classList.add('active');
            }
            
            // Hide all forms
            transferForm.style.display = 'none';
            checkForm.style.display = 'none';
            intercompanyForm.style.display = 'none';
            clientDepositForm.style.display = 'none';
            
            // Show appropriate form
            if (type === 'check') {
                checkForm.style.display = 'block';
            } else if (type === 'intercompany') {
                intercompanyForm.style.display = 'block';
            } else if (type === 'client_deposit') {
                clientDepositForm.style.display = 'block';
            } else {
                transferForm.style.display = 'block';
                document.getElementById('transfer_type').value = type;
            }
        }
    </script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
