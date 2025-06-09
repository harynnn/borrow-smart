<?php
// Prevent direct access
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 3600);
    
    session_start();
}

// Load configuration
require_once __DIR__ . '/../config.php';

// Database connection
require_once __DIR__ . '/../dbconnection.php';

// Load helpers
require_once __DIR__ . '/../security_helper.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session_handler.php';

// Initialize security helper
$securityHelper = new SecurityHelper($pdo);

// Initialize custom session handler
$sessionHandler = new SessionHandler($pdo, SESSION_LIFETIME);
session_set_save_handler($sessionHandler, true);

// Constants
define('DEPARTMENTS', [
    'FKEE' => 'Faculty of Electrical and Electronic Engineering',
    'FKMP' => 'Faculty of Mechanical and Manufacturing Engineering',
    'FSKTM' => 'Faculty of Computing',
    'FKAAB' => 'Faculty of Civil Engineering and Built Environment',
    'FTK' => 'Faculty of Engineering Technology',
    'FPTP' => 'Faculty of Technology Management and Business',
    'FPTV' => 'Faculty of Technical and Vocational Education',
    'FAST' => 'Faculty of Applied Sciences and Technology'
]);

// Maintenance mode check
$stmt = $pdo->prepare("
    SELECT setting_value 
    FROM settings 
    WHERE setting_key = 'system_maintenance_mode'
");
$stmt->execute();
$maintenanceMode = $stmt->fetchColumn() === 'true';

if ($maintenanceMode && !in_array($_SERVER['PHP_SELF'], ['/maintenance.php', '/admin_login.php'])) {
    header('Location: maintenance.php');
    exit();
}

// Session security validation
if (isset($_SESSION['uid'])) {
    if (!$securityHelper->validateSession()) {
        // Log the security event
        $securityHelper->logSecurityEvent(
            $_SESSION['uid'],
            'SESSION_INVALID',
            'Invalid session detected'
        );

        // Clear session
        $_SESSION = array();
        session_destroy();

        // Redirect to login with security flag
        header('Location: login.php?security=true');
        exit();
    }

    // Update session activity
    $securityHelper->updateSessionActivity();
}

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$securityHelper->verifyCsrfToken($_POST['csrf_token'])) {
        // Log the security event
        if (isset($_SESSION['uid'])) {
            $securityHelper->logSecurityEvent(
                $_SESSION['uid'],
                'CSRF_ATTEMPT',
                'Invalid CSRF token detected'
            );
        }

        // Return 403 Forbidden
        header('HTTP/1.0 403 Forbidden');
        exit('Invalid request.');
    }
}

// Helper Functions
function csrf_field() {
    global $securityHelper;
    return '<input type="hidden" name="csrf_token" value="' . $securityHelper->generateCsrfToken() . '">';
}

function getPasswordRequirements() {
    return '
    <div class="mt-1 text-xs text-gray-500">
        Password must contain:
        <ul class="list-disc list-inside">
            <li>At least 8 characters</li>
            <li>At least one uppercase letter</li>
            <li>At least one lowercase letter</li>
            <li>At least one number</li>
            <li>At least one special character</li>
        </ul>
    </div>';
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        return date('F j, Y', $time);
    }
}

function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Cleanup tasks (run occasionally)
if (mt_rand(1, 100) === 1) {
    $securityHelper->cleanupSessions();
}

// Set default timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Set headers for security
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header("Content-Security-Policy: default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval'");
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
?>
