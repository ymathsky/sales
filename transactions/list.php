<?php
/**
 * List All Transactions
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();

$pageTitle = 'All Transactions';

$companyId = getCurrentCompanyId();
requireCompanyAccess($companyId);

$company = Company::getById($companyId);

// Build filters
$filters = [];
if (!empty($_GET['type'])) {
    $filters['type'] = $_GET['type'];
}
if (!empty($_GET['start_date'])) {
    $filters['start_date'] = $_GET['start_date'];
}
if (!empty($_GET['end_date'])) {
    $filters['end_date'] = $_GET['end_date'];
}
if (!empty($_GET['category'])) {
    $filters['category'] = $_GET['category'];
}
if (!empty($_GET['account'])) {
    $filters['account'] = $_GET['account'];
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

$transactions = Transaction::getByCompany($companyId, $filters, 10000);
$categories = Transaction::getCategories($companyId);

// Check if user has access to multiple companies (for move functionality)
$userId = getCurrentUserId();
$userCompanies = Company::getByUser($userId);
$hasMultipleCompanies = count($userCompanies) > 1;

include __DIR__ . '/../views/header.php';
?>

<div class="page-header no-print" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 24px;">
    <h1 style="color: white; margin: 0 0 16px 0; font-size: 28px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">Transactions - <?= htmlspecialchars($company['name']) ?></h1>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="<?= WEB_ROOT ?>/transactions/export.php?company=<?= $companyId ?><?= !empty($_GET['type']) ? '&type=' . urlencode($_GET['type']) : '' ?><?= !empty($_GET['account']) ? '&account=' . urlencode($_GET['account']) : '' ?><?= !empty($_GET['start_date']) ? '&start_date=' . urlencode($_GET['start_date']) : '' ?><?= !empty($_GET['end_date']) ? '&end_date=' . urlencode($_GET['end_date']) : '' ?><?= !empty($_GET['category']) ? '&category=' . urlencode($_GET['category']) : '' ?><?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
           style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 8px rgba(16,185,129,0.3); transition: all 0.3s; border: none;" 
           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(16,185,129,0.4)';" 
           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(16,185,129,0.3)';">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Export Excel
        </a>
        <button onclick="window.print()" 
                style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 12px 20px; border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 8px rgba(59,130,246,0.3); transition: all 0.3s; border: none; cursor: pointer;" 
                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(59,130,246,0.4)';" 
                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(59,130,246,0.3)';">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Print
        </button>
        <a href="<?= WEB_ROOT ?>/transactions/create.php?company=<?= $companyId ?>" 
           style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 8px rgba(16,185,129,0.3); transition: all 0.3s;" 
           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(16,185,129,0.4)';" 
           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(16,185,129,0.3)';">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Transaction
        </a>
        <a href="<?= WEB_ROOT ?>/index.php?company=<?= $companyId ?>" 
           style="background: rgba(255,255,255,0.2); color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; border: 2px solid rgba(255,255,255,0.3); backdrop-filter: blur(10px); transition: all 0.3s;" 
           onmouseover="this.style.background='rgba(255,255,255,0.3)'; this.style.transform='translateY(-2px)';" 
           onmouseout="this.style.background='rgba(255,255,255,0.2)'; this.style.transform='translateY(0)';">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Dashboard
        </a>
    </div>
</div>

<h2 class="print-only">Transactions - <?= htmlspecialchars($company['name']) ?></h2>

<div class="filters-panel no-print" style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 24px;">
    <!-- Quick Filter Buttons -->
    <div style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
        <a href="?company=<?= $companyId ?>" 
           style="<?= empty($filters['type']) ? 'background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; box-shadow: 0 2px 8px rgba(59,130,246,0.3);' : 'background: white; color: #6b7280; border: 2px solid #e5e7eb;' ?> padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s;" 
           onmouseover="if(!this.href.includes('company=<?= $companyId ?>&type')) this.style.borderColor='#3b82f6'; this.style.transform='translateY(-2px)';" 
           onmouseout="if(!this.href.includes('company=<?= $companyId ?>&type')) this.style.borderColor='#e5e7eb'; this.style.transform='translateY(0)';">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            All Transactions
        </a>
        <a href="?company=<?= $companyId ?>&type=in<?= !empty($filters['start_date']) ? '&start_date=' . urlencode($filters['start_date']) : '' ?><?= !empty($filters['end_date']) ? '&end_date=' . urlencode($filters['end_date']) : '' ?><?= !empty($filters['category']) ? '&category=' . urlencode($filters['category']) : '' ?><?= !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : '' ?>" 
           style="<?= ($filters['type'] ?? '') === 'in' ? 'background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; box-shadow: 0 2px 8px rgba(16,185,129,0.3);' : 'background: white; color: #6b7280; border: 2px solid #e5e7eb;' ?> padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s;" 
           onmouseover="this.style.transform='translateY(-2px)'; if('<?= ($filters['type'] ?? '') ?>' !== 'in') this.style.borderColor='#10b981';" 
           onmouseout="this.style.transform='translateY(0)'; if('<?= ($filters['type'] ?? '') ?>' !== 'in') this.style.borderColor='#e5e7eb';">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
            </svg>
            Cash In Only
        </a>
        <a href="?company=<?= $companyId ?>&type=out<?= !empty($filters['start_date']) ? '&start_date=' . urlencode($filters['start_date']) : '' ?><?= !empty($filters['end_date']) ? '&end_date=' . urlencode($filters['end_date']) : '' ?><?= !empty($filters['category']) ? '&category=' . urlencode($filters['category']) : '' ?><?= !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : '' ?>" 
           style="<?= ($filters['type'] ?? '') === 'out' ? 'background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; box-shadow: 0 2px 8px rgba(239,68,68,0.3);' : 'background: white; color: #6b7280; border: 2px solid #e5e7eb;' ?> padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s;" 
           onmouseover="this.style.transform='translateY(-2px)'; if('<?= ($filters['type'] ?? '') ?>' !== 'out') this.style.borderColor='#ef4444';" 
           onmouseout="this.style.transform='translateY(0)'; if('<?= ($filters['type'] ?? '') ?>' !== 'out') this.style.borderColor='#e5e7eb';">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
            </svg>
            Cash Out Only
        </a>
        <a href="?company=<?= $companyId ?>&account=bank<?= !empty($filters['start_date']) ? '&start_date=' . urlencode($filters['start_date']) : '' ?><?= !empty($filters['end_date']) ? '&end_date=' . urlencode($filters['end_date']) : '' ?><?= !empty($filters['category']) ? '&category=' . urlencode($filters['category']) : '' ?><?= !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : '' ?>" 
           style="<?= ($filters['account'] ?? '') === 'bank' ? 'background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; box-shadow: 0 2px 8px rgba(139,92,246,0.3);' : 'background: white; color: #6b7280; border: 2px solid #e5e7eb;' ?> padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s;" 
           onmouseover="this.style.transform='translateY(-2px)'; if('<?= ($filters['account'] ?? '') ?>' !== 'bank') this.style.borderColor='#8b5cf6';" 
           onmouseout="this.style.transform='translateY(0)'; if('<?= ($filters['account'] ?? '') ?>' !== 'bank') this.style.borderColor='#e5e7eb';">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path>
            </svg>
            Bank Only
        </a>
        <a href="?company=<?= $companyId ?>&account=cash<?= !empty($filters['start_date']) ? '&start_date=' . urlencode($filters['start_date']) : '' ?><?= !empty($filters['end_date']) ? '&end_date=' . urlencode($filters['end_date']) : '' ?><?= !empty($filters['category']) ? '&category=' . urlencode($filters['category']) : '' ?><?= !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : '' ?>" 
           style="<?= ($filters['account'] ?? '') === 'cash' ? 'background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; box-shadow: 0 2px 8px rgba(245,158,11,0.3);' : 'background: white; color: #6b7280; border: 2px solid #e5e7eb;' ?> padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s;" 
           onmouseover="this.style.transform='translateY(-2px)'; if('<?= ($filters['account'] ?? '') ?>' !== 'cash') this.style.borderColor='#f59e0b';" 
           onmouseout="this.style.transform='translateY(0)'; if('<?= ($filters['account'] ?? '') ?>' !== 'cash') this.style.borderColor='#e5e7eb';">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            Cash Only
        </a>
    </div>

    <!-- Date Range Quick Filters -->
    <div style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); padding: 16px; border-radius: 10px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span style="font-weight: 700; color: #374151; display: inline-flex; align-items: center; gap: 6px;">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Quick Dates:
            </span>
            <a href="?company=<?= $companyId ?>&start_date=<?= date('Y-m-d') ?>&end_date=<?= date('Y-m-d') ?><?= !empty($filters['type']) ? '&type=' . urlencode($filters['type']) : '' ?><?= !empty($filters['account']) ? '&account=' . urlencode($filters['account']) : '' ?><?= !empty($filters['category']) ? '&category=' . urlencode($filters['category']) : '' ?>" 
               style="background: white; color: #374151; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s; border: 1px solid transparent;" 
               onmouseover="this.style.borderColor='#3b82f6'; this.style.color='#3b82f6'; this.style.transform='translateY(-2px)';" 
               onmouseout="this.style.borderColor='transparent'; this.style.color='#374151'; this.style.transform='translateY(0)';">Today</a>
            <a href="?company=<?= $companyId ?>&start_date=<?= date('Y-m-d', strtotime('monday this week')) ?>&end_date=<?= date('Y-m-d', strtotime('sunday this week')) ?><?= !empty($filters['type']) ? '&type=' . urlencode($filters['type']) : '' ?><?= !empty($filters['account']) ? '&account=' . urlencode($filters['account']) : '' ?><?= !empty($filters['category']) ? '&category=' . urlencode($filters['category']) : '' ?>" 
               style="background: white; color: #374151; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s; border: 1px solid transparent;" 
               onmouseover="this.style.borderColor='#3b82f6'; this.style.color='#3b82f6'; this.style.transform='translateY(-2px)';" 
               onmouseout="this.style.borderColor='transparent'; this.style.color='#374151'; this.style.transform='translateY(0)';">This Week</a>
            <a href="?company=<?= $companyId ?>&start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-t') ?><?= !empty($filters['type']) ? '&type=' . urlencode($filters['type']) : '' ?><?= !empty($filters['account']) ? '&account=' . urlencode($filters['account']) : '' ?><?= !empty($filters['category']) ? '&category=' . urlencode($filters['category']) : '' ?>" 
               style="background: white; color: #374151; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s; border: 1px solid transparent;" 
               onmouseover="this.style.borderColor='#3b82f6'; this.style.color='#3b82f6'; this.style.transform='translateY(-2px)';" 
               onmouseout="this.style.borderColor='transparent'; this.style.color='#374151'; this.style.transform='translateY(0)';">This Month</a>
            <a href="?company=<?= $companyId ?>&start_date=<?= date('Y-m-01', strtotime('last month')) ?>&end_date=<?= date('Y-m-t', strtotime('last month')) ?><?= !empty($filters['type']) ? '&type=' . urlencode($filters['type']) : '' ?><?= !empty($filters['account']) ? '&account=' . urlencode($filters['account']) : '' ?><?= !empty($filters['category']) ? '&category=' . urlencode($filters['category']) : '' ?>" 
               style="background: white; color: #374151; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s; border: 1px solid transparent;" 
               onmouseover="this.style.borderColor='#3b82f6'; this.style.color='#3b82f6'; this.style.transform='translateY(-2px)';" 
               onmouseout="this.style.borderColor='transparent'; this.style.color='#374151'; this.style.transform='translateY(0)';">Last Month</a>
            <a href="?company=<?= $companyId ?>&start_date=<?= date('Y') . '-01-01' ?>&end_date=<?= date('Y') . '-12-31' ?><?= !empty($filters['type']) ? '&type=' . urlencode($filters['type']) : '' ?><?= !empty($filters['account']) ? '&account=' . urlencode($filters['account']) : '' ?><?= !empty($filters['category']) ? '&category=' . urlencode($filters['category']) : '' ?>" 
               style="background: white; color: #374151; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s; border: 1px solid transparent;" 
               onmouseover="this.style.borderColor='#3b82f6'; this.style.color='#3b82f6'; this.style.transform='translateY(-2px)';" 
               onmouseout="this.style.borderColor='transparent'; this.style.color='#374151'; this.style.transform='translateY(0)';">This Year</a>
            <a href="?company=<?= $companyId ?>&start_date=<?= (date('Y') - 1) . '-01-01' ?>&end_date=<?= (date('Y') - 1) . '-12-31' ?><?= !empty($filters['type']) ? '&type=' . urlencode($filters['type']) : '' ?><?= !empty($filters['account']) ? '&account=' . urlencode($filters['account']) : '' ?><?= !empty($filters['category']) ? '&category=' . urlencode($filters['category']) : '' ?>" 
               style="background: white; color: #374151; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s; border: 1px solid transparent;" 
               onmouseover="this.style.borderColor='#3b82f6'; this.style.color='#3b82f6'; this.style.transform='translateY(-2px)';" 
               onmouseout="this.style.borderColor='transparent'; this.style.color='#374151'; this.style.transform='translateY(0)';">Last Year</a>
        </div>
    </div>

    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: end;">
        <input type="hidden" name="company" value="<?= $companyId ?>">
        
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 13px; font-weight: 600; color: #374151;">Type:</label>
            <select name="type" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; background: white;">
                <option value="">All</option>
                <option value="in" <?= ($filters['type'] ?? '') === 'in' ? 'selected' : '' ?>>Cash In</option>
                <option value="out" <?= ($filters['type'] ?? '') === 'out' ? 'selected' : '' ?>>Cash Out</option>
            </select>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 13px; font-weight: 600; color: #374151;">From:</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 13px; font-weight: 600; color: #374151;">To:</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 13px; font-weight: 600; color: #374151;">Category:</label>
            <select name="category" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; background: white;">
                <option value="">All</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" 
                            <?= ($filters['category'] ?? '') === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 13px; font-weight: 600; color: #374151;">Search:</label>
            <input type="text" name="search" placeholder="Description or reference" 
                   value="<?= htmlspecialchars($filters['search'] ?? '') ?>" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
        </div>
        
        <button type="submit" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 9px 20px; border-radius: 6px; font-weight: 600; border: none; cursor: pointer; font-size: 14px; transition: all 0.3s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">Filter</button>
        <a href="?company=<?= $companyId ?>" style="background: #6b7280; color: white; padding: 9px 20px; border-radius: 6px; font-weight: 600; text-decoration: none; text-align: center; font-size: 14px; transition: all 0.3s; display: block;" onmouseover="this.style.background='#4b5563'" onmouseout="this.style.background='#6b7280'">Clear</a>
    </form>
</div>

<?php 
// Calculate stats for Print Dashboard
$printStart = $filters['start_date'] ?? null;
$printEnd = $filters['end_date'] ?? null;

// Calculate totals from filtered transactions (for both print and table)
$totalIn = 0;
$totalOut = 0;
$transactionCount = 0;
foreach ($transactions as $trans) {
    if ($trans['type'] === 'in') {
        $totalIn += $trans['amount'];
    } else {
        $totalOut += $trans['amount'];
    }
    $transactionCount++;
}

// Global Stats (Cash vs Bank) - Must match dashboard calculation
$cashBalanceData = Company::getBookBalance($companyId, null, 'cash');
$bankBalanceData = Company::getBookBalance($companyId, null, 'bank');

$cashHand = $cashBalanceData['book_balance'];
$bankBal = $bankBalanceData['book_balance'];
?>

<!-- Print Only Dashboard Summary -->
<div class="print-only" style="display: none; margin-bottom: 24px; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
    <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px;">
        <!-- Cash In -->
        <div style="border: 1px solid #10b981; padding: 12px; border-radius: 8px; border-left: 4px solid #10b981; background: #d1fae5; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
            <div style="font-size: 9px; color: #065f46; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px;">Cash In</div>
            <div style="font-size: 13pt; font-weight: 700; color: #065f46;"><?= formatMoney($totalIn) ?></div>
            <div style="font-size: 8pt; color: #065f46; margin-top: 2px;">Filtered period</div>
        </div>

        <!-- Cash Out -->
        <div style="border: 1px solid #ef4444; padding: 12px; border-radius: 8px; border-left: 4px solid #ef4444; background: #fee2e2; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
            <div style="font-size: 9px; color: #991b1b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px;">Cash Out</div>
            <div style="font-size: 13pt; font-weight: 700; color: #991b1b;"><?= formatMoney($totalOut) ?></div>
            <div style="font-size: 8pt; color: #991b1b; margin-top: 2px;">Filtered period</div>
        </div>
        
        <!-- Net Balance -->
        <div style="border: 1px solid #3b82f6; padding: 12px; border-radius: 8px; border-left: 4px solid #3b82f6; background: #dbeafe; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
            <div style="font-size: 9px; color: #1e40af; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px;">Net Balance</div>
            <div style="font-size: 13pt; font-weight: 700; color: #1e40af;"><?= formatMoney($totalIn - $totalOut) ?></div>
            <div style="font-size: 8pt; color: #1e40af; margin-top: 2px;"><?= $transactionCount ?> transactions</div>
        </div>

        <!-- Cash on Hand -->
        <div style="border: 1px solid #f59e0b; padding: 12px; border-radius: 8px; border-left: 4px solid #f59e0b; background: #fef3c7; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
            <div style="font-size: 9px; color: #92400e; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px;">Cash on Hand</div>
            <div style="font-size: 13pt; font-weight: 700; color: #92400e;"><?= formatMoney($cashHand) ?></div>
            <div style="font-size: 8pt; color: #92400e; margin-top: 2px;">Physical balance</div>
        </div>

        <!-- Bank Balance -->
        <div style="border: 1px solid #3b82f6; padding: 12px; border-radius: 8px; border-left: 4px solid #3b82f6; background: #dbeafe; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
            <div style="font-size: 9px; color: #1e40af; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px;">Bank Balance</div>
            <div style="font-size: 13pt; font-weight: 700; color: #1e40af;"><?= formatMoney($bankBal) ?></div>
            <div style="font-size: 8pt; color: #1e40af; margin-top: 2px;">Bank accounts</div>
        </div>
    </div>
</div>

<?php if (!empty($transactions)): ?>
    <div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Category</th>
                <th>Description</th>
                <th>Reference</th>
                <th>Payment Method</th>
                <th>Amount</th>
                <th>Created By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $trans): ?>
                <tr>
                    <td><?= formatDate($trans['transaction_date'], 'M d, Y') ?></td>
                    <td><?= getTypeBadge($trans['type']) ?></td>
                    <td><?= htmlspecialchars($trans['category'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($trans['description'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($trans['reference_number'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($trans['receipt_path']) || (!empty($trans['receipt_count']) && $trans['receipt_count'] > 0)): ?>
                            <button onclick="viewTransactionReceipts(<?= $trans['transaction_id'] ?>)" 
                                    class="btn btn-sm btn-primary" style="padding: 4px 8px; font-size: 12px;">
                                <svg style="width: 14px; height: 14px; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <?= (!empty($trans['receipt_count']) && $trans['receipt_count'] > 1) ? $trans['receipt_count'] . ' Receipts' : 'Receipt' ?>
                            </button>
                        <?php else: ?>
                            <?= ucfirst(str_replace('_', ' ', $trans['payment_method'])) ?>
                        <?php endif; ?>
                    </td>
                    <td class="amount <?= $trans['type'] ?>">
                        <?= formatMoney($trans['amount']) ?>
                    </td>
                    <td><?= htmlspecialchars($trans['created_by_name'] ?? 'Unknown') ?></td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <a href="<?= WEB_ROOT ?>/transactions/edit.php?id=<?= $trans['transaction_id'] ?>&company=<?= $companyId ?>" 
                               class="btn btn-sm">Edit</a>
                            <?php if ($hasMultipleCompanies): ?>
                                <a href="<?= WEB_ROOT ?>/transactions/move.php?id=<?= $trans['transaction_id'] ?>&company=<?= $companyId ?>" 
                                   class="btn btn-sm" 
                                   style="background: #8b5cf6; color: white; border: none;"
                                   onmouseover="this.style.background='#7c3aed'"
                                   onmouseout="this.style.background='#8b5cf6'">
                                    <svg style="width: 14px; height: 14px; vertical-align: middle; margin-right: 3px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                    </svg>
                                    Move
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td colspan="6"><strong>Totals:</strong></td>
                <td class="amount">
                    <div class="amount in">In: <?= formatMoney($totalIn) ?></div>
                    <div class="amount out">Out: <?= formatMoney($totalOut) ?></div>
                    <div class="amount <?= ($totalIn - $totalOut) >= 0 ? 'in' : 'out' ?>">
                        <strong>Net: <?= formatMoney($totalIn - $totalOut) ?></strong>
                    </div>
                </td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
    
    <!-- Financial Summary (Print Only) -->
    <div class="print-summary" style="display: none; margin-top: 24px; padding: 20px; border: 2px solid #000; border-radius: 8px; background: #f9fafb; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; border-bottom: 2px solid #000; padding-bottom: 8px;">Financial Summary</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 10px 0; font-weight: 600; width: 60%;">Gross Cash In (Total Revenue):</td>
                <td style="padding: 10px 0; text-align: right; font-size: 16px; color: #065f46; font-weight: 700; background: #d1fae5; padding-right: 10px; -webkit-print-color-adjust: exact; print-color-adjust: exact;"><?= formatMoney($totalIn) ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 10px 0; font-weight: 600;">Total Cash Out (Expenses):</td>
                <td style="padding: 10px 0; text-align: right; font-size: 16px; color: #991b1b; font-weight: 700; background: #fee2e2; padding-right: 10px; -webkit-print-color-adjust: exact; print-color-adjust: exact;"><?= formatMoney($totalOut) ?></td>
            </tr>
            <tr style="border-bottom: 2px solid #000; background: #f3f4f6; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                <td style="padding: 12px 0; font-weight: 700; font-size: 16px;">Net Cash on Hand:</td>
                <td style="padding: 12px 0; text-align: right; font-size: 18px; font-weight: 700; color: <?= ($totalIn - $totalOut) >= 0 ? '#065f46' : '#991b1b' ?>; padding-right: 10px;"><?= formatMoney($totalIn - $totalOut) ?></td>
            </tr>
            <?php 
            // Calculate bank balance (assume cash method goes to cash on hand, others to bank)
            $cashOnHand = 0;
            $bankBalance = 0;
            foreach ($transactions as $trans) {
                if ($trans['payment_method'] === 'cash') {
                    if ($trans['type'] === 'in') {
                        $cashOnHand += $trans['amount'];
                    } else {
                        $cashOnHand -= $trans['amount'];
                    }
                } else {
                    if ($trans['type'] === 'in') {
                        $bankBalance += $trans['amount'];
                    } else {
                        $bankBalance -= $trans['amount'];
                    }
                }
            }
            ?>
            <tr>
                <td style="padding: 10px 0; font-weight: 600; padding-left: 20px;">└─ Cash on Hand:</td>
                <td style="padding: 10px 0; text-align: right; font-size: 16px; font-weight: 600;"><?= formatMoney($cashOnHand) ?></td>
            </tr>
            <tr style="background: #f3f4f6;">
                <td style="padding: 12px 0; font-weight: 700; font-size: 16px; padding-left: 20px;">└─ Balance on Bank:</td>
                <td style="padding: 12px 0; text-align: right; font-size: 18px; font-weight: 700; color: <?= $bankBalance >= 0 ? '#3b82f6' : '#ef4444' ?>;"><?= formatMoney($bankBalance) ?></td>
            </tr>
        </table>
        <p style="margin: 16px 0 0 0; font-size: 12px; color: #6b7280; font-style: italic;">* Cash transactions go to Cash on Hand, other payment methods go to Bank Balance</p>
    </div>
    </div>
<?php else: ?>
    <div class="empty-state">
        <p>No transactions found.</p>
        <a href="<?= WEB_ROOT ?>/transactions/create.php?company=<?= $companyId ?>" class="btn btn-primary">Add Transaction</a>
    </div>
<?php endif; ?>

<!-- Receipt Viewer Modal -->
<div id="receiptModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; overflow: auto;">
    <div style="position: relative; max-width: 900px; margin: 50px auto; background: white; border-radius: 12px; padding: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--border-color);">
            <div>
                <h3 id="receiptTitle" style="margin: 0;">Receipts</h3>
                <div id="receiptCounter" style="font-size: 12px; color: #6b7280; margin-top: 4px;"></div>
            </div>
            <button onclick="closeReceiptModal()" style="background: none; border: none; font-size: 28px; cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; color: var(--text-light);">&times;</button>
        </div>
        <div style="padding: 20px; text-align: center; min-height: 400px; position: relative;">
            <div id="receiptLoader" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                <div style="border: 4px solid #f3f4f6; border-top: 4px solid #6366f1; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite;"></div>
            </div>
            <div id="receiptContent"></div>
            
            <!-- Navigation for multiple receipts -->
            <div id="receiptNav" style="display: none; position: absolute; top: 50%; left: 0; right: 0; display: flex; justify-content: space-between; padding: 0 10px; transform: translateY(-50%);">
                <button onclick="prevReceipt()" style="background: rgba(0,0,0,0.5); color: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 20px;">‹</button>
                <button onclick="nextReceipt()" style="background: rgba(0,0,0,0.5); color: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 20px;">›</button>
            </div>
        </div>
        <div style="padding: 20px; border-top: 1px solid var(--border-color); text-align: right;">
            <a id="receiptDownload" href="" download class="btn btn-primary" style="margin-right: 10px;">
                <svg style="width: 16px; height: 16px; vertical-align: middle; margin-right: 5px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                Download
            </a>
            <button onclick="closeReceiptModal()" class="btn btn-secondary">Close</button>
        </div>
    </div>
</div>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
let currentReceipts = [];
let currentReceiptIndex = 0;

function viewTransactionReceipts(transactionId) {
    const modal = document.getElementById('receiptModal');
    const loader = document.getElementById('receiptLoader');
    const content = document.getElementById('receiptContent');
    
    modal.style.display = 'block';
    loader.style.display = 'block';
    content.innerHTML = '';
    document.body.style.overflow = 'hidden';
    
    // Fetch receipts via AJAX
    fetch(`<?= WEB_ROOT ?>/api/get-receipts.php?transaction_id=${transactionId}`)
        .then(response => response.json())
        .then(data => {
            loader.style.display = 'none';
            if (data.success && data.receipts.length > 0) {
                currentReceipts = data.receipts;
                currentReceiptIndex = 0;
                displayCurrentReceipt();
            } else {
                content.innerHTML = '<p style="color: #6b7280;">No receipts found.</p>';
            }
        })
        .catch(error => {
            loader.style.display = 'none';
            content.innerHTML = '<p style="color: #ef4444;">Error loading receipts.</p>';
            console.error('Error:', error);
        });
}

function displayCurrentReceipt() {
    const receipt = currentReceipts[currentReceiptIndex];
    const content = document.getElementById('receiptContent');
    const counter = document.getElementById('receiptCounter');
    const nav = document.getElementById('receiptNav');
    const download = document.getElementById('receiptDownload');
    
    const fullPath = '<?= WEB_ROOT ?>/' + receipt.file_path;
    const isPdf = receipt.file_path.toLowerCase().endsWith('.pdf');
    const displayName = receipt.original_name || receipt.file_path.split('/').pop();
    
    counter.textContent = `${currentReceiptIndex + 1} of ${currentReceipts.length}`;
    download.href = fullPath;
    download.download = displayName;
    
    if (isPdf) {
        content.innerHTML = `<iframe src="${fullPath}" style="width: 100%; height: 500px; border: none; border-radius: 8px;"></iframe>`;
    } else {
        content.innerHTML = `<img src="${fullPath}" alt="Receipt" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">`;
    }
    
    // Show navigation if multiple receipts
    if (currentReceipts.length > 1) {
        nav.style.display = 'flex';
    } else {
        nav.style.display = 'none';
    }
}

function nextReceipt() {
    if (currentReceiptIndex < currentReceipts.length - 1) {
        currentReceiptIndex++;
        displayCurrentReceipt();
    }
}

function prevReceipt() {
    if (currentReceiptIndex > 0) {
        currentReceiptIndex--;
        displayCurrentReceipt();
    }
}

function closeReceiptModal() {
    const modal = document.getElementById('receiptModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('receiptContent').innerHTML = '';
    currentReceipts = [];
    currentReceiptIndex = 0;
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReceiptModal();
    }
});

// Close modal when clicking outside
document.getElementById('receiptModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeReceiptModal();
    }
});
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>
