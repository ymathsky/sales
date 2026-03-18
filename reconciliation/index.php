<?php
/**
 * Bank Reconciliation
 * Match transactions against bank statements
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/Transaction.php';

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
requireCompanyAccess($companyId);

$company = Company::getById($companyId);

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'reconcile':
            $transactionId = intval($_POST['transaction_id'] ?? 0);
            $date = $_POST['reconciled_date'] ?? date('Y-m-d');
            $success = Transaction::reconcile($transactionId, $companyId, $userId, $date);
            echo json_encode(['success' => $success]);
            exit;
            
        case 'unreconcile':
            $transactionId = intval($_POST['transaction_id'] ?? 0);
            $success = Transaction::unreconcile($transactionId, $companyId);
            echo json_encode(['success' => $success]);
            exit;
            
        case 'bulk_unreconcile':
            $transactionIds = $_POST['transaction_ids'] ?? [];
            if (is_string($transactionIds)) {
                $transactionIds = json_decode($transactionIds, true);
            }
            // Add a bulkUnreconcile method to Transaction model or loop here
            // Since we don't have it yet, let's loop
            $count = 0;
            foreach ($transactionIds as $id) {
                if (Transaction::unreconcile($id, $companyId)) {
                    $count++;
                }
            }
            echo json_encode(['success' => true, 'count' => $count]);
            exit;
            
        case 'bulk_reconcile':
            $transactionIds = $_POST['transaction_ids'] ?? [];
            if (is_string($transactionIds)) {
                $transactionIds = json_decode($transactionIds, true);
            }
            $date = $_POST['reconciled_date'] ?? date('Y-m-d');
            $count = Transaction::bulkReconcile($transactionIds, $companyId, $userId, $date);
            echo json_encode(['success' => true, 'count' => $count]);
            exit;
    }
}

// Get filter parameters
$filters = [
    'type' => $_GET['type'] ?? '',
    'start_date' => $_GET['start_date'] ?? date('Y-m-01'), // First day of current month
    'end_date' => $_GET['end_date'] ?? date('Y-m-d'),
];

// Get transactions
$showReconciled = isset($_GET['show_reconciled']) && $_GET['show_reconciled'] === '1';

if ($showReconciled) {
    // Show last 1000 items if showing history
    $transactions = Transaction::getByCompany($companyId, $filters, 1000, 0);
} else {
    // Show all unreconciled for the period
    $transactions = Transaction::getUnreconciled($companyId, $filters);
}

// Get balances
$bookBalance = Company::getBookBalance($companyId);
$bankBalance = Company::getBankBalance($companyId);
$stats = Transaction::getReconciliationStats($companyId, $filters['start_date'], $filters['end_date']);

$difference = $bookBalance['book_balance'] - $bankBalance['bank_balance'];

$pageTitle = 'Bank Reconciliation';
include __DIR__ . '/../views/header.php';
?>

<div class="page-header">
    <div style="display: flex; gap: 16px; align-items: center;">
        <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 12px; border-radius: 12px; color: white; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3);">
            <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 26px; font-weight: 800; color: #111827; letter-spacing: -0.025em;">Bank Reconciliation</h1>
            <p style="margin: 4px 0 0; color: #6b7280; font-size: 15px;">Match your book records with bank statements to ensure accuracy.</p>
        </div>
    </div>
    <div>
        <a href="/sales/reconciliation/opening-balance.php?company=<?= $companyId ?>" 
           onclick="if(window.openNewTab) { window.openNewTab(this.href, 'Opening Balance'); return false; }"
           class="btn btn-white"
           style="background: white; border: 1px solid #d1d5db; color: #374151; font-weight: 600; padding: 10px 20px; border-radius: 8px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); display: flex; align-items: center; gap: 8px; transition: all 0.2s;">
            <svg style="width: 18px; height: 18px; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            Settings & Opening Balance
        </a>
    </div>
</div>

<div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; margin-bottom: 32px;">
    <!-- Book Balance -->
    <div style="background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border: 1px solid #f3f4f6; position: relative; overflow: hidden;">
        <div style="position: absolute; right: 0; top: 0; padding: 16px; opacity: 0.1;">
            <svg style="width: 64px; height: 64px; color: #3b82f6;" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"></path><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"></path></svg>
        </div>
        <div style="font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 8px;">
            Book Balance
        </div>
        <div style="font-size: 32px; font-weight: 800; color: #1f2937; letter-spacing: -0.025em; margin-bottom: 4px;">
            <?= formatMoney($bookBalance['book_balance']) ?>
        </div>
        <div style="font-size: 13px; color: #6b7280; display: flex; align-items: center; gap: 4px;">
            <span style="display: inline-block; width: 8px; height: 8px; background: #3b82f6; border-radius: 50%;"></span>
            System Records
        </div>
    </div>

    <!-- Bank Balance -->
    <div style="background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border: 1px solid #f3f4f6; position: relative; overflow: hidden;">
        <div style="position: absolute; right: 0; top: 0; padding: 16px; opacity: 0.1;">
            <svg style="width: 64px; height: 64px; color: #059669;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a1 1 0 100-2 1 1 0 000 2zm-3-1a1 1 0 112 0 1 1 0 01-2 0z" clip-rule="evenodd"></path></svg>
        </div>
        <div style="font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 8px;">
            Bank Statement
        </div>
        <div style="font-size: 32px; font-weight: 800; color: #059669; letter-spacing: -0.025em; margin-bottom: 4px;">
            <?= formatMoney($bankBalance['bank_balance']) ?>
        </div>
        <div style="font-size: 13px; color: #6b7280; display: flex; align-items: center; gap: 4px;">
            <span style="display: inline-block; width: 8px; height: 8px; background: #059669; border-radius: 50%;"></span>
            Reconciled Amount
        </div>
    </div>

    <!-- Difference -->
    <div style="background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border: 1px solid #f3f4f6;">
        <div style="font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 8px;">
            Difference
        </div>
        <div style="font-size: 32px; font-weight: 800; color: <?= $difference == 0 ? '#10b981' : '#f59e0b' ?>; letter-spacing: -0.025em; margin-bottom: 4px;">
            <?= formatMoney(abs($difference)) ?>
        </div>
        <div style="font-size: 13px; color: <?= $difference == 0 ? '#059669' : '#d97706' ?>; font-weight: 500; display: flex; align-items: center; gap: 6px;">
            <?php if ($difference == 0): ?>
                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Perfectly Balanced
            <?php else: ?>
                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                Action Needed
            <?php endif; ?>
        </div>
    </div>

    <!-- Progress -->
    <div style="background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border: 1px solid #f3f4f6; display: flex; flex-direction: column; justify-content: center;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 12px;">
            <div style="font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280;">
                Completion
            </div>
            <div style="font-size: 24px; font-weight: 800; color: #3b82f6; line-height: 1;">
                <?= number_format($stats['reconciliation_rate'], 0) ?>%
            </div>
        </div>
        <div style="height: 12px; background: #f3f4f6; border-radius: 6px; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
            <div style="height: 100%; background: linear-gradient(90deg, #3b82f6, #2563eb); border-radius: 6px; width: <?= $stats['reconciliation_rate'] ?>%; transition: width 1s ease-in-out;"></div>
        </div>
        <div style="font-size: 13px; color: #6b7280; margin-top: 12px; text-align: center;">
            of transactions reconciled
        </div>
    </div>
</div>

<div class="card" style="border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border: 1px solid #f3f4f6; overflow: hidden; margin-bottom: 24px;">
    <div style="padding: 24px; background: #fff; border-bottom: 1px solid #f3f4f6;">
        <form method="GET" style="display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap;">
            <input type="hidden" name="company" value="<?= $companyId ?>">
            
            <div style="flex-grow: 1; display: flex; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">Transaction Type</label>
                    <div style="position: relative;">
                         <select name="type" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; appearance: none; background-color: white;">
                            <option value="">All Types</option>
                            <option value="in" <?= $filters['type'] === 'in' ? 'selected' : '' ?>>Cash In (+)</option>
                            <option value="out" <?= $filters['type'] === 'out' ? 'selected' : '' ?>>Cash Out (-)</option>
                        </select>
                        <div style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #6b7280;">
                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">Date Range</label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="date" name="start_date" value="<?= htmlspecialchars($filters['start_date']) ?>" style="flex: 1; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none;">
                        <span style="color: #9ca3af;">to</span>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($filters['end_date']) ?>" style="flex: 1; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none;">
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; align-items: center; padding-bottom: 2px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; transition: all 0.2s;">
                    <input type="checkbox" name="show_reconciled" value="1" <?= $showReconciled ? 'checked' : '' ?> onchange="this.form.submit()" style="accent-color: #3b82f6; width: 16px; height: 16px;">
                    <span style="font-size: 14px; font-weight: 500; color: #4b5563;">Show Reconciled</span>
                </label>

                <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 24px; font-weight: 600;">Apply Filters</button>
                <a href="?company=<?= $companyId ?>" style="height: 42px; padding: 0 16px; display: flex; align-items: center; color: #6b7280; font-weight: 500; text-decoration: none; border: 1px solid #d1d5db; border-radius: 8px; background: white;">Reset</a>
            </div>
        </form>
    </div>

    <!-- Table Header with Bulk Actions -->
    <div style="padding: 16px 24px; background: #f8fafc; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0; font-size: 16px; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.05em;">Transactions List</h2>
        
        <div class="bulk-actions" style="display: flex; align-items: center; gap: 16px; opacity: 0.5; pointer-events: none; transition: all 0.2s;">
            <span id="selectedCount" style="color: #6b7280; font-size: 13px; font-weight: 600; background: #e2e8f0; padding: 4px 10px; border-radius: 20px;">0 selected</span>
            <?php if (!$showReconciled): ?>
                <button id="bulkReconcileBtn" class="btn btn-success" disabled style="display: flex; align-items: center; gap: 6px; font-size: 13px; padding: 6px 16px; border-radius: 6px;">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Reconcile Selection
                </button>
            <?php else: ?>
                <button id="bulkUnreconcileBtn" class="btn btn-white" disabled style="display: flex; align-items: center; gap: 6px; font-size: 13px; padding: 6px 16px; border-radius: 6px; border: 1px solid #cbd5e1; background: white; color: #64748b;">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                    Undo Selection
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <div style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #ffffff; border-bottom: 2px solid #f1f5f9;">
                    <th style="padding: 16px 24px; width: 40px; text-align: center;">
                        <input type="checkbox" id="selectAll" style="accent-color: #3b82f6; width: 18px; height: 18px; cursor: pointer;">
                    </th>
                    <th style="padding: 16px 24px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b;">Date</th>
                    <th style="padding: 16px 24px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b;">Reference/Desc</th>
                    <th style="padding: 16px 24px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b;">Type</th>
                    <th style="padding: 16px 24px; text-align: right; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b;">Amount</th>
                    <th style="padding: 16px 24px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b;">Status</th>
                    <th style="padding: 16px 24px; text-align: right; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b;">Action</th>
                </tr>
            </thead>
            <tbody style="font-size: 14px;">
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="<?= $showReconciled ? 6 : 7 ?>" style="text-align: center; padding: 64px 24px; color: #9ca3af;">
                            <div style="margin-bottom: 16px; background: #f1f5f9; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-left: auto; margin-right: auto;">
                                <svg style="width: 40px; height: 40px; color: #cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                            </div>
                            <h3 style="margin: 0 0 8px; color: #334155; font-weight: 600;">No transactions found</h3>
                            <p style="margin: 0; color: #64748b;">There are no <?= $showReconciled ? 'reconciled' : 'unreconciled' ?> transactions for the selected period.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                        <tr class="tx-row <?= $tx['is_reconciled'] ? 'bg-green-50' : '' ?>" style="border-bottom: 1px solid #f1f5f9; transition: background-color 0.15s;">
                            <td style="padding: 16px 24px; text-align: center;">
                                <input type="checkbox" class="tx-checkbox" value="<?= $tx['transaction_id'] ?>" style="accent-color: #3b82f6; width: 18px; height: 18px; cursor: pointer;">
                            </td>
                            <td style="padding: 16px 24px; color: #334155; font-weight: 500;">
                                <?= date('M d, Y', strtotime($tx['transaction_date'])) ?>
                            </td>
                            <td style="padding: 16px 24px;">
                                <div style="font-weight: 600; color: #1e293b; margin-bottom: 2px;"><?= htmlspecialchars($tx['description'] ?? '-') ?></div>
                                <div style="display: flex; gap: 8px; align-items: center; font-size: 12px;">
                                    <?php if (!empty($tx['reference_number'])): ?>
                                        <span style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; background: #e2e8f0; padding: 2px 6px; border-radius: 4px; color: #475569;">
                                            <?= htmlspecialchars($tx['reference_number']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span style="color: #64748b;"><?= htmlspecialchars($tx['category'] ?? 'Uncategorized') ?></span>
                                </div>
                            </td>
                            <td style="padding: 16px 24px; text-align: center;">
                                <?php if ($tx['type'] === 'in'): ?>
                                    <span style="display: inline-flex; width: 32px; height: 32px; align-items: center; justify-content: center; background: #dcfce7; color: #166534; border-radius: 50%;">
                                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path></svg>
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-flex; width: 32px; height: 32px; align-items: center; justify-content: center; background: #fee2e2; color: #991b1b; border-radius: 50%;">
                                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path></svg>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 16px 24px; text-align: right; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 15px; font-weight: 600;">
                                <span style="color: <?= $tx['type'] === 'in' ? '#059669' : '#dc2626' ?>;">
                                    <?= $tx['type'] === 'in' ? '+' : '-' ?><?= number_format($tx['amount'], 2) ?>
                                </span>
                            </td>
                            <td style="padding: 16px 24px; text-align: center;">
                                <?php if ($tx['is_reconciled']): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; border-radius: 99px; font-size: 12px; font-weight: 600;">
                                        <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        Reconciled
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; border-radius: 99px; font-size: 12px; font-weight: 600;">
                                        Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 16px 24px; text-align: right;">
                                <?php if ($tx['is_reconciled']): ?>
                                    <button onclick="unreconcileTransaction(<?= $tx['transaction_id'] ?>)" 
                                            class="btn btn-sm btn-white" 
                                            title="Undo reconciliation"
                                            style="padding: 6px 12px; color: #64748b; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; font-weight: 500; transition: all 0.2s; background: white;">
                                        Undo
                                    </button>
                                <?php else: ?>
                                    <button onclick="reconcileTransaction(<?= $tx['transaction_id'] ?>)" 
                                            class="btn btn-sm btn-primary"
                                            style="padding: 6px 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 2px rgba(59, 130, 246, 0.3);">
                                        Reconcile
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Select All functionality
const selectAll = document.getElementById('selectAll');
const bulkBtn = document.getElementById('bulkReconcileBtn');
const bulkUnreconcileBtn = document.getElementById('bulkUnreconcileBtn');
const selectedCount = document.getElementById('selectedCount');
const bulkActions = document.querySelector('.bulk-actions');

if (selectAll) {
    selectAll.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.tx-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkState();
    });

    // Individual checkbox change
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('tx-checkbox')) {
            updateBulkState();
            
            // Update Select All state
            const all = document.querySelectorAll('.tx-checkbox');
            const checked = document.querySelectorAll('.tx-checkbox:checked');
            selectAll.checked = all.length === checked.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
        }
    });
}

function updateBulkState() {
    const checked = document.querySelectorAll('.tx-checkbox:checked');
    const count = checked.length;
    
    if (selectedCount) selectedCount.textContent = count + ' selected';
    if (bulkBtn) bulkBtn.disabled = count === 0;
    if (bulkUnreconcileBtn) bulkUnreconcileBtn.disabled = count === 0;
    
    if (bulkActions) {
        if (count > 0) {
            bulkActions.style.opacity = '1';
            bulkActions.style.pointerEvents = 'auto';
        } else {
            bulkActions.style.opacity = '0.5';
            bulkActions.style.pointerEvents = 'none';
        }
    }
}

// Bulk Reconcile
if (bulkBtn) {
    bulkBtn.addEventListener('click', async function() {
        if (!confirm('Reconcile selected transactions?')) return;
        
        const checked = document.querySelectorAll('.tx-checkbox:checked');
        const ids = Array.from(checked).map(cb => cb.value);
        
        try {
            const formData = new FormData();
            formData.append('action', 'bulk_reconcile');
            formData.append('transaction_ids', JSON.stringify(ids));
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.success) {
                window.location.reload();
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to reconcile transactions');
        }
    });
}

// Bulk Unreconcile
if (bulkUnreconcileBtn) {
    bulkUnreconcileBtn.addEventListener('click', async function() {
        if (!confirm('Undo reconciliation for selected transactions?')) return;
        
        const checked = document.querySelectorAll('.tx-checkbox:checked');
        const ids = Array.from(checked).map(cb => cb.value);
        
        try {
            const formData = new FormData();
            formData.append('action', 'bulk_unreconcile');
            formData.append('transaction_ids', JSON.stringify(ids));
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.success) {
                window.location.reload();
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to undo transactions');
        }
    });
}

async function reconcileTransaction(id) {
    try {
        const formData = new FormData();
        formData.append('action', 'reconcile');
        formData.append('transaction_id', id);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.success) {
            window.location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to reconcile transaction');
    }
}

async function unreconcileTransaction(id) {
    if (!confirm('Undo reconciliation for this transaction?')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'unreconcile');
        formData.append('transaction_id', id);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.success) {
            window.location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to unreconcile transaction');
    }
}

// Row hover effect
document.querySelectorAll('.tx-row').forEach(row => {
    row.addEventListener('mouseover', () => row.style.backgroundColor = '#f8fafc');
    row.addEventListener('mouseout', () => {
        if (!row.classList.contains('bg-green-50')) {
            row.style.backgroundColor = '';
        }
    });
});
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>
