<?php
/**
 * AR Dashboard
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Invoice.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();

$pageTitle = 'Accounts Receivable Dashboard';

$companyId = (int)($_GET['company'] ?? getCurrentCompanyId());
requireCompanyAccess($companyId);

$company = Company::getById($companyId);

// Update overdue invoices
Invoice::updateOverdueStatus($companyId);

// Get all invoices
$invoices = Invoice::getByCompany($companyId);

// Calculate summary
$summary = [
    'total_outstanding' => 0,
    'current' => 0,
    'overdue' => 0,
    'count_sent' => 0,
    'count_overdue' => 0,
    'count_partial' => 0
];

foreach ($invoices as $inv) {
    if (in_array($inv['status'], ['sent', 'partial', 'overdue'])) {
        $summary['total_outstanding'] += $inv['amount_due'];
        
        if ($inv['status'] == 'sent') {
            $summary['count_sent']++;
            if ($inv['due_date'] >= date('Y-m-d')) {
                $summary['current'] += $inv['amount_due'];
            }
        } elseif ($inv['status'] == 'overdue') {
            $summary['count_overdue']++;
            $summary['overdue'] += $inv['amount_due'];
        } elseif ($inv['status'] == 'partial') {
            $summary['count_partial']++;
            if ($inv['due_date'] < date('Y-m-d')) {
                $summary['overdue'] += $inv['amount_due'];
            } else {
                $summary['current'] += $inv['amount_due'];
            }
        }
    }
}

// Get aging report
$agingReport = Invoice::getAgingReport($companyId);

include __DIR__ . '/../views/header.php';
?>

<div class="page-header">
    <h1>
        <svg style="width: 28px; height: 28px; vertical-align: middle; margin-right: 10px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
        </svg>
        AR Dashboard - <?= htmlspecialchars($company['name']) ?>
    </h1>
    <div>
        <a href="<?= WEB_ROOT ?>/invoices/create.php?company=<?= $companyId ?>" class="btn btn-success">+ New Invoice</a>
        <a href="<?= WEB_ROOT ?>/customers/list.php?company=<?= $companyId ?>" class="btn btn-primary">Customers</a>
        <a href="<?= WEB_ROOT ?>/index.php?company=<?= $companyId ?>" class="btn btn-secondary">← Dashboard</a>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
    <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
        <h3 style="font-size: 14px; font-weight: 600; opacity: 0.95; margin-bottom: 10px;">Total Outstanding</h3>
        <div class="stat-value" style="font-size: 32px; font-weight: 700; margin-bottom: 8px;"><?= formatMoney($summary['total_outstanding']) ?></div>
        <div class="stat-detail" style="font-size: 14px; opacity: 0.9;"><?= $summary['count_sent'] + $summary['count_partial'] + $summary['count_overdue'] ?> unpaid invoices</div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
        <h3 style="font-size: 14px; font-weight: 600; opacity: 0.95; margin-bottom: 10px;">Overdue</h3>
        <div class="stat-value" style="font-size: 32px; font-weight: 700; margin-bottom: 8px;"><?= formatMoney($summary['overdue']) ?></div>
        <div class="stat-detail" style="font-size: 14px; opacity: 0.9;"><?= $summary['count_overdue'] ?> overdue invoices</div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
        <h3 style="font-size: 14px; font-weight: 600; opacity: 0.95; margin-bottom: 10px;">Current (Not Due)</h3>
        <div class="stat-value" style="font-size: 32px; font-weight: 700; margin-bottom: 8px;"><?= formatMoney($summary['current']) ?></div>
        <div class="stat-detail" style="font-size: 14px; opacity: 0.9;"><?= $summary['count_sent'] ?> sent invoices</div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
        <h3 style="font-size: 14px; font-weight: 600; opacity: 0.95; margin-bottom: 10px;">Partial Payments</h3>
        <div class="stat-value" style="font-size: 32px; font-weight: 700; margin-bottom: 8px;"><?= $summary['count_partial'] ?></div>
        <div class="stat-detail" style="font-size: 14px; opacity: 0.9;">Invoices with partial payment</div>
    </div>
</div>

<!-- Aging Report -->
<h2 style="margin-top: 40px; margin-bottom: 20px;">Accounts Receivable Aging</h2>

<?php if (!empty($agingReport)): ?>
    <div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Current</th>
                <th>1-30 Days</th>
                <th>31-60 Days</th>
                <th>61-90 Days</th>
                <th>Over 90 Days</th>
                <th>Total Due</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totals = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, 'over_90' => 0, 'total' => 0];
            foreach ($agingReport as $row): 
                $totals['current'] += $row['current_amount'];
                $totals['1_30'] += $row['days_1_30'];
                $totals['31_60'] += $row['days_31_60'];
                $totals['61_90'] += $row['days_61_90'];
                $totals['over_90'] += $row['days_over_90'];
                $totals['total'] += $row['total_due'];
            ?>
                <tr>
                    <td style="font-weight: 600;">
                        <a href="<?= WEB_ROOT ?>/customers/view.php?id=<?= $row['customer_id'] ?>&company=<?= $companyId ?>" 
                           style="color: var(--primary-color); text-decoration: none;">
                            <?= htmlspecialchars($row['customer_name']) ?>
                        </a>
                    </td>
                    <td class="amount"><?= formatMoney($row['current_amount']) ?></td>
                    <td class="amount" style="<?= $row['days_1_30'] > 0 ? 'background-color: #fff3cd;' : '' ?>">
                        <?= formatMoney($row['days_1_30']) ?>
                    </td>
                    <td class="amount" style="<?= $row['days_31_60'] > 0 ? 'background-color: #ffe4b5;' : '' ?>">
                        <?= formatMoney($row['days_31_60']) ?>
                    </td>
                    <td class="amount" style="<?= $row['days_61_90'] > 0 ? 'background-color: #ffcccb;' : '' ?>">
                        <?= formatMoney($row['days_61_90']) ?>
                    </td>
                    <td class="amount" style="<?= $row['days_over_90'] > 0 ? 'background-color: #ff9999;' : '' ?>">
                        <?= formatMoney($row['days_over_90']) ?>
                    </td>
                    <td class="amount out" style="font-weight: 600;">
                        <?= formatMoney($row['total_due']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot style="background-color: #f8f9fa; font-weight: 600;">
            <tr>
                <td>TOTALS</td>
                <td class="amount"><?= formatMoney($totals['current']) ?></td>
                <td class="amount"><?= formatMoney($totals['1_30']) ?></td>
                <td class="amount"><?= formatMoney($totals['31_60']) ?></td>
                <td class="amount"><?= formatMoney($totals['61_90']) ?></td>
                <td class="amount"><?= formatMoney($totals['over_90']) ?></td>
                <td class="amount out"><?= formatMoney($totals['total']) ?></td>
            </tr>
        </tfoot>
    </table>
    </div>
<?php else: ?>
    <div class="empty-state">
        <p>No outstanding invoices.</p>
    </div>
<?php endif; ?>

<!-- Recent Invoices -->
<h2 style="margin-top: 40px; margin-bottom: 20px;">Recent Invoices</h2>

<a href="<?= WEB_ROOT ?>/invoices/list.php?company=<?= $companyId ?>" class="btn btn-primary" style="margin-bottom: 20px;">View All Invoices</a>

<?php 
$recentInvoices = array_slice($invoices, 0, 10);
if (!empty($recentInvoices)): 
?>
    <div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Due Date</th>
                <th>Amount</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentInvoices as $inv): ?>
                <tr>
                    <td style="font-weight: 600;">
                        <a href="<?= WEB_ROOT ?>/invoices/view.php?id=<?= $inv['invoice_id'] ?>&company=<?= $companyId ?>" 
                           style="color: var(--primary-color); text-decoration: none;">
                            <?= htmlspecialchars($inv['invoice_number']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($inv['customer_name']) ?></td>
                    <td><?= formatDate($inv['invoice_date'], 'M d, Y') ?></td>
                    <td><?= formatDate($inv['due_date'], 'M d, Y') ?></td>
                    <td class="amount"><?= formatMoney($inv['total_amount']) ?></td>
                    <td class="amount in"><?= formatMoney($inv['amount_paid']) ?></td>
                    <td class="amount <?= $inv['amount_due'] > 0 ? 'out' : '' ?>">
                        <?= formatMoney($inv['amount_due']) ?>
                    </td>
                    <td>
                        <?php
                        $badges = [
                            'draft' => 'secondary',
                            'sent' => 'info',
                            'partial' => 'warning',
                            'paid' => 'success',
                            'overdue' => 'danger',
                            'cancelled' => 'secondary'
                        ];
                        $badgeClass = $badges[$inv['status']] ?? 'secondary';
                        ?>
                        <span class="badge badge-<?= $badgeClass ?>"><?= ucfirst($inv['status']) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../views/footer.php'; ?>
