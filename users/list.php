<?php
/**
 * List Users Page
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/User.php';

requireAdmin();

// Get all users
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT u.*, 
               COUNT(DISTINCT uc.company_id) as company_count
        FROM users u
        LEFT JOIN user_companies uc ON u.user_id = uc.user_id
        GROUP BY u.user_id
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $users = [];
}

$pageTitle = 'Users';
include __DIR__ . '/../views/header.php';
?>

<div class="container" style="max-width: 1200px; margin: 0 auto; padding-top: 24px;">
    
    <!-- Modern Header -->
    <div class="page-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h1 style="color: white; margin: 0 0 8px 0; font-size: 28px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">Users</h1>
                <p style="color: rgba(255,255,255,0.8); margin: 0; font-size: 14px;">Manage system access and permissions.</p>
            </div>
            <div>
                <button onclick="window.openNewTab ? window.openNewTab('<?= WEB_ROOT ?>/users/create.php', 'Create User') : window.location.href='<?= WEB_ROOT ?>/users/create.php'" class="btn" style="background: rgba(255,255,255,0.2); color: white; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); padding: 10px 20px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;">
                    <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add New User
                </button>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-success" style="background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error" style="background: #fee2e2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="card" style="background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); overflow: hidden;">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                    <tr>
                        <th style="padding: 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em;">Username</th>
                        <th style="padding: 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em;">Full Name</th>
                        <th style="padding: 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em;">Email</th>
                        <th style="padding: 16px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em;">Role</th>
                        <th style="padding: 16px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em;">Companies</th>
                        <th style="padding: 16px; text-align: center; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em;">Status</th>
                        <th style="padding: 16px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em;">Last Login</th>
                        <th style="padding: 16px; text-align: right; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em;">Actions</th>
                    </tr>
                </thead>
                <tbody style="font-size: 14px; color: #374151;">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #9ca3af;">
                                <div style="margin-bottom: 10px; font-size: 24px;">👥</div>
                                No users found in the system.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr style="border-bottom: 1px solid #f3f4f6; transition: background 0.2s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                                <td style="padding: 16px;">
                                    <div style="font-weight: 600; color: #111827;"><?= htmlspecialchars($user['username']) ?></div>
                                </td>
                                <td style="padding: 16px;"><?= htmlspecialchars($user['full_name']) ?></td>
                                <td style="padding: 16px; color: #6b7280;"><?= htmlspecialchars($user['email']) ?></td>
                                <td style="padding: 16px; text-align: center;">
                                    <span style="display: inline-block; padding: 2px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; <?= $user['role'] === 'admin' ? 'background: #fee2e2; color: #991b1b;' : 'background: #d1fae5; color: #065f46;' ?>">
                                        <?= strtoupper($user['role']) ?>
                                    </span>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <span style="background: #e5e7eb; color: #374151; padding: 2px 8px; border-radius: 6px; font-size: 12px; font-weight: 500;">
                                        <?= $user['company_count'] ?>
                                    </span>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; <?= $user['status'] === 'active' ? 'background: #10b981;' : 'background: #ef4444;' ?>"></span>
                                    <?= ucfirst($user['status']) ?>
                                </td>
                                <td style="padding: 16px; color: #6b7280; font-size: 13px;">
                                    <?= $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : '<span style="color: #9ca3af;">Never</span>' ?>
                                </td>
                                <td style="padding: 16px; text-align: right;">
                                    <button onclick="window.openNewTab ? window.openNewTab('<?= WEB_ROOT ?>/users/edit.php?id=<?= $user['user_id'] ?>', 'Edit User') : window.location.href='<?= WEB_ROOT ?>/users/edit.php?id=<?= $user['user_id'] ?>'" 
                                            class="btn" style="padding: 6px 14px; font-size: 13px; background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe; border-radius: 6px; font-weight: 500; transition: all 0.2s; white-space: nowrap;"
                                            onmouseover="this.style.background='#3b82f6'; this.style.color='white';"
                                            onmouseout="this.style.background='#eff6ff'; this.style.color='#3b82f6';">
                                        Edit
                                    </button>
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
