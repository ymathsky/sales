<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Diagnostics</h2>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";

// Check config file
$configPath = __DIR__ . '/config/database.php';
echo "<p>Config file exists: " . (file_exists($configPath) ? '<b style="color:green">YES</b>' : '<b style="color:red">NO - You need to create config/database.php from database.sample.php</b>') . "</p>";

if (file_exists($configPath)) {
    require_once $configPath;
    echo "<p>Config loaded OK</p>";
    echo "<p>WEB_ROOT = '" . (defined('WEB_ROOT') ? WEB_ROOT : 'NOT DEFINED') . "'</p>";
    echo "<p>DB_NAME = '" . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "'</p>";

    // Test DB connection
    try {
        $pdo = getDBConnection();
        echo "<p style='color:green'><b>Database connection: OK</b></p>";
        
        // Check if tables exist
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Tables found: " . implode(', ', $tables) . "</p>";
        if (empty($tables)) {
            echo "<p style='color:red'><b>No tables found! You need to import database/schema.sql</b></p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'><b>Database error: " . htmlspecialchars($e->getMessage()) . "</b></p>";
    }
}

// Check session
echo "<p>Session status: " . session_status() . " (1=disabled, 2=active)</p>";
