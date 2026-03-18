<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    $sql = file_get_contents(__DIR__ . '/../database/memberships.sql');
    
    // Split by semicolon to handle multiple statements if PDO doesn't like batch
    // specific to some drivers, but standard PDO usually handles multiple if emulation is on.
    // Let's safe-split just in case or just run raw.
    $pdo->exec($sql);
    
    echo "Membership tables created successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
