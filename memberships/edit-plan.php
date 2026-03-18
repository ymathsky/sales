<?php
/**
 * Edit Membership Plan
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Membership.php';
requireLogin();

$companyId = getCurrentCompanyId();
requireCompanyAccess($companyId);
requirePermission('membership_manage');

$planId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$planId) {
    header("Location: plans.php?company=$companyId");
    exit;
}

// Fetch Plan
$plan = Membership::getPlan($planId);

if (!$plan || $plan['company_id'] != $companyId) {
    die("Plan not found or access denied.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $days = filter_input(INPUT_POST, 'duration_days', FILTER_VALIDATE_INT);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (!$price && $price !== 0.0) throw new Exception("Invalid Price");
        if (!$days) throw new Exception("Invalid Duration");

        $data = [
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'price' => $price,
            'duration_days' => $days,
            'is_active' => $isActive
        ];

        Membership::updatePlan($planId, $data);
        setFlashMessage('Plan updated successfully.', 'success');
        header("Location: plans.php?company=$companyId");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Edit Plan';
include __DIR__ . '/../views/header.php';
?>

<div class="row" style="max-width: 600px; margin: 0 auto; padding-top: 40px;">
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 24px; border-bottom: 1px solid #e5e7eb; background: #f8fafc;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                <a href="plans.php?company=<?= $companyId ?>" 
                   onclick="if(window.openNewTab) { window.openNewTab(this.href, 'Plans'); return false; }"
                   style="color: #6b7280; display: flex; align-items: center;">
                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <h1 style="margin: 0; font-size: 20px; font-weight: 700;">Edit Plan</h1>
            </div>
            <p style="margin: 0; color: #6b7280; font-size: 14px; padding-left: 32px;">Update subscription details.</p>
        </div>
        
        <div style="padding: 32px;">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger" style="margin-bottom: 24px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group" style="margin-bottom: 24px;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 8px;">Plan Name</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($plan['name']) ?>" required 
                           style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px;">
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 8px;">Description</label>
                    <textarea name="description" class="form-control" rows="3"
                              style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px;"><?= htmlspecialchars($plan['description']) ?></textarea>
                </div>

                <div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                    <div class="form-group">
                        <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 8px;">Price</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 11px; color: #6b7280; font-weight: 500;">₱</span>
                            <input type="number" name="price" class="form-control" step="0.01" value="<?= htmlspecialchars($plan['price']) ?>" required
                                   style="width: 100%; padding: 10px 12px 10px 32px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 8px;">Duration (Days)</label>
                        <input type="number" name="duration_days" class="form-control" value="<?= htmlspecialchars($plan['duration_days']) ?>" required
                               style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 32px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_active" value="1" <?= $plan['is_active'] ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                        <span style="font-weight: 600; color: #374151;">Active Plan</span>
                    </label>
                    <p style="margin: 4px 0 0 28px; font-size: 13px; color: #6b7280;">Inactive plans cannot be assigned to new customers.</p>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <a href="plans.php?company=<?= $companyId ?>" class="btn btn-secondary" style="padding: 10px 24px;">Cancel</a>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 24px; background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); border: none;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
