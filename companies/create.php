<?php
/**
 * Create Company Page
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Company.php';

requireAdmin();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    if (empty($_POST['name'])) {
        $errors[] = 'Company name is required';
    }
    
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
            // Create company
            $companyId = Company::create([
                'name' => sanitizeInput($_POST['name']),
                'address' => sanitizeInput($_POST['address']),
                'phone' => sanitizeInput($_POST['phone']),
                'email' => sanitizeInput($_POST['email']),
                'tax_id' => sanitizeInput($_POST['tax_id'])
            ]);
            
            // Automatically grant access to the admin user
            $stmt = $pdo->prepare("INSERT INTO user_companies (user_id, company_id, access_level) VALUES (?, ?, 'admin')");
            $stmt->execute([getCurrentUserId(), $companyId]);
            
            $pdo->commit();
            $success = true;
            // Using setFlashMessage would require updating list.php to use getFlashMessage
            $_SESSION['flash_message'] = 'Company created successfully';
            header('Location: /sales/companies/list.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Create Company';
include __DIR__ . '/../views/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Create New Company</h1>
        <div>
            <a href="/sales/companies/list.php" class="btn btn-secondary">Back to Companies</a>
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
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="tax_id">Tax ID / Registration Number</label>
                    <input type="text" id="tax_id" name="tax_id" 
                           value="<?= htmlspecialchars($_POST['tax_id'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="3"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Company</button>
                <a href="/sales/companies/list.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
