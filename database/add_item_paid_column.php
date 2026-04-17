<?php
require_once __DIR__ . '/../config/database.php';

echo "<pre>\n";
echo "=== Migration: add is_paid column to invoice_items ===\n\n";

try {
    $pdo = getDBConnection();

    // Check if column already exists
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'invoice_items'
          AND COLUMN_NAME  = 'is_paid'
    ");
    $stmt->execute();
    $exists = (int) $stmt->fetchColumn();

    if ($exists > 0) {
        echo "✓ Column 'is_paid' already exists on invoice_items. Nothing to do.\n";
    } else {
        $pdo->exec("ALTER TABLE invoice_items
                    ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 0 AFTER amount");
        echo "✓ Column 'is_paid' added to invoice_items successfully.\n";
    }

    echo "\nMigration completed.\n";

} catch (PDOException $e) {
    echo "✗ Migration failed: " . htmlspecialchars($e->getMessage()) . "\n";
}

echo "</pre>\n";
?>
