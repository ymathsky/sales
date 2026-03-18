<?php
/**
 * Dashboard - Main page showing cash flow summary
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/models/Company.php';
require_once __DIR__ . '/models/Transaction.php';

requireLogin();

$pageTitle = 'Dashboard';
$userId = getCurrentUserId();
$companies = Company::getByUser($userId);

$currentCompanyId = getCurrentCompanyId();

// Set first company as active if none selected
if (!$currentCompanyId && !empty($companies)) {
    $currentCompanyId = $companies[0]['company_id'];
    setActiveCompany($currentCompanyId);
}

$currentCompany = null;
if ($currentCompanyId) {
    $currentCompany = Company::getById($currentCompanyId);
}

// Get date range (default: current month)
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Handle delete transaction
if (isset($_GET['delete']) && $currentCompanyId) {
    $transactionId = (int)$_GET['delete'];
    try {
        Transaction::delete($transactionId, $currentCompanyId);
        setFlashMessage('Transaction deleted successfully', 'success');
        header("Location: index.php?company=$currentCompanyId");
        exit;
    } catch (Exception $e) {
        setFlashMessage('Error deleting transaction: ' . $e->getMessage(), 'error');
    }
}

// Get balance and recent transactions
$balance = null;
$recentTransactions = [];
$cashOnHand = 0;
$bankBalance = 0;
$cashBalance = null;
$bankBookBalance = null;
$reconStats = null;
if ($currentCompanyId) {
    $balance = Company::getBalance($currentCompanyId, $startDate, $endDate);
    $cashBalance = Company::getBookBalance($currentCompanyId, null, 'cash');
    $bankBookBalance = Company::getBookBalance($currentCompanyId, null, 'bank');
    $cashOnHand = $cashBalance['book_balance'];
    $bankBalance = $bankBookBalance['book_balance'];
    $reconBankBalance = Company::getBankBalance($currentCompanyId);
    $reconStats = Transaction::getReconciliationStats($currentCompanyId, $startDate, $endDate);
    $recentTransactions = Transaction::getByCompany($currentCompanyId, [
        'start_date' => $startDate,
        'end_date' => $endDate
    ], 10);
}

include __DIR__ . '/views/header.php';
?>

<div class="dashboard">
    
    <?php if ($flashMsg = getFlashMessage()): ?>
        <div class="alert alert-<?= $flashMsg['type'] ?>">
            <?= htmlspecialchars($flashMsg['message']) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($currentCompany): ?>
        <!-- Modern Welcome Header -->
        <div class="dashboard-welcome-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 32px; border-radius: 16px; color: white; margin-bottom: 24px; box-shadow: 0 4px 20px rgba(118, 75, 162, 0.4);">
            <h2 style="margin: 0 0 8px 0; font-size: 28px; font-weight: 700;">Welcome back, <?= htmlspecialchars(getCurrentUserName()) ?>!</h2>
            <p style="margin: 0; opacity: 0.9; font-size: 16px; font-weight: 500;">
                <span style="opacity: 0.8;"><?= htmlspecialchars($currentCompany['name']) ?></span> • <?= date('l, F d, Y') ?>
            </p>
        </div>
        
        <!-- Filter Card -->
        <div class="card filter-card" style="background: white; border-radius: 16px; padding: 24px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
            <form method="GET" style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <input type="hidden" name="company" value="<?= $currentCompanyId ?>">
                
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <label style="font-weight: 600; color: #374151; font-size: 15px;">Period:</label>
                    <input type="date" name="start_date" value="<?= $startDate ?>" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; color: #1f2937;">
                    <span style="color: #6b7280; font-weight: 500;">to</span>
                    <input type="date" name="end_date" value="<?= $endDate ?>" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; color: #1f2937;">
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn" style="padding: 10px 24px; background: #2563eb; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                        Apply
                    </button>
                    <a href="?company=<?= $currentCompanyId ?>" class="btn" style="padding: 10px 24px; background: #f3f4f6; color: #4b5563; border: 1px solid #d1d5db; border-radius: 6px; font-weight: 600; text-align: center; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; transition: all 0.2s;" onmouseover="this.style.background='#e5e7eb'; this.style.color='#1f2937'" onmouseout="this.style.background='#f3f4f6'; this.style.color='#4b5563'">
                        Reset
                    </a>
                </div>
            </form>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card cash-in">
                <div class="stat-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                    </svg>
                </div>
                <h3>CASH IN</h3>
                <div class="stat-value"><?= formatMoney($balance['total_in'] ?? 0) ?></div>
                <div class="stat-detail">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?= $balance['count_in'] ?? 0 ?> transactions
                </div>
            </div>
            
            <div class="stat-card cash-out">
                <div class="stat-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                    </svg>
                </div>
                <h3>CASH OUT</h3>
                <div class="stat-value"><?= formatMoney($balance['total_out'] ?? 0) ?></div>
                <div class="stat-detail">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?= $balance['count_out'] ?? 0 ?> transactions
                </div>
            </div>
            
            <div class="stat-card balance <?= ($balance['balance'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">
                <div class="stat-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                </div>
                <h3>NET BALANCE</h3>
                <div class="stat-value"><?= formatMoney($balance['balance'] ?? 0) ?></div>
                <div class="stat-detail">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <?= date('M d', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?>
                </div>
            </div>
            
            <div class="stat-card cash-on-hand <?= $cashOnHand >= 0 ? 'positive' : 'negative' ?>">
                <div class="stat-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <h3>💵 CASH ON HAND</h3>
                <div class="stat-value"><?= formatMoney($cashOnHand) ?></div>
                <div class="stat-detail">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Physical cash balance
                </div>
            </div>
            
            <div class="stat-card cash-on-hand <?= $bankBalance >= 0 ? 'positive' : 'negative' ?>" style="border-left-color: #3b82f6;">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                </div>
                <h3>🏦 BANK BALANCE</h3>
                <div class="stat-value"><?= formatMoney($bankBalance) ?></div>
                <div class="stat-detail">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Bank account balance
                </div>
            </div>
        </div>
        
        <div class="actions-bar">
            <h2>Reconciliation Summary</h2>
        </div>
        
        <?php if ($cashBalance && $bankBookBalance && $reconBankBalance && $reconStats): ?>
            <div class="reconciliation-summary">
                <div class="recon-card">
                    <div class="recon-header">
                        <h3>💰 Balance Overview</h3>
                    </div>
                    <div class="recon-items">
                        <div class="recon-item">
                            <span class="recon-label">💵 Cash on Hand:</span>
                            <span class="recon-value"><?= formatMoney($cashBalance['book_balance']) ?></span>
                        </div>
                        <div class="recon-item">
                            <span class="recon-label">🏦 Bank Balance (Book):</span>
                            <span class="recon-value"><?= formatMoney($bankBookBalance['book_balance']) ?></span>
                        </div>
                        <div class="recon-item">
                            <span class="recon-label">🏦 Bank Balance (Reconciled):</span>
                            <span class="recon-value recon-bank"><?= formatMoney($reconBankBalance['bank_balance']) ?></span>
                        </div>
                        <div class="recon-item recon-difference">
                            <span class="recon-label">Total Balance:</span>
                            <span class="recon-value" style="color: #059669; font-size: 20px;">
                                <?= formatMoney($cashBalance['book_balance'] + $bankBookBalance['book_balance']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="recon-card">
                    <div class="recon-header">
                        <h3>📊 Reconciliation Stats (Period)</h3>
                    </div>
                    <div class="recon-items">
                        <div class="recon-item">
                            <span class="recon-label">Total Transactions:</span>
                            <span class="recon-value"><?= $reconStats['total_transactions'] ?></span>
                        </div>
                        <div class="recon-item">
                            <span class="recon-label">Reconciled:</span>
                            <span class="recon-value" style="color: #059669;"><?= $reconStats['reconciled_count'] ?> (<?= number_format($reconStats['reconciliation_rate'], 1) ?>%)</span>
                        </div>
                        <div class="recon-item">
                            <span class="recon-label">Unreconciled:</span>
                            <span class="recon-value" style="color: #f59e0b;"><?= $reconStats['unreconciled_count'] ?></span>
                        </div>
                        <div class="recon-item">
                            <span class="recon-label">Unreconciled Amount:</span>
                            <span class="recon-value" style="color: #f59e0b;"><?= formatMoney(abs($reconStats['unreconciled_net'])) ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="recon-card recon-action">
                    <div class="recon-header">
                        <h3>🔧 Quick Actions</h3>
                    </div>
                    <div class="recon-actions">
                        <a href="<?= WEB_ROOT ?>/reconciliation/index.php?company=<?= $currentCompanyId ?>" onclick="if(window.openNewTab) { window.openNewTab(this.href, 'Bank Reconciliation'); return false; }" class="btn btn-primary">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20" style="vertical-align:-3px;margin-right:5px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                            Bank Reconciliation
                        </a>
                        <a href="<?= WEB_ROOT ?>/reconciliation/opening-balance.php?company=<?= $currentCompanyId ?>" onclick="if(window.openNewTab) { window.openNewTab(this.href, 'Opening Balance'); return false; }" class="btn btn-outline">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20" style="vertical-align:-3px;margin-right:5px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Opening Balance
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="actions-bar">
            <h2>Recent Transactions</h2>
            <div class="quick-actions">
                <a href="<?= WEB_ROOT ?>/pos/index.php?company=<?= $currentCompanyId ?>" target="_blank"
                   class="btn btn-success" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20" style="display:inline;vertical-align:middle;margin-right:5px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Point of Sale
                </a>
                <a href="<?= WEB_ROOT ?>/transactions/create.php?company=<?= $currentCompanyId ?>" onclick="if(window.openNewTab) { window.openNewTab(this.href, 'Add Transaction'); return false; }"
                   class="btn btn-success">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20" style="display:inline;vertical-align:middle;margin-right:5px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Transaction
                </a>
                <a href="<?= WEB_ROOT ?>/transactions/list.php?company=<?= $currentCompanyId ?>" onclick="if(window.openNewTab) { window.openNewTab(this.href, 'All Transactions'); return false; }"
                   class="btn btn-primary">View All</a>
                <a href="<?= WEB_ROOT ?>/reports/index.php?company=<?= $currentCompanyId ?>" onclick="if(window.openNewTab) { window.openNewTab(this.href, 'Reports'); return false; }"
                   class="btn btn-secondary">Reports</a>
            </div>
        </div>
        
        <?php if (!empty($recentTransactions)): ?>
            <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTransactions as $trans): ?>
                        <tr>
                            <td><?= formatDate($trans['transaction_date'], 'M d, Y') ?></td>
                            <td><?= getTypeBadge($trans['type']) ?></td>
                            <td><?= htmlspecialchars($trans['category'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($trans['description'] ?? '-') ?></td>
                            <td class="amount <?= $trans['type'] ?>">
                                <?= formatMoney($trans['amount']) ?>
                            </td>
                            <td>
                                <a href="<?= WEB_ROOT ?>/transactions/edit.php?id=<?= $trans['transaction_id'] ?>&company=<?= $currentCompanyId ?>" 
                                   class="btn btn-sm">Edit</a>
                                <button onclick="if(confirm('Are you sure you want to delete this transaction?')) window.location.href='?company=<?= $currentCompanyId ?>&delete=<?= $trans['transaction_id'] ?>'" 
                                        class="btn btn-sm btn-danger" 
                                        style="margin-left: 5px;">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No transactions found for this period.</p>
                <a href="<?= WEB_ROOT ?>/transactions/create.php?company=<?= $currentCompanyId ?>" 
                   class="btn btn-primary">Add Your First Transaction</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state">
            <p>No companies available. Please contact administrator.</p>
        </div>
    <?php endif; ?>
</div>

<style>
@media print {
    /* Hide non-essential elements */
    .alert,
    .date-filter,
    .btn,
    button,
    .sidebar,
    .main-header,
    .tab-bar,
    td:last-child,
    th:last-child,
    .empty-state .btn {
        display: none !important;
    }
    
    body {
        background: white !important;
        padding: 15mm !important;
    }
    
    .dashboard-welcome {
        text-align: center !important;
        border-bottom: 2px solid #000 !important;
        padding-bottom: 10pt !important;
        margin-bottom: 15pt !important;
    }
    
    .balance-cards {
        page-break-inside: avoid !important;
    }
}
</style>

<?php include __DIR__ . '/views/footer.php'; ?>
