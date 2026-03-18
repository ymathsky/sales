<?php
require 'c:\xampp\htdocs\sales\config\database.php';
try {
    $pdo = getDBConnection();
    // Check if table exists first
    $stmt = $pdo->query("SHOW TABLES LIKE 'membership_plans'");
    if ($stmt->rowCount() == 0) {
        echo "Table membership_plans does not exist.\n";
    } else {
        $stmt = $pdo->query('SELECT * FROM membership_plans');
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Found " . count($plans) . " plans.\n";
        print_r($plans);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
