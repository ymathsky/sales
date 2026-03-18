<?php
/**
 * Reports Page
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/Transaction.php';

requireLogin();

$pageTitle = 'Financial Reports';

$companyId = (int)($_GET['company'] ?? getCurrentCompanyId());
requireCompanyAccess($companyId);

$company = Company::getById($companyId);

// Default to current month
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Get summary data
$balance = Company::getBalance($companyId, $startDate, $endDate);
$summary = Transaction::getSummary($companyId, $startDate, $endDate);

// Group by category
$categoryTotals = [];
foreach ($summary as $row) {
    $category = $row['category'] ?? 'Uncategorized';
    if (!isset($categoryTotals[$category])) {
        $categoryTotals[$category] = ['in' => 0, 'out' => 0];
    }
    if ($row['type'] === 'in') {
        $categoryTotals[$category]['in'] += $row['total'];
    } else {
        $categoryTotals[$category]['out'] += $row['total'];
    }
}

include __DIR__ . '/../views/header.php';
?>

<div class="page-header">
    <h1>Financial Reports - <?= htmlspecialchars($company['name']) ?></h1>
    <a href="<?= WEB_ROOT ?>/index.php?company=<?= $companyId ?>" class="btn btn-secondary">← Dashboard</a>
</div>

<div class="date-filter">
    <form method="GET" class="inline-form">
        <input type="hidden" name="company" value="<?= $companyId ?>">
        <label>From:</label>
        <input type="date" name="start_date" value="<?= $startDate ?>" required>
        <label>To:</label>
        <input type="date" name="end_date" value="<?= $endDate ?>" required>
        <button type="submit" class="btn btn-primary">Generate Report</button>
    </form>
</div>

<div class="report-container">
    <h2>Summary for <?= formatDate($startDate, 'M d, Y') ?> to <?= formatDate($endDate, 'M d, Y') ?></h2>
    
    <div class="stats-grid">
        <div class="stat-card cash-in">
            <h3>Total Cash In</h3>
            <div class="stat-value"><?= formatMoney($balance['total_in'] ?? 0) ?></div>
            <div class="stat-detail"><?= $balance['count_in'] ?? 0 ?> transactions</div>
        </div>
        
        <div class="stat-card cash-out">
            <h3>Total Cash Out</h3>
            <div class="stat-value"><?= formatMoney($balance['total_out'] ?? 0) ?></div>
            <div class="stat-detail"><?= $balance['count_out'] ?? 0 ?> transactions</div>
        </div>
        
        <div class="stat-card balance <?= ($balance['balance'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">
            <h3>Net Balance</h3>
            <div class="stat-value"><?= formatMoney($balance['balance'] ?? 0) ?></div>
        </div>
    </div>
    
    <?php if (!empty($categoryTotals)): ?>
        <h3>Breakdown by Category</h3>
        <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Cash In</th>
                    <th>Cash Out</th>
                    <th>Net</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categoryTotals as $category => $totals): ?>
                    <tr>
                        <td><?= htmlspecialchars($category) ?></td>
                        <td class="amount in"><?= formatMoney($totals['in']) ?></td>
                        <td class="amount out"><?= formatMoney($totals['out']) ?></td>
                        <td class="amount <?= ($totals['in'] - $totals['out']) >= 0 ? 'in' : 'out' ?>">
                            <?= formatMoney($totals['in'] - $totals['out']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </div>
        </table>
    <?php endif; ?>
    
    <div class="report-actions">
        <button onclick="window.print()" class="btn btn-primary">Print Report</button>
        <a href="<?= WEB_ROOT ?>/transactions/list.php?company=<?= $companyId ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" 
           class="btn btn-secondary">View Detailed Transactions</a>
    </div>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
