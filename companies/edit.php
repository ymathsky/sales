<?php
/**
 * Edit Company Page
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Company.php';

requireAdmin();

$errors = [];
$companyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get company data
$company = Company::getById($companyId);
if (!$company) {
    $_SESSION['flash_message'] = 'Company not found';
    header('Location: ' . WEB_ROOT . '/companies/list.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    if (empty($_POST['name'])) {
        $errors[] = 'Company name is required';
    }
    
    if (empty($errors)) {
        try {
            Company::update($companyId, [
                'name' => sanitizeInput($_POST['name']),
                'address' => sanitizeInput($_POST['address']),
                'phone' => sanitizeInput($_POST['phone']),
                'email' => sanitizeInput($_POST['email']),
                'tax_id' => sanitizeInput($_POST['tax_id'])
            ]);
            
            $_SESSION['flash_message'] = 'Company updated successfully';
            header('Location: ' . WEB_ROOT . '/companies/list.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get users and transactions count
try {
    $pdo = getDBConnection();
    
    // Get assigned users
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.full_name, uc.access_level
        FROM users u
        INNER JOIN user_companies uc ON u.user_id = uc.user_id
        WHERE uc.company_id = ?
        ORDER BY u.full_name
    ");
    $stmt->execute([$companyId]);
    $assignedUsers = $stmt->fetchAll();
    
    // Get transaction count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transactions WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $transactionCount = $stmt->fetch()['count'];
} catch (PDOException $e) {
    $assignedUsers = [];
    $transactionCount = 0;
}

$pageTitle = 'Edit Company';
include __DIR__ . '/../views/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Edit Company</h1>
        <div>
            <a href="<?= WEB_ROOT ?>/companies/list.php" class="btn btn-secondary">Back to Companies</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>Error:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Company Name *</label>
                    <input type="text" id="name" name="name" required 
                           value="<?= htmlspecialchars($company['name']) ?>">
                </div>

                <div class="form-group">
                    <label for="tax_id">Tax ID / Registration Number</label>
                    <input type="text" id="tax_id" name="tax_id" 
                           value="<?= htmlspecialchars($company['tax_id'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?= htmlspecialchars($company['phone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" 
                           value="<?= htmlspecialchars($company['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="3"><?= htmlspecialchars($company['address'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Company</button>
                <a href="<?= WEB_ROOT ?>/companies/list.php" class="btn btn-secondary">Cancel</a>
                <?php if ($transactionCount == 0 && count($assignedUsers) == 0): ?>
                    <button type="button" onclick="confirmDelete()" class="btn btn-danger" style="margin-left: auto;">Delete Company</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Company Statistics -->
    <div style="margin-top: 40px;">
        <h2 style="margin-bottom: 20px;">Company Information</h2>
        
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 30px;">
            <div class="stat-card">
                <h3>Created</h3>
                <div class="stat-value" style="font-size: 1.2rem; color: var(--text-dark);">
                    <?= date('M d, Y', strtotime($company['created_at'])) ?>
                </div>
            </div>
            
            <div class="stat-card">
                <h3>Assigned Users</h3>
                <div class="stat-value" style="font-size: 1.5rem; color: var(--primary-color);">
                    <?= count($assignedUsers) ?>
                </div>
            </div>
            
            <div class="stat-card">
                <h3>Total Transactions</h3>
                <div class="stat-value" style="font-size: 1.5rem; color: var(--success-color);">
                    <?= $transactionCount ?>
                </div>
            </div>
        </div>

        <?php if (!empty($assignedUsers)): ?>
            <h3 style="margin-bottom: 15px;">Assigned Users</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Access Level</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignedUsers as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                <td>
                                    <span class="badge badge-success">
                                        <?= htmlspecialchars(ucfirst($user['access_level'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <button onclick="removeUserAccess(<?= $user['user_id'] ?>)" class="btn btn-sm btn-danger">Remove Access</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDelete() {
    if (confirm('Are you sure you want to delete this company? This action cannot be undone.')) {
        window.location.href = '<?= WEB_ROOT ?>/companies/delete.php?id=<?= $companyId ?>';
    }
}

function removeUserAccess(userId) {
    if (confirm('Remove this user\'s access to the company?')) {
        window.location.href = '<?= WEB_ROOT ?>/companies/remove-user.php?company_id=<?= $companyId ?>&user_id=' + userId;
    }
}
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>
