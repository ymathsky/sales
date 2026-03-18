<?php
/**
 * Settings - Role Permissions
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireAdmin(); // Only admin can access settings

$pageTitle = 'Role Permissions';

// Fetch current permissions
$pdo = getDBConnection();
$sql = "SELECT * FROM role_permissions";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pivot data
$permissions = [
    'pos_access' => 'Access POS System',
    'sale_create' => 'Create Sales',
    'sale_delete' => 'Delete Sales',
    'transaction_view' => 'View Transactions',
    'transaction_create' => 'Create Transactions',
    'transaction_delete' => 'Delete Transactions',
    'report_view' => 'View Reports',
    'user_manage' => 'Manage Users',
    'company_manage' => 'Manage Companies',
    'membership_manage' => 'Manage Memberships',
    'settings_manage' => 'Manage Settings'
];

$roles = ['manager', 'user']; 

// Organize DB data
$rolePerms = [];
foreach ($rows as $r) {
    $rolePerms[$r['role']][$r['permission_key']] = $r['is_granted'];
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($roles as $role) {
        foreach ($permissions as $key => $label) {
            $isGranted = isset($_POST['perms'][$role][$key]) ? 1 : 0;
            
            // Check if exists
            $check = $pdo->prepare("SELECT id FROM role_permissions WHERE role = ? AND permission_key = ?");
            $check->execute([$role, $key]);
            
            if ($check->rowCount() > 0) {
                $update = $pdo->prepare("UPDATE role_permissions SET is_granted = ? WHERE role = ? AND permission_key = ?");
                $update->execute([$isGranted, $role, $key]);
            } else {
                $insert = $pdo->prepare("INSERT INTO role_permissions (role, permission_key, is_granted) VALUES (?, ?, ?)");
                $insert->execute([$role, $key, $isGranted]);
            }
        }
    }
    setFlashMessage('Permissions updated successfully.', 'success');
    header('Location: index.php');
    exit;
}

include __DIR__ . '/../views/header.php';
?>

<div class="page-header">
    <div style="display: flex; gap: 12px; align-items: center;">
        <div style="background: linear-gradient(135deg, #4b5563 0%, #1f2937 100%); padding: 12px; border-radius: 12px; color: white;">
            <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #1f2937;">Settings</h1>
            <p style="margin: 0; color: #6b7280; font-size: 14px;">Manage application configuration and permissions.</p>
        </div>
    </div>
</div>

<?php if ($flash = getFlashMessage()): ?>
    <div class="alert alert-<?= $flash['type'] ?>" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; font-weight: 500; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;">
        <?= htmlspecialchars($flash['text']) ?>
    </div>
<?php endif; ?>

<div class="card" style="background: white; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden;">
    <div style="padding: 24px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
        <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #1f2937;">Role Based Access Control (RBAC)</h3>
        <p style="margin: 4px 0 0; color: #6b7280; font-size: 14px;">Define what each role can access in the system. Admin has full access by default.</p>
    </div>
    
    <form method="POST">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f3f4f6; text-align: left;">
                    <th style="padding: 16px 24px; font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600;">Feature / Permission</th>
                    <?php foreach ($roles as $role): ?>
                        <th style="padding: 16px 24px; font-size: 13px; color: #4b5563; text-transform: uppercase; font-weight: 600; text-align: center;"><?= ucfirst($role) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permissions as $key => $label): ?>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 16px 24px; font-weight: 500; color: #1f2937;">
                            <?= htmlspecialchars($label) ?>
                            <div style="font-size: 12px; color: #9ca3af; font-weight: 400; font-family: monospace; margin-top: 2px;"><?= $key ?></div>
                        </td>
                        <?php foreach ($roles as $role): ?>
                            <td style="padding: 16px 24px; text-align: center;">
                                <label style="cursor: pointer; display: inline-flex; align-items: center; justify-content: center; width: 100%; height: 100%;">
                                    <input type="checkbox" name="perms[<?= $role ?>][<?= $key ?>]" 
                                           style="width: 18px; height: 18px; accent-color: #6366f1; cursor: pointer;"
                                           <?= isset($rolePerms[$role][$key]) && $rolePerms[$role][$key] ? 'checked' : '' ?>>
                                </label>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="padding: 24px; background: #f9fafb; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" style="background: #1f2937; color: white; border: none; padding: 10px 24px; font-weight: 600; font-size: 14px; border-radius: 6px; cursor: pointer;">
                Save Changes
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
