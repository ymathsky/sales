<?php
/**
 * Membership Plans List
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Membership.php';
requireLogin();

$companyId = getCurrentCompanyId();
requireCompanyAccess($companyId);
requirePermission('membership_manage');

$plans = Membership::getPlans($companyId, false);

$pageTitle = 'Membership Plans';
include __DIR__ . '/../views/header.php';
?>

<div class="page-header">
    <div style="display: flex; gap: 12px; align-items: center;">
        <div style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); padding: 10px; border-radius: 10px; color: white;">
            <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 24px; font-weight: 700;">Plans & Packages</h1>
            <p style="margin: 0; color: #6b7280; font-size: 14px;">Configure subscription tiers and pricing.</p>
        </div>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="<?= WEB_ROOT ?>/memberships/list.php?company=<?= $companyId ?>" 
           onclick="if(window.openNewTab) { window.openNewTab(this.href, 'Memberships'); return false; }" 
           class="btn btn-secondary">
            ← Back to List
        </a>
        <a href="<?= WEB_ROOT ?>/memberships/create-plan.php?company=<?= $companyId ?>" 
           onclick="if(window.openNewTab) { window.openNewTab(this.href, 'Create Plan'); return false; }" 
           class="btn btn-primary"
           style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); border: none;">
            + Create New Plan
        </a>
    </div>
</div>

<div class="row" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;">
    <?php if (empty($plans)): ?>
        <div class="card" style="grid-column: 1 / -1; padding: 48px; text-align: center; color: #9ca3af;">
            <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
            <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">No Plans Defined</div>
            <div>Create your first subscription plan to get started.</div>
            <a href="create-plan.php" class="btn btn-primary" style="margin-top: 16px;">Create Plan</a>
        </div>
    <?php else: ?>
        <?php foreach ($plans as $plan): ?>
            <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; height: 100%;">
                <div style="padding: 24px; border-bottom: 1px solid #f1f5f9; background: #fffcfc;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                        <h3 style="margin: 0; font-size: 20px; font-weight: 700; color: #1f2937;"><?= htmlspecialchars($plan['name']) ?></h3>
                        <?php if ($plan['is_active']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($plan['description'])): ?>
                        <p style="margin: 0; color: #6b7280; font-size: 14px; line-height: 1.5;"><?= htmlspecialchars($plan['description']) ?></p>
                    <?php else: ?>
                        <p style="margin: 0; color: #9ca3af; font-size: 14px; font-style: italic;">No description provided</p>
                    <?php endif; ?>
                </div>
                
                <div style="padding: 24px; flex-grow: 1;">
                    <div style="display: flex; align-items: baseline; gap: 4px; margin-bottom: 20px;">
                        <span style="font-size: 32px; font-weight: 800; color: #1f2937;"><?= formatMoney($plan['price']) ?></span>
                        <span style="color: #6b7280; font-weight: 500;">/ <?= $plan['duration_days'] ?> days</span>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="display: flex; align-items: center; gap: 10px; font-size: 14px; color: #4b5563;">
                            <svg style="width: 18px; height: 18px; color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Duration: <b><?= $plan['duration_days'] ?> Days</b></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px; font-size: 14px; color: #4b5563;">
                            <svg style="width: 18px; height: 18px; color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Available in POS</span>
                        </div>
                    </div>
                </div>
                
                <div style="padding: 16px 24px; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 8px;">
                    <?php if (getCurrentUserRole() === 'admin'): ?>
                        <form method="POST" action="delete-plan.php" onsubmit="return confirm('Are you sure? This cannot be undone.');" style="margin: 0;">
                            <input type="hidden" name="id" value="<?= $plan['plan_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-white" style="border: 1px solid #ef4444; color: #ef4444;">
                                Delete
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="<?= WEB_ROOT ?>/memberships/edit-plan.php?id=<?= $plan['plan_id'] ?>" 
                       onclick="if(window.openNewTab) { window.openNewTab(this.href, 'Edit Plan'); return false; }"
                       class="btn btn-sm btn-white" style="width: auto; text-align: center; border: 1px solid #d1d5db;">
                        Edit Configuration
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
