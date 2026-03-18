<?php
/**
 * List Companies Page
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Company.php';

requireAdmin();

// Get all companies
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT c.company_id, c.name, c.address, c.phone, c.email, c.tax_id, c.status, c.created_at,
               COALESCE(COUNT(DISTINCT uc.user_id), 0) as user_count,
               COALESCE(COUNT(DISTINCT t.transaction_id), 0) as transaction_count
        FROM companies c
        LEFT JOIN user_companies uc ON c.company_id = uc.company_id
        LEFT JOIN transactions t ON c.company_id = t.company_id
        GROUP BY c.company_id, c.name, c.address, c.phone, c.email, c.tax_id, c.status, c.created_at
        ORDER BY c.created_at DESC
    ");
    $companies = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $companies = [];
}

$pageTitle = 'Companies';
include __DIR__ . '/../views/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Companies</h1>
        <div>
            <a href="<?= WEB_ROOT ?>/companies/create.php" class="btn btn-primary">
                <svg style="width: 16px; height: 16px; margin-right: 5px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Add New Company
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Company Name</th>
                    <th>Tax ID</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Users</th>
                    <th>Transactions</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($companies)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            No companies found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($companies as $company): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($company['name']) ?></strong></td>
                            <td><?= htmlspecialchars($company['tax_id'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($company['phone'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($company['email'] ?? '-') ?></td>
                            <td><?= ($company['user_count'] ?? 0) ?> users</td>
                            <td><?= ($company['transaction_count'] ?? 0) ?> transactions</td>
                            <td>
                                <span class="badge <?= $company['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= htmlspecialchars(ucfirst($company['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= WEB_ROOT ?>/companies/edit.php?id=<?= $company['company_id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                <a href="<?= WEB_ROOT ?>/index.php?company=<?= $company['company_id'] ?>" class="btn btn-sm btn-secondary">View Dashboard</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
