<?php
/**
 * Create User Page
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Company.php';

requireAdmin();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    if (empty($_POST['username'])) {
        $errors[] = 'Username is required';
    }
    if (empty($_POST['password'])) {
        $errors[] = 'Password is required';
    }
    if (empty($_POST['full_name'])) {
        $errors[] = 'Full name is required';
    }
    if (empty($_POST['email'])) {
        $errors[] = 'Email is required';
    }
    if (empty($_POST['role'])) {
        $errors[] = 'Role is required';
    }
    if (empty($_POST['companies'])) {
        $errors[] = 'At least one company must be selected';
    }
    
    // Check if username already exists
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->execute([$_POST['username']]);
            if ($stmt->fetch()) {
                $errors[] = 'Username already exists';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
            // Create user
            $userId = User::create([
                'username' => sanitizeInput($_POST['username']),
                'password' => $_POST['password'],
                'full_name' => sanitizeInput($_POST['full_name']),
                'email' => sanitizeInput($_POST['email']),
                'role' => $_POST['role']
            ]);
            
            // Assign companies
            $stmt = $pdo->prepare("INSERT INTO user_companies (user_id, company_id, access_level) VALUES (?, ?, 'admin')");
            foreach ($_POST['companies'] as $companyId) {
                $stmt->execute([$userId, $companyId]);
            }
            
            // Save custom permissions
            $customPermissions = $_POST['custom_permissions'] ?? [];
            if (!empty($customPermissions)) {
                $stmt = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_key, is_granted, granted_by) VALUES (?, ?, 1, ?)");
                foreach ($customPermissions as $permKey) {
                    $stmt->execute([$userId, $permKey, getCurrentUserId()]);
                }
            }
            
            $pdo->commit();
            $success = true;
            $_SESSION['flash_message'] = 'User created successfully';
            header('Location: <?= WEB_ROOT ?>/users/list.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get all companies for assignment
$companies = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT company_id, name FROM companies WHERE status = 'active' ORDER BY name");
    $companies = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = 'Could not load companies: ' . $e->getMessage();
}

// Get role permissions for display
$rolePermissions = [];
try {
    $stmt = $pdo->query("SELECT role, permission_key, is_granted FROM role_permissions WHERE is_granted = 1 ORDER BY role, permission_key");
    $perms = $stmt->fetchAll();
    foreach ($perms as $perm) {
        $rolePermissions[$perm['role']][] = $perm['permission_key'];
    }
} catch (PDOException $e) {
    $rolePermissions = [];
}

$permissionLabels = [
    'pos_access' => 'POS Access',
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

$pageTitle = 'Create User';
include __DIR__ . '/../views/header.php';
?>

<div class="container" style="max-width: 1000px; margin: 0 auto; padding-top: 24px;">
    <!-- Modern Header -->
    <div class="page-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h1 style="color: white; margin: 0 0 8px 0; font-size: 28px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">Create New User</h1>
                <p style="color: rgba(255,255,255,0.8); margin: 0; font-size: 14px;">Add a new user to the system and assign permissions.</p>
            </div>
            <div>
                <a href="<?= WEB_ROOT ?>/users/list.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3);">
                    <svg style="width: 16px; height: 16px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Users
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" style="background: #fee2e2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <strong>Please correct the following errors:</strong>
            </div>
            <ul style="margin: 0; padding-left: 24px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="form-container" style="display: grid; grid-template-columns: 1fr 350px; gap: 24px; align-items: start;">
        
        <!-- Left Column: User Details -->
        <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <h3 style="margin-top: 0; margin-bottom: 20px; color: #374151; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px;">
                <svg style="width: 20px; height: 20px; color: #667eea;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                Account Information
            </h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group" style="grid-column: span 2;">
                    <label for="full_name" style="font-weight: 600; color: #4b5563; margin-bottom: 6px; display: block;">Full Name <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="full_name" name="full_name" required 
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                           style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; transition: border-color 0.2s;"
                           onfocus="this.style.borderColor='#667eea'; this.style.outline='none'; box-shadow: 0 0 0 3px rgba(102,126,234,0.1);"
                           onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none';">
                </div>

                <div class="form-group">
                    <label for="username" style="font-weight: 600; color: #4b5563; margin-bottom: 6px; display: block;">Username <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="username" name="username" required 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; transition: border-color 0.2s;"
                           onfocus="this.style.borderColor='#667eea'; this.style.outline='none'; box-shadow: 0 0 0 3px rgba(102,126,234,0.1);"
                           onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none';">
                </div>

                <div class="form-group">
                    <label for="email" style="font-weight: 600; color: #4b5563; margin-bottom: 6px; display: block;">Email Address <span style="color: #ef4444;">*</span></label>
                    <input type="email" id="email" name="email" required 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; transition: border-color 0.2s;"
                           onfocus="this.style.borderColor='#667eea'; this.style.outline='none'; box-shadow: 0 0 0 3px rgba(102,126,234,0.1);"
                           onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none';">
                </div>

                <div class="form-group">
                    <label for="password" style="font-weight: 600; color: #4b5563; margin-bottom: 6px; display: block;">Password <span style="color: #ef4444;">*</span></label>
                    <input type="password" id="password" name="password" required minlength="6"
                           style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; transition: border-color 0.2s;"
                           onfocus="this.style.borderColor='#667eea'; this.style.outline='none'; box-shadow: 0 0 0 3px rgba(102,126,234,0.1);"
                           onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none';">
                    <small style="color: #6b7280; font-size: 12px; margin-top: 4px; display: block;">Minimum 6 characters</small>
                </div>

                <div class="form-group">
                    <label for="role" style="font-weight: 600; color: #4b5563; margin-bottom: 6px; display: block;">System Role <span style="color: #ef4444;">*</span></label>
                    <select id="role" name="role" required
                           style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; transition: border-color 0.2s;"
                           onfocus="this.style.borderColor='#667eea'; this.style.outline='none'; box-shadow: 0 0 0 3px rgba(102,126,234,0.1);"
                           onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none';'"
                           onchange="showRolePermissions(this.value)">
                        <option value="">Select Role</option>
                        <option value="user" <?= ($_POST['role'] ?? '') === 'user' ? 'selected' : '' ?>>User (Basic Access)</option>
                        <option value="manager" <?= ($_POST['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager (Extended Access)</option>
                        <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator (Full Access)</option>
                    </select>
                </div>

                <!-- Role Permissions Display -->
                <div id="role-permissions-display" style="margin-top: 16px; padding: 12px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; display: none;">
                    <div style="font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Role Permissions</div>
                    <div id="permissions-list" style="font-size: 13px; color: #374151;"></div>
                </div>
            </div>
            
            <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb; display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-size: 16px;">
                    <svg style="width: 18px; height: 18px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    Create User
                </button>
            </div>
        </div>
        
        <!-- Right Column: Company Assignments -->
        <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <h3 style="margin-top: 0; margin-bottom: 20px; color: #374151; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px;">
                <svg style="width: 20px; height: 20px; color: #667eea;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                Company Access
            </h3>
            
            <p style="font-size: 13px; color: #6b7280; margin-bottom: 16px;">Select which companies this user can access.</p>

            <div class="company-list" style="display: flex; flex-direction: column; gap: 8px; max-height: 400px; overflow-y: auto;">
                <?php if (empty($companies)): ?>
                    <p style="color: #ef4444; font-size: 14px; text-align: center; padding: 20px;">No companies available.</p>
                <?php else: ?>
                    <?php foreach ($companies as $company): ?>
                        <label class="company-item" style="display: flex; align-items: center; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.2s;"
                               onmouseover="this.style.borderColor='#667eea'; this.style.background='#f9fafb';"
                               onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='#e5e7eb'; this.style.background='white';} else {this.style.borderColor='#667eea'; this.style.background='#f0fdf4';}">
                            <input type="checkbox" name="companies[]" value="<?= $company['company_id'] ?>"
                                   <?= in_array($company['company_id'], $_POST['companies'] ?? []) ? 'checked' : '' ?>
                                   style="width: 18px; height: 18px; margin-right: 12px; accent-color: #667eea;"
                                   onchange="this.parentElement.style.background = this.checked ? '#f0fdf4' : 'white'; this.parentElement.style.borderColor = this.checked ? '#667eea' : '#e5e7eb';">
                            <span style="font-weight: 500; color: #374151;"><?= htmlspecialchars($company['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 16px; font-size: 12px; color: #9ca3af; text-align: center;">
                Admin users have full access to selected companies.
            </div>
        </div>

        <!-- Custom Permissions Section -->
        <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); grid-column: 1 / -1;">
            <h3 style="margin-top: 0; margin-bottom: 16px; color: #374151; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px;">
                <svg style="width: 20px; height: 20px; color: #667eea;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                Custom Permissions
                <span style="font-size: 12px; color: #6b7280; font-weight: 400; margin-left: auto;">(Optional - Override role-based permissions)</span>
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                <?php foreach ($permissionLabels as $key => $label): ?>
                    <label style="display: flex; align-items: center; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 6px; cursor: pointer; transition: all 0.2s; background: white;"
                           onmouseover="this.style.borderColor='#667eea'; this.style.background='#f9fafb';"
                           onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='#e5e7eb'; this.style.background='white';} else {this.style.borderColor='#667eea'; this.style.background='#eff6ff';}">
                        <input type="checkbox" name="custom_permissions[]" value="<?= $key ?>"
                               style="width: 18px; height: 18px; margin-right: 10px; accent-color: #667eea;"
                               onchange="this.parentElement.style.background = this.checked ? '#eff6ff' : 'white'; this.parentElement.style.borderColor = this.checked ? '#667eea' : '#e5e7eb';">
                        <span style="font-weight: 500; color: #374151; font-size: 14px;"><?= htmlspecialchars($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            
            <div style="margin-top: 16px; padding: 12px; background: #fef3c7; border: 1px solid #fbbf24; border-radius: 6px; font-size: 13px; color: #92400e;">
                <svg style="width: 16px; height: 16px; display: inline; margin-right: 6px;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                <strong>Note:</strong> Custom permissions are added ON TOP of role permissions. Select only additional permissions you want this specific user to have beyond their role.
            </div>
        </div>

    </form>
</div>

<script>
const rolePermissions = <?= json_encode($rolePermissions) ?>;
const permissionLabels = <?= json_encode($permissionLabels) ?>;

function showRolePermissions(role) {
    const display = document.getElementById('role-permissions-display');
    const permList = document.getElementById('permissions-list');
    
    if (role === 'admin') {
        permList.innerHTML = '<span style="color: #10b981; font-weight: 600;">✓ Full system access - all permissions granted</span>';
        display.style.display = 'block';
    } else if (role === 'user' || role === 'manager') {
        const perms = rolePermissions[role] || [];
        if (perms.length > 0) {
            permList.innerHTML = perms.map(p => 
                `<div style="padding: 4px 0; display: flex; align-items: center; gap: 6px;">
                    <svg style="width: 14px; height: 14px; color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    <span>${permissionLabels[p] || p}</span>
                </div>`
            ).join('');
        } else {
            permList.innerHTML = '<span style="color: #9ca3af;">No specific permissions configured</span>';
        }
        display.style.display = 'block';
    } else {
        display.style.display = 'none';
    }
}

// Show permissions on page load if role is selected
window.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    if (roleSelect.value) {
        showRolePermissions(roleSelect.value);
    }
});
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>
