<?php
/**
 * Delete Membership Plan
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Membership.php';
requireAdmin(); // Admin only

$companyId = getCurrentCompanyId();
requireCompanyAccess($companyId);

$planId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$planId) {
    setFlashMessage("Invalid Plan ID", "error");
    header("Location: plans.php?company=$companyId");
    exit;
}

// Fetch Plan
$plan = Membership::getPlan($planId);
if (!$plan || $plan['company_id'] != $companyId) {
    setFlashMessage("Plan not found or access denied.", "error");
    header("Location: plans.php?company=$companyId");
    exit;
}

try {
    // Check for existing subscriptions
    $usageCount = Membership::getPlanUsageCount($planId);
    
    if ($usageCount > 0) {
        // Soft delete (archive)
        Membership::updatePlan($planId, [
            'name' => $plan['name'],
            'description' => $plan['description'],
            'price' => $plan['price'],
            'duration_days' => $plan['duration_days'],
            'is_active' => 0 // Deactivate
        ]);
        setFlashMessage("Plan is in use by $usageCount members. It has been archived (set to inactive) instead of deleted.", "warning");
    } else {
        // Hard delete
        Membership::deletePlan($planId);
        setFlashMessage("Plan deleted successfully.", "success");
    }

} catch (Exception $e) {
    setFlashMessage("Error deleting plan: " . $e->getMessage(), "error");
}

header("Location: plans.php?company=$companyId");
exit;
