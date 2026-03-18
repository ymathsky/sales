<?php
/**
 * Assign Subscription
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Membership.php';
require_once __DIR__ . '/../models/Customer.php';
requireLogin();

$companyId = getCurrentCompanyId();
requireCompanyAccess($companyId);
$userId = $_SESSION['user_id'];

$customers = Customer::getByCompany($companyId, true);
$plans = Membership::getPlans($companyId, true);

// Select plan from URL if provided
$selectedPlanId = isset($_GET['plan']) ? (int)$_GET['plan'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $customerId = (int)$_POST['customer_id'];
        $planId = (int)$_POST['plan_id'];
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
        
        // Add Membership
        $membershipId = Membership::addMembership($customerId, $planId, $startDate);
        
        // Also create a Transaction (Revenue)
        $plan = Membership::getPlan($planId);
        require_once __DIR__ . '/../models/Transaction.php';
        
        $transactionId = Transaction::create([
            'company_id' => $companyId,
            'type' => 'in',
            'amount' => $plan['price'],
            'transaction_date' => $startDate,
            'category' => 'Subscription',
            'description' => 'Subscription: ' . $plan['name'],
            'reference_number' => 'SUB-' . time(),
            'created_by' => $userId
        ]);

        if ($membershipId) {
            Membership::linkTransaction($membershipId, $transactionId);
        }

        header("Location: <?= WEB_ROOT ?>/transactions/receipt.php?id=$transactionId&company=$companyId");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Assign Subscription';
include __DIR__ . '/../views/header.php';
?>

<div class="row" style="max-width: 600px; margin: 0 auto; padding-top: 40px;">
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 24px; border-bottom: 1px solid #e5e7eb; background: #f8fafc;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                <a href="list.php?company=<?= $companyId ?>" 
                   onclick="if(window.openNewTab) { window.openNewTab(this.href, 'Memberships'); return false; }"
                   style="color: #6b7280; display: flex; align-items: center;">
                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <h1 style="margin: 0; font-size: 20px; font-weight: 700;">Assign Subscription</h1>
            </div>
            <p style="margin: 0; color: #6b7280; font-size: 14px; padding-left: 32px;">Manually assign a plan to a customer.</p>
        </div>
        
        <div style="padding: 32px;">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger" style="margin-bottom: 24px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                
                <div class="form-group" style="margin-bottom: 24px;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 8px;">Select Customer</label>
                    <select name="customer_id" class="form-control" required 
                            style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px;">
                        <option value="">-- Choose a Customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['customer_id'] ?>">
                                <?= htmlspecialchars($c['customer_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 8px;">Select Plan</label>
                    
                    <?php if (empty($plans)): ?>
                        <div style="padding: 16px; background: #fff1f2; border: 1px solid #fecaca; border-radius: 8px; color: #9f1239;">
                            <strong>No active plans found!</strong><br>
                            Please <a href="plans.php?company=<?= $companyId ?>" style="color: #be123c; text-decoration: underline; font-weight: 600;">go to Plans & Packages</a> to create or activate a plan.
                        </div>
                    <?php else: ?>
                        <div style="display: grid; gap: 12px;">
                            <?php foreach ($plans as $plan): ?>
                                <label class="plan-option" style="display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                                    <input type="radio" name="plan_id" value="<?= $plan['plan_id'] ?>" required <?= $plan['plan_id'] == $selectedPlanId ? 'checked' : '' ?>
                                           style="width: 20px; height: 20px; accent-color: #7c3aed;">
                                    <div style="flex-grow: 1;">
                                        <div style="font-weight: 600; color: #1f2937;"><?= htmlspecialchars($plan['name']) ?></div>
                                        <div style="font-size: 13px; color: #6b7280;"><?= $plan['duration_days'] ?> Days</div>
                                    </div>
                                    <div style="font-weight: 700; color: #7c3aed;"><?= formatMoney($plan['price']) ?></div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group" style="margin-bottom: 32px;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 8px;">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required
                           style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px;">
                </div>

                <div class="alert alert-info" style="margin-bottom: 24px; font-size: 14px; background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe; padding: 12px; border-radius: 6px;">
                    ℹ️ This will automatically create a transaction record for this subscription.
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <a href="list.php?company=<?= $companyId ?>" class="btn btn-secondary" style="padding: 10px 24px;">Cancel</a>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 24px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border: none;">Assign & Charge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .plan-option:hover {
        background: #f9fafb;
        border-color: #8b5cf6 !important;
    }
    .plan-option:has(input:checked) {
        background: #f5f3ff;
        border-color: #8b5cf6 !important;
        box-shadow: 0 0 0 1px #8b5cf6;
    }
</style>

<?php include __DIR__ . '/../views/footer.php'; ?>
