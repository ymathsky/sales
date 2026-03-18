<?php
/**
 * Diagnostic Script for Upload Issues
 * Run this file in your browser to check upload directory permissions
 * URL: http://yoursite.com/sales/check-uploads.php
 */

// Security: Only allow admin access (uncomment if you want to restrict)
// require_once __DIR__ . '/includes/session.php';
// requireAdmin();

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Upload Directory Diagnostic Test</h2>";
echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
.success { color: #10b981; background: #d1fae5; padding: 10px; margin: 10px 0; border-radius: 5px; }
.error { color: #ef4444; background: #fee2e2; padding: 10px; margin: 10px 0; border-radius: 5px; }
.warning { color: #f59e0b; background: #fef3c7; padding: 10px; margin: 10px 0; border-radius: 5px; }
.info { color: #3b82f6; background: #dbeafe; padding: 10px; margin: 10px 0; border-radius: 5px; }
pre { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 5px; overflow-x: auto; }
</style>";

$uploadsPath = __DIR__ . '/uploads';
$receiptsPath = __DIR__ . '/uploads/receipts';

echo "<h3>1. Checking PHP Upload Settings</h3>";
echo "<div class='info'>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'Enabled ✓' : 'Disabled ✗') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
echo "upload_tmp_dir: " . (ini_get('upload_tmp_dir') ?: 'System default') . "<br>";
echo "</div>";

echo "<h3>2. Checking Directory Existence</h3>";

// Check uploads directory
if (is_dir($uploadsPath)) {
    echo "<div class='success'>✓ uploads/ directory EXISTS</div>";
} else {
    echo "<div class='error'>✗ uploads/ directory DOES NOT EXIST</div>";
    echo "<div class='warning'>Attempting to create uploads/ directory...</div>";
    if (mkdir($uploadsPath, 0755, true)) {
        echo "<div class='success'>✓ Successfully created uploads/ directory</div>";
    } else {
        echo "<div class='error'>✗ Failed to create uploads/ directory</div>";
        echo "<div class='error'>You need to manually create this directory via FTP/cPanel File Manager</div>";
    }
}

// Check receipts directory
if (is_dir($receiptsPath)) {
    echo "<div class='success'>✓ uploads/receipts/ directory EXISTS</div>";
} else {
    echo "<div class='error'>✗ uploads/receipts/ directory DOES NOT EXIST</div>";
    echo "<div class='warning'>Attempting to create uploads/receipts/ directory...</div>";
    if (mkdir($receiptsPath, 0755, true)) {
        echo "<div class='success'>✓ Successfully created uploads/receipts/ directory</div>";
    } else {
        echo "<div class='error'>✗ Failed to create uploads/receipts/ directory</div>";
        echo "<div class='error'>You need to manually create this directory via FTP/cPanel File Manager</div>";
    }
}

echo "<h3>3. Checking Directory Permissions</h3>";

if (is_dir($uploadsPath)) {
    echo "<strong>uploads/ directory:</strong><br>";
    echo "<div class='info'>";
    echo "Path: <code>$uploadsPath</code><br>";
    echo "Permissions: " . substr(sprintf('%o', fileperms($uploadsPath)), -4) . "<br>";
    echo "Owner: " . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($uploadsPath))['name'] : 'N/A') . "<br>";
    echo "Readable: " . (is_readable($uploadsPath) ? 'Yes ✓' : 'No ✗') . "<br>";
    echo "Writable: " . (is_writable($uploadsPath) ? 'Yes ✓' : 'No ✗') . "<br>";
    echo "</div>";
    
    if (!is_writable($uploadsPath)) {
        echo "<div class='error'>✗ uploads/ is NOT WRITABLE - This is the problem!</div>";
        echo "<div class='warning'><strong>Fix via FTP/cPanel:</strong><br>";
        echo "1. Right-click uploads/ directory<br>";
        echo "2. Choose 'Change Permissions' or 'File Permissions'<br>";
        echo "3. Set to 755 (rwxr-xr-x) or 775 (rwxrwxr-x)<br>";
        echo "4. Check 'Apply to directories recursively'</div>";
    }
}

if (is_dir($receiptsPath)) {
    echo "<br><strong>uploads/receipts/ directory:</strong><br>";
    echo "<div class='info'>";
    echo "Path: <code>$receiptsPath</code><br>";
    echo "Permissions: " . substr(sprintf('%o', fileperms($receiptsPath)), -4) . "<br>";
    echo "Owner: " . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($receiptsPath))['name'] : 'N/A') . "<br>";
    echo "Readable: " . (is_readable($receiptsPath) ? 'Yes ✓' : 'No ✗') . "<br>";
    echo "Writable: " . (is_writable($receiptsPath) ? 'Yes ✓' : 'No ✗') . "<br>";
    echo "</div>";
    
    if (!is_writable($receiptsPath)) {
        echo "<div class='error'>✗ uploads/receipts/ is NOT WRITABLE - This is the problem!</div>";
        echo "<div class='warning'><strong>Fix via FTP/cPanel:</strong><br>";
        echo "1. Right-click uploads/receipts/ directory<br>";
        echo "2. Choose 'Change Permissions' or 'File Permissions'<br>";
        echo "3. Set to 755 (rwxr-xr-x) or 775 (rwxrwxr-x)</div>";
    }
}

echo "<h3>4. Test File Upload</h3>";

if (is_dir($receiptsPath) && is_writable($receiptsPath)) {
    $testFile = $receiptsPath . '/test_' . time() . '.txt';
    $testContent = 'Upload test - ' . date('Y-m-d H:i:s');
    
    if (file_put_contents($testFile, $testContent)) {
        echo "<div class='success'>✓ Successfully wrote test file: " . basename($testFile) . "</div>";
        
        if (unlink($testFile)) {
            echo "<div class='success'>✓ Successfully deleted test file</div>";
            echo "<div class='success'><strong>✓ UPLOAD DIRECTORY IS WORKING CORRECTLY!</strong></div>";
        } else {
            echo "<div class='warning'>⚠ Could not delete test file (but upload works)</div>";
        }
    } else {
        echo "<div class='error'>✗ Failed to write test file</div>";
        echo "<div class='error'>File uploads will NOT work - check permissions above</div>";
    }
} else {
    echo "<div class='error'>✗ Cannot test upload - directory doesn't exist or isn't writable</div>";
}

echo "<h3>5. Server Information</h3>";
echo "<div class='info'>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "<br>";
echo "Current User: " . (function_exists('posix_getpwuid') && function_exists('posix_geteuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user()) . "<br>";
echo "Script Path: " . __FILE__ . "<br>";
echo "</div>";

echo "<hr>";
echo "<h3>Quick Fix Commands (via SSH if you have access):</h3>";
echo "<pre>";
echo "cd " . dirname(__DIR__) . "\n";
echo "mkdir -p uploads/receipts\n";
echo "chmod 755 uploads\n";
echo "chmod 755 uploads/receipts\n";
echo "# Or if 755 doesn't work:\n";
echo "chmod 775 uploads/receipts\n";
echo "</pre>";

echo "<p style='margin-top: 30px; padding: 15px; background: #f0f9ff; border-left: 4px solid #3b82f6;'>";
echo "<strong>Note:</strong> After fixing permissions, delete this file (check-uploads.php) for security reasons.";
echo "</p>";
?>
