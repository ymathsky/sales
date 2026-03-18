<?php
/**
 * Common Helper Functions
 */

/**
 * Format money amount with currency
 * 
 * @param float $amount Amount to format
 * @param string $currency Currency symbol
 * @return string Formatted amount
 */
function formatMoney($amount, $currency = '₱') {
    return $currency . number_format($amount, 2);
}

/**
 * Format date for display
 * 
 * @param string $date Date string
 * @param string $format Display format
 * @return string Formatted date
 */
function formatDate($date, $format = 'Y-m-d') {
    return date($format, strtotime($date));
}

/**
 * Sanitize input string
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate decimal amount
 * 
 * @param mixed $amount Amount to validate
 * @return bool True if valid
 */
function isValidAmount($amount) {
    return is_numeric($amount) && $amount > 0;
}

/**
 * Validate date string
 * 
 * @param string $date Date to validate
 * @return bool True if valid
 */
function isValidDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Get transaction type badge HTML
 * 
 * @param string $type Transaction type (in/out)
 * @return string HTML badge
 */
function getTypeBadge($type) {
    $class = $type === 'in' ? 'success' : 'danger';
    $label = $type === 'in' ? 'Cash In' : 'Cash Out';
    // Add inline styles for print
    $bgColor = $type === 'in' ? '#d1fae5' : '#fee2e2';
    $textColor = $type === 'in' ? '#065f46' : '#991b1b';
    $borderColor = $type === 'in' ? '#10b981' : '#ef4444';
    return "<span class='badge badge-{$class}' style='background-color: {$bgColor} !important; color: {$textColor} !important; border: 1px solid {$borderColor}; padding: 4px 12px; border-radius: 4px; font-weight: 700; display: inline-block; -webkit-print-color-adjust: exact; print-color-adjust: exact;'>{$label}</span>";
}

/**
 * Set flash message
 * 
 * @param string $message Message text
 * @param string $type Message type (success, error, warning, info)
 */
function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = [
        'text' => $message,
        'type' => $type
    ];
}

/**
 * Check if flash message exists
 * 
 * @return bool True if flash message exists
 */
function hasFlashMessage() {
    return isset($_SESSION['flash_message']);
}

/**
 * Get and clear flash message
 * 
 * @return array|null Flash message or null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return [
            'message' => $message['text'],
            'type' => $message['type']
        ];
    }
    return null;
}

/**
 * Redirect to URL
 * 
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header("Location: {$url}");
    exit;
}

/**
 * Generate CSRF token
 * 
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token to verify
 * @return bool True if valid
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
