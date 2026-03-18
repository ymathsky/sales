<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Create role_permissions table
    $sql = "CREATE TABLE IF NOT EXISTS role_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role VARCHAR(50) NOT NULL,
        permission_key VARCHAR(100) NOT NULL,
        is_granted TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_role_perm (role, permission_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "Table 'role_permissions' created successfully.<br>";
    
    // Default Permissions
    $permissions = [
        'pos_access' => ['admin' => 1, 'manager' => 1, 'user' => 1],
        'sale_delete' => ['admin' => 1, 'manager' => 0, 'user' => 0],
        'sale_create' => ['admin' => 1, 'manager' => 1, 'user' => 1],
        'transaction_view' => ['admin' => 1, 'manager' => 1, 'user' => 1],
        'transaction_create' => ['admin' => 1, 'manager' => 1, 'user' => 0],
        'transaction_delete' => ['admin' => 1, 'manager' => 0, 'user' => 0],
        'report_view' => ['admin' => 1, 'manager' => 1, 'user' => 0],
        'user_manage' => ['admin' => 1, 'manager' => 0, 'user' => 0],
        'company_manage' => ['admin' => 1, 'manager' => 0, 'user' => 0],
        'settings_manage' => ['admin' => 1, 'manager' => 0, 'user' => 0],
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role, permission_key, is_granted) VALUES (?, ?, ?)");
    
    foreach ($permissions as $perm => $roles) {
        foreach ($roles as $role => $granted) {
            $stmt->execute([$role, $perm, $granted]);
        }
    }
    
    echo "Default permissions seeded.";
    
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
