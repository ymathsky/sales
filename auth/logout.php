<?php
/**
 * Logout
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Clear session
session_destroy();

// Redirect to login
redirect('/sales/auth/login.php');
