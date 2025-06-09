<?php
// Prevent direct access
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

// Environment
define('APP_ENV', 'development'); // 'development' or 'production'
define('APP_DEBUG', APP_ENV === 'development');
define('APP_URL', 'http://localhost:8000');
define('APP_NAME', 'BorrowSmart');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'borrowsmart');
define('DB_USER', 'root');
define('DB_PASS', '');

// Session configuration
define('SESSION_LIFETIME', 3600); // 1 hour in seconds
define('SESSION_TIMEOUT', 900);   // 15 minutes in seconds
define('REMEMBER_ME_EXPIRY', 2592000); // 30 days in seconds

// Cookie configuration
define('COOKIE_PATH', '/');
define('COOKIE_DOMAIN', '');
define('COOKIE_SECURE', true);
define('COOKIE_HTTPONLY', true);

// SMTP configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-specific-password');
define('SMTP_FROM_EMAIL', 'noreply@borrowsmart.uthm.edu.my');
define('SMTP_FROM_NAME', 'BorrowSmart System');

// File upload configuration
define('UPLOAD_MAX_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_FILE_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
]);
define('UPLOAD_PATH', __DIR__ . '/uploads');

// Security configuration
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_MAX_LENGTH', 72);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_DURATION', 900); // 15 minutes in seconds
define('IP_BLOCK_DURATION', 3600);     // 1 hour in seconds
define('TWO_FACTOR_EXPIRY', 300);      // 5 minutes in seconds

// Maintenance configuration
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', 'System is currently under maintenance. Please try again later.');
define('MAINTENANCE_ALLOWED_IPS', [
    '127.0.0.1',
    '::1'
]);

// Logging configuration
define('LOG_PATH', __DIR__ . '/logs');
define('ERROR_LOG', LOG_PATH . '/error.log');
define('ACCESS_LOG', LOG_PATH . '/access.log');
define('SECURITY_LOG', LOG_PATH . '/security.log');

// API configuration
define('API_VERSION', '1.0');
define('API_RATE_LIMIT', 60); // requests per minute
define('API_TIMEOUT', 30);    // seconds

// Cache configuration
define('CACHE_ENABLED', true);
define('CACHE_PATH', __DIR__ . '/cache');
define('CACHE_LIFETIME', 3600); // 1 hour in seconds

// Pagination configuration
define('ITEMS_PER_PAGE', 10);
define('MAX_PAGE_LINKS', 5);

// Date/Time configuration
define('DEFAULT_TIMEZONE', 'Asia/Kuala_Lumpur');
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i:s');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');

// System paths
define('ROOT_PATH', __DIR__);
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// URLs
define('ASSETS_URL', APP_URL . '/assets');
define('CSS_URL', ASSETS_URL . '/css');
define('JS_URL', ASSETS_URL . '/js');
define('IMAGES_URL', ASSETS_URL . '/images');

// Contact information
define('ADMIN_EMAIL', 'admin@borrowsmart.uthm.edu.my');
define('SUPPORT_EMAIL', 'support@borrowsmart.uthm.edu.my');
define('CONTACT_PHONE', '+60-7-453-7000');
define('CONTACT_ADDRESS', 'Universiti Tun Hussein Onn Malaysia, 86400 Parit Raja, Batu Pahat, Johor, Malaysia');

// Social media
define('FACEBOOK_URL', 'https://www.facebook.com/UTHM.Official/');
define('TWITTER_URL', 'https://twitter.com/UTHM_OFFICIAL');
define('INSTAGRAM_URL', 'https://www.instagram.com/uthm.official/');
define('YOUTUBE_URL', 'https://www.youtube.com/user/uthmtv');

// System limits
define('MAX_ACTIVE_REQUESTS', 3);
define('MAX_BORROW_DAYS', 14);
define('MAX_RENEWAL_TIMES', 1);
define('LATE_RETURN_PENALTY', 5.00); // RM per day

// Notification settings
define('EMAIL_NOTIFICATIONS', true);
define('SMS_NOTIFICATIONS', false);
define('PUSH_NOTIFICATIONS', false);
define('NOTIFICATION_TYPES', [
    'request_approved',
    'request_rejected',
    'return_reminder',
    'overdue_notice',
    'maintenance_reminder',
    'system_alert'
]);

// Create required directories if they don't exist
$directories = [
    LOG_PATH,
    UPLOAD_PATH,
    CACHE_PATH
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Set default timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// Error reporting based on environment
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Initialize error log
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG);
?>
