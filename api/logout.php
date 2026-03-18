<?php
/**
 * API Logout Endpoint
 */

require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json');

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy session
session_unset();
session_destroy();

echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully'
]);
