<?php
/**
 * Customers List
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();

$pageTitle = 'Clients';

$companyId = (int)($_GET['company'] ?? getCurrentCompanyId());
requireCompanyAccess($companyId);

$company = Company::getById($companyId);
$customers = Customer::getByCompany($companyId, false); // Get all including inactive

include __DIR__ . '/../views/header.php';
?>

<div class="page-header">
    <h1>
        <svg style="width: 28px; height: 28px; vertical-align: middle; margin-right: 10px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>
        Clients - <?= htmlspecialchars($company['name']) ?>
    </h1>
    <div>
        <a href="/sales/customers/create.php?company=<?= $companyId ?>" class="btn btn-success">+ Add Client</a>
        <a href="/sales/ar/dashboard.php?company=<?= $companyId ?>" class="btn btn-primary">AR Dashboard</a>
        <a href="/sales/index.php?company=<?= $companyId ?>" class="btn btn-secondary">← Dashboard</a>
    </div>
</div>

<?php if (!empty($customers)): ?>
    <div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Client Name</th>
                <th>Contact Person</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Payment Terms</th>
                <th>Outstanding</th>
                <th>Invoices</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($customers as $customer): ?>
                <tr>
                    <td style="font-weight: 600;">
                        <a href="/sales/customers/view.php?id=<?= $customer['customer_id'] ?>&company=<?= $companyId ?>" 
                           style="color: var(--primary-color); text-decoration: none;">
                            <?= htmlspecialchars($customer['customer_name']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($customer['contact_person'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($customer['email'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($customer['phone'] ?? '-') ?></td>
                    <td>Net <?= $customer['payment_terms'] ?> days</td>
                    <td class="amount <?= $customer['total_outstanding'] > 0 ? 'out' : '' ?>">
                        <?= formatMoney($customer['total_outstanding']) ?>
                    </td>
                    <td><?= $customer['invoice_count'] ?> invoices</td>
                    <td>
                        <?php if ($customer['is_active']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/sales/customers/edit.php?id=<?= $customer['customer_id'] ?>&company=<?= $companyId ?>" 
                           class="btn btn-sm btn-primary">Edit</a>
                        <a href="/sales/invoices/create.php?customer_id=<?= $customer['customer_id'] ?>&company=<?= $companyId ?>" 
                           class="btn btn-sm btn-success">+ Invoice</a>
                        <a href="/sales/customers/delete.php?id=<?= $customer['customer_id'] ?>&company=<?= $companyId ?>" 
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Are you sure you want to delete this client? This action cannot be undone.');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php else: ?>
    <div class="empty-state">
        <p>No clients found.</p>
        <a href="/sales/customers/create.php?company=<?= $companyId ?>" class="btn btn-primary">Add First Client</a>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../views/footer.php'; ?>
