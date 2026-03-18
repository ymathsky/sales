<?php
/**
 * Edit Category
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();
requireAdmin();

$pageTitle = 'Edit Category';

$companies = Company::getByUser(getCurrentUserId());
$categoryId = (int)($_GET['id'] ?? 0);
$category = Category::getById($categoryId);

if (!$category) {
    setFlashMessage('Category not found', 'error');
    header('Location: /sales/categories/list.php');
    exit;
}

$errors = [];
$formData = $category;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'name' => trim($_POST['name'] ?? ''),
        'company_id' => !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null,
        'type' => $_POST['type'] ?? 'both',
        'description' => trim($_POST['description'] ?? ''),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    // Validation
    if (empty($formData['name'])) {
        $errors[] = 'Category name is required';
    }
    
    if (!in_array($formData['type'], ['in', 'out', 'both'])) {
        $errors[] = 'Invalid category type';
    }
    
    if (empty($errors)) {
        try {
            Category::update($categoryId, $formData);
            setFlashMessage('Category updated successfully', 'success');
            header('Location: /sales/categories/list.php');
            exit;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $errors[] = 'A category with this name already exists';
            } else {
                $errors[] = 'Error updating category: ' . $e->getMessage();
            }
        }
    }
}

$usageCount = Category::getUsageCount($categoryId);

include __DIR__ . '/../views/header.php';
?>

<div class="page-header">
    <h1>
        <svg style="width: 28px; height: 28px; vertical-align: middle; margin-right: 10px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
        </svg>
        Edit Category
    </h1>
    <div>
        <?php if ($usageCount == 0): ?>
            <a href="/sales/categories/delete.php?id=<?= $categoryId ?>" 
               class="btn btn-danger"
               onclick="return confirm('Delete this category?')">Delete Category</a>
        <?php endif; ?>
        <a href="/sales/categories/list.php" class="btn btn-secondary">← Back to Categories</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>Please fix the following errors:</strong>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($usageCount > 0): ?>
    <div class="alert alert-info">
        <strong>Note:</strong> This category is being used in <?= $usageCount ?> transaction(s). 
        You cannot delete it, but you can make it inactive.
    </div>
<?php endif; ?>

<div class="form-container">
    <form method="POST" action="">
        <div class="form-group">
            <label for="name">Category Name *</label>
            <input type="text" id="name" name="name" required 
                   value="<?= htmlspecialchars($formData['name']) ?>"
                   placeholder="e.g., Office Supplies, Sales Revenue">
        </div>
        
        <div class="form-group">
            <label for="company_id">Assign to Company</label>
            <select id="company_id" name="company_id">
                <option value="">Global (Available to All Companies)</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= $company['company_id'] ?>" 
                            <?= $formData['company_id'] == $company['company_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($company['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="color: var(--text-light);">Leave as Global to make available to all companies</small>
        </div>
        
        <div class="form-group">
            <label for="type">Transaction Type *</label>
            <select id="type" name="type" required>
                <option value="both" <?= $formData['type'] === 'both' ? 'selected' : '' ?>>Both (Cash In & Out)</option>
                <option value="in" <?= $formData['type'] === 'in' ? 'selected' : '' ?>>Cash In Only</option>
                <option value="out" <?= $formData['type'] === 'out' ? 'selected' : '' ?>>Cash Out Only</option>
            </select>
            <small style="color: var(--text-light);">Specify where this category can be used</small>
        </div>
        
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3" 
                      placeholder="Optional description for this category"><?= htmlspecialchars($formData['description']) ?></textarea>
        </div>
        
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="is_active" value="1" <?= $formData['is_active'] ? 'checked' : '' ?>>
                Active (visible in transaction forms)
            </label>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Category</button>
            <a href="/sales/categories/list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
