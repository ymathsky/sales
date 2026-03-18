<?php
/**
 * Categories List
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();
requireAdmin();

$pageTitle = 'Categories';

// Get company filter
$companyId = !empty($_GET['company']) ? (int)$_GET['company'] : null;
$companies = Company::getByUser(getCurrentUserId());

$categories = Category::getAll(false, null, $companyId); // Get all including inactive

include __DIR__ . '/../views/header.php';
?>

<div class="container" style="max-width: 1200px; margin: 0 auto; padding-top: 24px;">

    <!-- Modern Header -->
    <div class="page-header" style="background: linear-gradient(135deg, #0d9488 0%, #115e59 100%); padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="background: rgba(255,255,255,0.2); p-2; border-radius: 8px; padding: 8px; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 24px; height: 24px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                </div>
                <div>
                    <h1 style="color: white; margin: 0 0 4px 0; font-size: 24px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">Transaction Categories</h1>
                    <p style="color: rgba(255,255,255,0.9); margin: 0; font-size: 14px;">Organize income and expenses.</p>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="<?= WEB_ROOT ?>/index.php" class="btn" style="background: rgba(0,0,0,0.2); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 10px 20px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; border-radius: 8px; text-decoration: none;">
                    ← Dashboard
                </a>
                <button onclick="window.openNewTab ? window.openNewTab('<?= WEB_ROOT ?>/categories/create.php<?= $companyId ? '?company=' . $companyId : '' ?>', 'Add Category') : window.location.href='<?= WEB_ROOT ?>/categories/create.php<?= $companyId ? '?company=' . $companyId : '' ?>'" 
                        class="btn" style="background: rgba(255,255,255,0.9); color: #0d9488; border: none; padding: 10px 20px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    New Category
                </button>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card" style="background: white; border-radius: 12px; padding: 20px; margin-bottom: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e5e7eb;">
        <form method="GET" class="inline-form">
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #374151; margin: 0; font-size: 14px;">
                    <svg style="width: 18px; height: 18px; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                    Filter View:
                </label>
                <div style="flex-grow: 1; max-width: 400px;">
                    <select name="company" onchange="this.form.submit()" 
                            class="form-control"
                            style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; background: white; cursor: pointer; color: #1f2937;">
                        <option value="">
                            🌐 All Categories (Global + Company-Specific)
                        </option>
                        <?php foreach ($companies as $comp): ?>
                            <option value="<?= $comp['company_id'] ?>" <?= $companyId == $comp['company_id'] ? 'selected' : '' ?>>
                                🏢 <?= htmlspecialchars($comp['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($companyId): ?>
                    <a href="?company=" class="btn" style="padding: 10px 16px; background: #f3f4f6; color: #4b5563; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">
                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="card" style="background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); overflow: hidden; border: 1px solid #e5e7eb;">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <tr>
                        <th style="padding: 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Name</th>
                        <th style="padding: 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Company</th>
                        <th style="padding: 16px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Type</th>
                        <th style="padding: 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Description</th>
                        <th style="padding: 16px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Status</th>
                        <th style="padding: 16px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Usage</th>
                        <th style="padding: 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Created</th>
                        <th style="padding: 16px; text-align: right; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Actions</th>
                    </tr>
                </thead>
                <tbody style="font-size: 14px; color: #334155;">
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 48px; color: #94a3b8;">
                                <div style="margin-bottom: 16px;">
                                    <svg style="width: 48px; height: 48px; margin: 0 auto; color: #cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                    </svg>
                                </div>
                                <p style="font-size: 16px; font-weight: 500;">No categories found</p>
                                <p style="margin-bottom: 24px;">Get started by adding your first transaction category.</p>
                                <a href="<?= WEB_ROOT ?>/categories/create.php" class="btn btn-primary" style="padding: 8px 16px; font-size: 14px; border-radius: 6px; background-color: #0d9488; border-color: #0d9488; color: white; text-decoration: none;">Add Category</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                                <td style="padding: 16px;">
                                    <div style="font-weight: 600; color: #0f172a;"><?= htmlspecialchars($category['name']) ?></div>
                                </td>
                                <td style="padding: 16px;">
                                    <?php if ($category['company_id']): ?>
                                        <div style="display: flex; align-items: center; gap: 6px; color: #475569;">
                                            <span style="font-size: 16px;">🏢</span>
                                            <span style="font-weight: 500; font-size: 13px;"><?= htmlspecialchars($category['company_name']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div style="display: flex; align-items: center; gap: 6px; color: #64748b;">
                                            <span style="font-size: 16px;">🌐</span>
                                            <span style="font-weight: 500; font-size: 13px;">Global</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <?php if ($category['type'] === 'in'): ?>
                                        <span style="display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; background: #dcfce7; color: #166534;">
                                            CASH IN
                                        </span>
                                    <?php elseif ($category['type'] === 'out'): ?>
                                        <span style="display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; background: #fee2e2; color: #991b1b;">
                                            CASH OUT
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; background: #e0f2fe; color: #075985;">
                                            BOTH
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 16px; color: #64748b; max-width: 300px;"><?= htmlspecialchars($category['description'] ?? '-') ?></td>
                                <td style="padding: 16px; text-align: center;">
                                    <?php if ($category['is_active']): ?>
                                        <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">ACTIVE</span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0;">INACTIVE</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <span style="background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 6px; font-size: 12px; font-weight: 500;">
                                        <?= Category::getUsageCount($category['category_id']) ?> txns
                                    </span>
                                </td>
                                <td style="padding: 16px; color: #64748b; font-size: 13px;">
                                    <?= formatDate($category['created_at'], 'M d, Y') ?>
                                </td>
                                <td style="padding: 16px; text-align: right;">
                                    <div style="display: inline-flex; gap: 8px;">
                                        <button onclick="window.openNewTab ? window.openNewTab('<?= WEB_ROOT ?>/categories/edit.php?id=<?= $category['category_id'] ?>', 'Edit Category') : window.location.href='<?= WEB_ROOT ?>/categories/edit.php?id=<?= $category['category_id'] ?>'" 
                                                class="btn btn-sm"
                                                style="padding: 6px 14px; font-size: 13px; background: #3b82f6; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer; transition: background 0.2s;"
                                                onmouseover="this.style.background='#2563eb'"
                                                onmouseout="this.style.background='#3b82f6'">
                                            Edit
                                        </button>
                                        <?php if (Category::getUsageCount($category['category_id']) == 0): ?>
                                            <a href="<?= WEB_ROOT ?>/categories/delete.php?id=<?= $category['category_id'] ?>" 
                                               class="btn btn-sm"
                                               onclick="return confirm('Delete this category?')"
                                               style="padding: 6px 14px; font-size: 13px; background: #ef4444; color: white; border: none; border-radius: 6px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; transition: background 0.2s;"
                                               onmouseover="this.style.background='#dc2626'"
                                               onmouseout="this.style.background='#ef4444'">
                                                Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
