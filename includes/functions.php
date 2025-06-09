<?php
// Prevent direct access
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

/**
 * Format date/time
 */
function formatDateTime($datetime, $format = null) {
    if (!$datetime) return '';
    $dt = new DateTime($datetime);
    return $dt->format($format ?? DATETIME_FORMAT);
}

/**
 * Format date
 */
function formatDate($date, $format = null) {
    if (!$date) return '';
    $dt = new DateTime($date);
    return $dt->format($format ?? DATE_FORMAT);
}

/**
 * Format time
 */
function formatTime($time, $format = null) {
    if (!$time) return '';
    $dt = new DateTime($time);
    return $dt->format($format ?? TIME_FORMAT);
}

/**
 * Format currency
 */
function formatCurrency($amount, $decimals = 2) {
    return 'RM ' . number_format($amount, $decimals);
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Generate pagination links
 */
function generatePagination($currentPage, $totalPages, $urlPattern) {
    $links = [];
    $halfMax = floor(MAX_PAGE_LINKS / 2);
    $start = max(1, $currentPage - $halfMax);
    $end = min($totalPages, $start + MAX_PAGE_LINKS - 1);

    if ($end - $start + 1 < MAX_PAGE_LINKS) {
        $start = max(1, $end - MAX_PAGE_LINKS + 1);
    }

    // Previous link
    if ($currentPage > 1) {
        $links[] = [
            'url' => sprintf($urlPattern, $currentPage - 1),
            'label' => '&laquo; Previous',
            'active' => false
        ];
    }

    // First page
    if ($start > 1) {
        $links[] = [
            'url' => sprintf($urlPattern, 1),
            'label' => '1',
            'active' => false
        ];
        if ($start > 2) {
            $links[] = ['url' => '', 'label' => '...', 'active' => false];
        }
    }

    // Page links
    for ($i = $start; $i <= $end; $i++) {
        $links[] = [
            'url' => sprintf($urlPattern, $i),
            'label' => $i,
            'active' => $i === $currentPage
        ];
    }

    // Last page
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $links[] = ['url' => '', 'label' => '...', 'active' => false];
        }
        $links[] = [
            'url' => sprintf($urlPattern, $totalPages),
            'label' => $totalPages,
            'active' => false
        ];
    }

    // Next link
    if ($currentPage < $totalPages) {
        $links[] = [
            'url' => sprintf($urlPattern, $currentPage + 1),
            'label' => 'Next &raquo;',
            'active' => false
        ];
    }

    return $links;
}

/**
 * Get user role name
 */
function getUserRole($roleId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT role_name FROM roles WHERE role_id = ?");
    $stmt->execute([$roleId]);
    return $stmt->fetchColumn();
}

/**
 * Get user name
 */
function getUserName($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT name FROM users WHERE uid = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

/**
 * Get department name
 */
function getDepartmentName($code) {
    return DEPARTMENTS[$code] ?? $code;
}

/**
 * Generate random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate matric number
 */
function validateMatric($matric) {
    return preg_match('/^[A-Z][A-Z][0-9]{2}[A-Z]{2}[0-9]{4}$/', $matric);
}

/**
 * Get client IP
 */
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

/**
 * Get client device info
 */
function getClientDevice() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Send JSON response
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Redirect with message
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION[$type] = $message;
    header('Location: ' . $url);
    exit();
}

/**
 * Get flash message
 */
function getFlashMessage($type) {
    if (isset($_SESSION[$type])) {
        $message = $_SESSION[$type];
        unset($_SESSION[$type]);
        return $message;
    }
    return null;
}

/**
 * Check maintenance mode
 */
function isMaintenanceMode() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT setting_value 
        FROM settings 
        WHERE setting_key = 'system_maintenance_mode'
    ");
    $stmt->execute();
    return $stmt->fetchColumn() === 'true';
}

/**
 * Check if IP is allowed during maintenance
 */
function isAllowedIP($ip) {
    return in_array($ip, MAINTENANCE_ALLOWED_IPS);
}

/**
 * Log system activity
 */
function logActivity($userId, $action, $details = null) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (
            user_id, action, details, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $action,
        $details,
        getClientIP(),
        getClientDevice()
    ]);
}

/**
 * Check if user has permission
 */
function hasPermission($permission) {
    global $pdo;
    if (!isset($_SESSION['uid']) || !isset($_SESSION['role'])) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.permission_id
        JOIN roles r ON rp.role_id = r.role_id
        WHERE r.role_name = ? AND p.permission_name = ?
    ");
    $stmt->execute([$_SESSION['role'], $permission]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Sanitize output
 */
function sanitizeOutput($output) {
    return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate breadcrumbs
 */
function generateBreadcrumbs($items) {
    $html = '<nav class="text-sm" aria-label="Breadcrumb">';
    $html .= '<ol class="list-none p-0 inline-flex">';
    
    foreach ($items as $index => $item) {
        $isLast = $index === count($items) - 1;
        
        $html .= '<li class="flex items-center">';
        
        if (!$isLast) {
            $html .= '<a href="' . $item['url'] . '" class="text-gray-600 hover:text-gray-900">';
            $html .= sanitizeOutput($item['label']);
            $html .= '</a>';
            $html .= '<svg class="h-5 w-5 text-gray-400 mx-2" fill="currentColor" viewBox="0 0 20 20">';
            $html .= '<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>';
            $html .= '</svg>';
        } else {
            $html .= '<span class="text-gray-900">' . sanitizeOutput($item['label']) . '</span>';
        }
        
        $html .= '</li>';
    }
    
    $html .= '</ol>';
    $html .= '</nav>';
    
    return $html;
}

/**
 * Get setting value
 */
function getSetting($key, $default = null) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT setting_value 
        FROM settings 
        WHERE setting_key = ?
    ");
    $stmt->execute([$key]);
    return $stmt->fetchColumn() ?: $default;
}

/**
 * Update setting
 */
function updateSetting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    return $stmt->execute([$key, $value]);
}

/**
 * Get notification count
 */
function getUnreadNotificationCount($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM notifications 
        WHERE user_id = ? AND read = 0
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

/**
 * Send notification
 */
function sendNotification($userId, $type, $title, $message) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_id, type, title, message
        ) VALUES (?, ?, ?, ?)
    ");
    return $stmt->execute([$userId, $type, $title, $message]);
}
?>
