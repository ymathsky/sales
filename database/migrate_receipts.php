<?php
require_once __DIR__ . '/../config/database.php';

echo "Starting migration...\n";

try {
    $pdo = getDBConnection();
    
    // Create table
    $sql = "CREATE TABLE IF NOT EXISTS transaction_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        original_name VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "Table 'transaction_receipts' created or already exists.\n";

    // Migrate data
    // We select distinct to avoid potential duplicates if re-run, though the WHERE clause handles it
    // Check if we need to migrate
    $count = $pdo->query("SELECT COUNT(*) FROM transaction_receipts")->fetchColumn();
    
    if ($count == 0) {
        $sql = "INSERT INTO transaction_receipts (transaction_id, file_path, original_name)
                SELECT transaction_id, receipt_path, SUBSTRING_INDEX(receipt_path, '/', -1)
                FROM transactions 
                WHERE receipt_path IS NOT NULL AND receipt_path != ''";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        echo "Migrated " . $stmt->rowCount() . " existing receipts.\n";
    } else {
        echo "Data already seems migrated ($count records found).\n";
    }
    
    echo "Migration completed successfully.\n";
    
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>