<?php
// Prevent direct access
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

// Error reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Set internal character encoding
mb_internal_encoding('UTF-8');

// Load configuration
require_once 'config.php';

// Load database connection
require_once 'dbconnection.php';

// Load helper functions
require_once 'functions.php';

// Load security helper
require_once dirname(__DIR__) . '/security_helper.php';
$securityHelper = new SecurityHelper($pdo);

// Load session handler
require_once 'session_handler.php';
SessionHandler::initSession();

// Load middleware
require_once dirname(__DIR__) . '/middleware.php';

// Check maintenance mode
if (MAINTENANCE_MODE && 
    !in_array($_SERVER['REMOTE_ADDR'], MAINTENANCE_ALLOWED_IPS) && 
    basename($_SERVER['PHP_SELF']) !== 'maintenance.php') {
    header('Location: maintenance.php');
    exit();
}

// Check for remembered user
if (!SessionHandler::isAuthenticated() && SessionHandler::hasRememberToken()) {
    $remember_token = SessionHandler::getRememberToken();
    
    // Find valid remember token
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.role_id 
        JOIN remember_tokens rt ON u.uid = rt.user_id 
        WHERE rt.token = ? 
        AND rt.expires_at > NOW()
    ");
    $stmt->execute([password_hash($remember_token, PASSWORD_DEFAULT)]);
    $user = $stmt->fetch();

    if ($user) {
        // Start session for remembered user
        session_regenerate_id(true);
        $_SESSION['uid'] = $user['uid'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role_name'];
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();

        // Update last login
        $stmt = $pdo->prepare("
            UPDATE users 
            SET last_login = NOW() 
            WHERE uid = ?
        ");
        $stmt->execute([$user['uid']]);

        // Log auto-login
        $securityHelper->logSecurityEvent(
            $user['uid'],
            'AUTO_LOGIN',
            'Auto-login via remember token from ' . $_SERVER['REMOTE_ADDR']
        );
    }
}

// Force HTTPS
if (HTTPS_ENABLED && !isset($_SERVER['HTTPS'])) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}

// Check if password change is required
if (SessionHandler::isAuthenticated() && 
    basename($_SERVER['PHP_SELF']) !== 'change_password.php') {
    
    $stmt = $pdo->prepare("
        SELECT force_password_change, 
               password_changed_at,
               DATEDIFF(NOW(), password_changed_at) as days_since_change
        FROM users 
        WHERE uid = ?
    ");
    $stmt->execute([$_SESSION['uid']]);
    $result = $stmt->fetch();

    if ($result['force_password_change'] || 
        ($result['password_changed_at'] && $result['days_since_change'] > PASSWORD_EXPIRY_DAYS)) {
        $_SESSION['error'] = "For security reasons, you must change your password.";
        header('Location: change_password.php');
        exit();
    }
}

// Check account status
if (SessionHandler::isAuthenticated() && 
    basename($_SERVER['PHP_SELF']) !== 'logout.php') {
    
    $stmt = $pdo->prepare("
        SELECT status 
        FROM users 
        WHERE uid = ?
    ");
    $stmt->execute([$_SESSION['uid']]);
    $status = $stmt->fetchColumn();

    if ($status !== 'active') {
        SessionHandler::destroySession();
        header('Location: login.php?inactive=1');
        exit();
    }
}

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || 
        !$securityHelper->verifyCsrfToken($_POST['csrf_token'])) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}

/**
 * Generate CSRF field
 */
function csrf_field() {
    global $securityHelper;
    return '<input type="hidden" name="csrf_token" value="' . 
           $securityHelper->generateCsrfToken() . '">';
}

/**
 * Get Gravatar URL
 */
function getGravatarUrl($email, $size = 80) {
    global $securityHelper;
    return $securityHelper->getGravatarUrl($email, $size);
}

/**
 * Format date/time
 */
function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
    global $securityHelper;
    return $securityHelper->formatDateTime($datetime, $format);
}

/**
 * Get time ago
 */
function timeAgo($datetime) {
    global $securityHelper;
    return $securityHelper->timeAgo($datetime);
}

/**
 * Get department name
 */
function getDepartmentName($code) {
    global $securityHelper;
    return $securityHelper->getDepartmentName($code);
}

/**
 * Get password requirements HTML
 */
function getPasswordRequirements() {
    global $securityHelper;
    return $securityHelper->getPasswordRequirements();
}
?>
