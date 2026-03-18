<?php
/**
 * Memberships List
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Membership.php';
requireLogin();

$companyId = getCurrentCompanyId();
requireCompanyAccess($companyId);

$memberships = Membership::getActiveMemberships($companyId);
$plans = Membership::getPlans($companyId, true);

// Calculate stats
$totalRevenue = 0;
$expiringSoon = 0;
foreach ($memberships as $m) {
    if (strtotime($m['end_date']) < time() + (86400 * 7)) {
        $expiringSoon++;
    }
}

$pageTitle = 'Memberships';
include __DIR__ . '/../views/header.php';
?>

<div class="page-header" style="margin-bottom: 32px">
    <div style="display: flex; gap: 16px; align-items: center;">
        <div style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); padding: 12px; border-radius: 12px; color: white; box-shadow: 0 4px 6px -1px rgba(124, 58, 237, 0.4);">
            <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
            </svg>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 26px; font-weight: 800; color: #1f2937; letter-spacing: -0.5px;">Memberships</h1>
            <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 15px;">Manage customer subscriptions and recurring plans.</p>
        </div>
    </div>
    <div style="display: flex; gap: 12px;">
        <?php if (function_exists('hasPermission') && hasPermission('membership_manage')): ?>
        <a href="/sales/memberships/plans.php?company=<?= $companyId ?>" 
           onclick="if(window.openNewTab) { window.openNewTab(this.href, 'Manage Plans'); return false; }" 
           class="btn btn-white" style="border: 1px solid #d1d5db; color: #374151; font-weight: 600; padding: 10px 20px; display: flex; align-items: center; gap: 8px;">
            <svg style="width: 18px; height: 18px; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            Manage Plans
        </a>
        <?php endif; ?>
        <a href="/sales/memberships/assign.php?company=<?= $companyId ?>" 
           onclick="if(window.openNewTab) { window.openNewTab(this.href, 'New Subscription'); return false; }" 
           class="btn btn-primary"
           style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border: none; padding: 10px 24px; font-weight: 600; box-shadow: 0 4px 6px -1px rgba(124, 58, 237, 0.3);">
            + New Subscription
        </a>
    </div>
</div>

<div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 32px;">
    <!-- Active Members -->
    <div class="card" style="padding: 24px; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: #8b5cf6;"></div>
        <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #6b7280; margin-bottom: 12px; letter-spacing: 0.5px;">
            Active Members
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <div style="font-size: 32px; font-weight: 800; color: #1f2937; line-height: 1;">
                <?= count($memberships) ?>
            </div>
        </div>
        <div style="font-size: 13px; color: #6b7280; margin-top: 8px; display: flex; align-items: center; gap: 4px;">
            <svg style="width: 14px; height: 14px; color: #8b5cf6;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            Current subscribers
        </div>
    </div>

    <!-- Active Plans -->
    <div class="card" style="padding: 24px; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: #f59e0b;"></div>
        <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #6b7280; margin-bottom: 12px; letter-spacing: 0.5px;">
            Available Plans
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <div style="font-size: 32px; font-weight: 800; color: #1f2937; line-height: 1;">
                <?= count($plans) ?>
            </div>
        </div>
        <div style="font-size: 13px; color: #6b7280; margin-top: 8px; display: flex; align-items: center; gap: 4px;">
            <svg style="width: 14px; height: 14px; color: #f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
            Subscription options
        </div>
    </div>

    <!-- Expiring Soon -->
    <div class="card" style="padding: 24px; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: <?= $expiringSoon > 0 ? '#ef4444' : '#10b981' ?>;"></div>
        <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #6b7280; margin-bottom: 12px; letter-spacing: 0.5px;">
            Expiring Soon
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <div style="font-size: 32px; font-weight: 800; color: #1f2937; line-height: 1;">
                <?= $expiringSoon ?>
            </div>
        </div>
        <div style="font-size: 13px; color: #6b7280; margin-top: 8px; display: flex; align-items: center; gap: 4px;">
            <svg style="width: 14px; height: 14px; color: <?= $expiringSoon > 0 ? '#ef4444' : '#10b981' ?>;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Within next 7 days
        </div>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
    <div style="padding: 20px 24px; border-bottom: 1px solid #f1f5f9; background: #fff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <h2 style="margin: 0; font-size: 18px; font-weight: 700; color: #1f2937;">Active Subscriptions</h2>
        
        <div style="display: flex; gap: 12px; align-items: center;">
            <div style="position: relative;">
                <svg style="width: 16px; height: 16px; color: #9ca3af; position: absolute; left: 12px; top: 50%; transform: translateY(-50%);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" placeholder="Search members..." style="padding: 8px 12px 8px 36px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; width: 200px; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor = '#8b5cf6'" onblur="this.style.borderColor = '#e5e7eb'">
            </div>
            <select style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; outline: none; background-color: #fff; cursor: pointer;">
                <option value="all">All Plans</option>
                <?php foreach ($plans as $p): ?>
                    <option value="<?= $p['plan_id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
            <thead style="background: #f8fafc;">
                <tr>
                    <th style="padding: 16px 24px; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Customer</th>
                    <th style="padding: 16px 24px; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Plan</th>
                    <th style="padding: 16px 24px; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Period</th>
                    <th style="padding: 16px 24px; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Status</th>
                    <th style="padding: 16px 24px; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; text-align: right; border-bottom: 1px solid #e5e7eb;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($memberships)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 64px 24px; color: #9ca3af;">
                            <div style="margin-bottom: 16px; background: #f3f4f6; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                                <svg style="width: 32px; height: 32px; color: #cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <h3 style="color: #374151; font-weight: 600; margin: 0 0 8px 0;">No Active Memberships</h3>
                            <p style="margin: 0; font-size: 14px; max-width: 300px; margin: 0 auto;">Get started by assigning a subscription plan to one of your customers.</p>
                            <a href="/sales/memberships/assign.php?company=<?= $companyId ?>" class="btn btn-primary" style="margin-top: 16px; display: inline-block;">Assign First Membership</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($memberships as $sub): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background-color 0.2s;">
                            <td style="padding: 16px 24px;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 36px; height: 36px; background: #e0e7ff; color: #4f46e5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                                        <?= strtoupper(substr($sub['customer_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: #1f2937;"><?= htmlspecialchars($sub['customer_name']) ?></div>
                                        <div style="font-size: 12px; color: #6b7280;"><?= formatMoney($sub['price_paid']) ?> / cycle</div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 16px 24px;">
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #f3f4f6; color: #4b5563; border-radius: 6px; font-size: 13px; font-weight: 500; border: 1px solid #e5e7eb;">
                                    <?= htmlspecialchars($sub['plan_name']) ?>
                                </span>
                            </td>
                            <td style="padding: 16px 24px;">
                                <div style="font-size: 13px; color: #4b5563;">
                                    <div style="margin-bottom: 2px;">Started: <?= date('M d, Y', strtotime($sub['start_date'])) ?></div>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span style="color: #9ca3af;">Ends:</span>
                                        <?php 
                                            $daysLeft = ceil((strtotime($sub['end_date']) - time()) / 86400); 
                                            $isExpiring = $daysLeft <= 7;
                                        ?>
                                        <span style="font-weight: 600; color: <?= $isExpiring ? '#dc2626' : '#1f2937' ?>">
                                            <?= date('M d, Y', strtotime($sub['end_date'])) ?>
                                        </span>
                                        <?php if($isExpiring && $daysLeft > 0): ?>
                                            <span style="font-size: 10px; background: #fee2e2; color: #991b1b; padding: 1px 6px; border-radius: 99px; font-weight: 600;">Expiring</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 16px 24px;">
                                <?php if (strtotime($sub['end_date']) < time()): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #fef2f2; color: #991b1b; border-radius: 99px; font-size: 12px; font-weight: 600;">
                                        ● Expired
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #ecfdf5; color: #047857; border-radius: 99px; font-size: 12px; font-weight: 600;">
                                        ● Active
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 16px 24px; text-align: right;">
                                <div style="display: inline-flex; gap: 8px;">
                                    <?php if (!empty($sub['transaction_id'])): ?>
                                    <a href="/sales/transactions/receipt.php?id=<?= $sub['transaction_id'] ?>&company=<?= $companyId ?>" target="_blank" style="padding: 6px; hover:bg-gray-50; border-radius: 6px; color: #6b7280; text-decoration: none;" title="Print Receipt">
                                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                    </a>
                                    <?php endif; ?>
                                    <a href="#" onclick="alert('Edit implementation coming soon'); return false;" style="padding: 6px; hover:bg-gray-50; border-radius: 6px; color: #6b7280; text-decoration: none;" title="Edit">
                                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                    <form method="POST" action="delete-subscription.php" onsubmit="return confirm('Please confirm you want to delete this subscription. This action cannot be undone.');" style="margin: 0;">
                                        <input type="hidden" name="id" value="<?= $sub['membership_id'] ?>">
                                        <button type="submit" style="background: none; border: none; padding: 6px; border-radius: 6px; color: #ef4444; cursor: pointer;" title="Delete">
                                            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[placeholder="Search members..."]');
    const planSelect = document.querySelector('select');
    const tableRows = document.querySelectorAll('tbody tr');

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedPlanId = planSelect.value;
        let hasVisibleRows = false;

        tableRows.forEach(row => {
            if (row.querySelector('td[colspan]')) return; // Skip empty state row

            const customerName = row.cells[0].textContent.toLowerCase();
            // Plan ID isn't directly in the row text, but we can filter by Plan Name text for now or add data attributes
            // Let's use text matching for simplicity or data-plan-id if we added it (we didn't yet)
            const planNameCell = row.cells[1].textContent.trim();
            const selectedPlanText = planSelect.options[planSelect.selectedIndex].text;
            
            const matchesSearch = customerName.includes(searchTerm);
            const matchesPlan = selectedPlanId === 'all' || planNameCell === selectedPlanText;

            if (matchesSearch && matchesPlan) {
                row.style.display = '';
                hasVisibleRows = true;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Handle no results visibility could be added here
    }

    searchInput.addEventListener('input', filterTable);
    planSelect.addEventListener('change', filterTable);
});
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>
