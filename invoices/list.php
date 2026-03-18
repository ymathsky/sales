<?php
/**
 * Invoice List
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Invoice.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();

$pageTitle = 'Invoices';

$companyId = (int)($_GET['company'] ?? getCurrentCompanyId());
requireCompanyAccess($companyId);

$company = Company::getById($companyId);

// Update overdue invoices
Invoice::updateOverdueStatus($companyId);

// Filters
$statusFilter = $_GET['status'] ?? '';
$customerFilter = $_GET['customer'] ?? '';

$invoices = Invoice::getByCompany($companyId);
$customers = Customer::getByCompany($companyId, true); // Active only

// Apply filters
if ($statusFilter) {
    $invoices = array_filter($invoices, function($inv) use ($statusFilter) {
        return $inv['status'] == $statusFilter;
    });
}

if ($customerFilter) {
    $invoices = array_filter($invoices, function($inv) use ($customerFilter) {
        return $inv['customer_id'] == $customerFilter;
    });
}

// Calculate totals
$totalAmount = array_sum(array_column($invoices, 'total_amount'));
$totalPaid = array_sum(array_column($invoices, 'amount_paid'));
$totalDue = array_sum(array_column($invoices, 'amount_due'));

include __DIR__ . '/../views/header.php';
?>

<div class="page-header">
    <h1>
        <svg style="width: 28px; height: 28px; vertical-align: middle; margin-right: 10px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        Invoices - <?= htmlspecialchars($company['name']) ?>
    </h1>
    <div>
        <a href="/sales/invoices/create.php?company=<?= $companyId ?>" class="btn btn-success">+ New Invoice</a>
        <a href="/sales/ar/dashboard.php?company=<?= $companyId ?>" class="btn btn-primary">AR Dashboard</a>
    </div>
</div>

<!-- Filters -->
<div class="filters-bar" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <form method="GET" action="" class="inline-form" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="company" value="<?= $companyId ?>">
        
        <div style="flex: 1; min-width: 200px;">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="">All Status</option>
                <option value="draft" <?= $statusFilter == 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="sent" <?= $statusFilter == 'sent' ? 'selected' : '' ?>>Sent</option>
                <option value="partial" <?= $statusFilter == 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="paid" <?= $statusFilter == 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="overdue" <?= $statusFilter == 'overdue' ? 'selected' : '' ?>>Overdue</option>
                <option value="cancelled" <?= $statusFilter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        
        <div style="flex: 1; min-width: 200px;">
            <label>Customer</label>
            <select name="customer" class="form-control">
                <option value="">All Customers</option>
                <?php foreach ($customers as $cust): ?>
                    <option value="<?= $cust['customer_id'] ?>" <?= $customerFilter == $cust['customer_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cust['customer_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($statusFilter || $customerFilter): ?>
                <a href="/sales/invoices/list.php?company=<?= $companyId ?>" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Summary -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 25px;">
    <div class="stat-card">
        <h3>Total Amount</h3>
        <div class="stat-value"><?= formatMoney($totalAmount) ?></div>
    </div>
    <div class="stat-card">
        <h3>Total Paid</h3>
        <div class="stat-value in"><?= formatMoney($totalPaid) ?></div>
    </div>
    <div class="stat-card">
        <h3>Total Due</h3>
        <div class="stat-value out"><?= formatMoney($totalDue) ?></div>
    </div>
</div>

<!-- Invoices Table -->
<?php if (!empty($invoices)): ?>
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
                <th>Balance Due</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td style="font-weight: 600;">
                        <a href="/sales/invoices/view.php?id=<?= $inv['invoice_id'] ?>&company=<?= $companyId ?>" 
                           style="color: var(--primary-color); text-decoration: none;">
                            <?= htmlspecialchars($inv['invoice_number']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($inv['customer_name']) ?></td>
                    <td><?= formatDate($inv['invoice_date'], 'M d, Y') ?></td>
                    <td>
                        <?= formatDate($inv['due_date'], 'M d, Y') ?>
                        <?php if ($inv['status'] == 'overdue'): ?>
                            <span style="color: var(--danger-color); font-size: 0.85em; display: block;">
                                <?php
                                $daysOverdue = (strtotime(date('Y-m-d')) - strtotime($inv['due_date'])) / 86400;
                                echo floor($daysOverdue) . ' days overdue';
                                ?>
                            </span>
                        <?php endif; ?>
                    </td>
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
                    <td class="actions">
                        <a href="/sales/invoices/view.php?id=<?= $inv['invoice_id'] ?>&company=<?= $companyId ?>" 
                           class="btn btn-sm btn-primary">View</a>
                        <?php if ($inv['status'] == 'draft'): ?>
                            <a href="/sales/invoices/edit.php?id=<?= $inv['invoice_id'] ?>&company=<?= $companyId ?>" 
                               class="btn btn-sm btn-secondary">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php else: ?>
    <div class="empty-state">
        <svg style="width: 80px; height: 80px; margin-bottom: 20px; opacity: 0.3;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3>No invoices found</h3>
        <p>Get started by creating your first invoice.</p>
        <a href="/sales/invoices/create.php?company=<?= $companyId ?>" class="btn btn-success">+ Create Invoice</a>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../views/footer.php'; ?>
