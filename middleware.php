<?php
// Prevent direct access
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

class Middleware {
    private $pdo;
    private $securityHelper;
    private $publicPages = [
        'login.php',
        'login_action.php',
        'register.php',
        'registration_action.php',
        'forgotpassword.php',
        'resetpassword_request.php',
        'resetpassword.php',
        'resetpassword_action.php',
        'verify_email.php',
        'verify_2fa.php',
        'resend_2fa.php',
        'unauthorized.php',
        'maintenance.php',
        'error.php'
    ];

    public function __construct($pdo, $securityHelper) {
        $this->pdo = $pdo;
        $this->securityHelper = $securityHelper;
    }

    /**
     * Check if authentication is required
     */
    public function requiresAuth() {
        $currentPage = basename($_SERVER['PHP_SELF']);
        return !in_array($currentPage, $this->publicPages);
    }

    /**
     * Authenticate user
     */
    public function authenticate() {
        // Skip authentication for public pages
        if (!$this->requiresAuth()) {
            return true;
        }

        // Check if user is logged in
        if (!isset($_SESSION['uid'])) {
            // Check for remember me cookie
            if (isset($_COOKIE['remember_token'])) {
                return $this->authenticateRememberToken();
            }

            // Store intended URL
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            
            // Redirect to login
            header('Location: login.php');
            exit;
        }

        // Validate session
        if (!$this->securityHelper->validateSession()) {
            // Session is invalid
            header('Location: logout.php?security=true');
            exit;
        }

        // Check if user account still exists and is active
        $stmt = $this->pdo->prepare("
            SELECT status 
            FROM users 
            WHERE uid = ?
        ");
        $stmt->execute([$_SESSION['uid']]);
        $status = $stmt->fetchColumn();

        if (!$status || $status !== 'active') {
            header('Location: logout.php');
            exit;
        }

        return true;
    }

    /**
     * Authenticate using remember token
     */
    private function authenticateRememberToken() {
        $token = $_COOKIE['remember_token'];

        $stmt = $this->pdo->prepare("
            SELECT u.* 
            FROM users u
            JOIN remember_tokens rt ON u.uid = rt.user_id
            WHERE rt.token = ? 
            AND rt.expires_at > NOW()
            AND u.status = 'active'
        ");
        $stmt->execute([password_hash($token, PASSWORD_DEFAULT)]);
        $user = $stmt->fetch();

        if ($user) {
            // Set session variables
            $_SESSION['uid'] = $user['uid'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $this->getRoleName($user['role_id']);

            // Initialize session security
            $this->securityHelper->initializeSession($user['uid']);

            // Generate new remember token
            $newToken = generateRandomString(32);
            $expires = time() + REMEMBER_ME_EXPIRY;

            // Update token in database
            $stmt = $this->pdo->prepare("
                UPDATE remember_tokens 
                SET token = ?, 
                    expires_at = FROM_UNIXTIME(?)
                WHERE user_id = ?
            ");
            $stmt->execute([
                password_hash($newToken, PASSWORD_DEFAULT),
                $expires,
                $user['uid']
            ]);

            // Set new cookie
            setcookie(
                'remember_token',
                $newToken,
                $expires,
                COOKIE_PATH,
                COOKIE_DOMAIN,
                COOKIE_SECURE,
                COOKIE_HTTPONLY
            );

            return true;
        }

        // Invalid token, remove cookie
        setcookie('remember_token', '', time() - 3600, COOKIE_PATH);
        return false;
    }

    /**
     * Authorize user role
     */
    public function authorize($allowedRoles = []) {
        if (!isset($_SESSION['role'])) {
            header('Location: unauthorized.php');
            exit;
        }

        if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles)) {
            header('Location: unauthorized.php');
            exit;
        }

        return true;
    }

    /**
     * Check specific permission
     */
    public function checkPermission($permission) {
        if (!isset($_SESSION['uid'])) {
            return false;
        }

        return hasPermission($_SESSION['uid'], $permission);
    }

    /**
     * Require specific permission
     */
    public function requirePermission($permission) {
        if (!$this->checkPermission($permission)) {
            header('Location: unauthorized.php');
            exit;
        }
        return true;
    }

    /**
     * Get role name by ID
     */
    private function getRoleName($roleId) {
        $stmt = $this->pdo->prepare("
            SELECT role_name 
            FROM roles 
            WHERE role_id = ?
        ");
        $stmt->execute([$roleId]);
        return $stmt->fetchColumn();
    }

    /**
     * Check maintenance mode
     */
    public function checkMaintenance() {
        if (isMaintenanceMode()) {
            // Allow access to maintenance page and admin users
            $currentPage = basename($_SERVER['PHP_SELF']);
            $allowedPages = ['maintenance.php', 'login.php', 'login_action.php'];
            
            if (!in_array($currentPage, $allowedPages) && 
                (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
                header('Location: maintenance.php');
                exit;
            }
        }
    }

    /**
     * Check working hours
     */
    public function checkWorkingHours() {
        // Skip check for admin users
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return true;
        }

        if (!isWorkingHours()) {
            header('Location: error.php?code=503&message=' . urlencode('System is only available during working hours.'));
            exit;
        }

        return true;
    }

    /**
     * Rate limiting
     */
    public function checkRateLimit($key, $limit, $period = 60) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $cacheKey = "ratelimit:{$key}:{$ip}";
        
        // Get current count from cache
        $count = apcu_fetch($cacheKey) ?: 0;
        
        if ($count >= $limit) {
            header('HTTP/1.1 429 Too Many Requests');
            header('Retry-After: ' . $period);
            exit('Too many requests. Please try again later.');
        }
        
        // Increment counter
        if ($count === 0) {
            apcu_add($cacheKey, 1, $period);
        } else {
            apcu_inc($cacheKey);
        }
        
        return true;
    }

    /**
     * CSRF protection
     */
    public function verifyCsrf() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || 
                !$this->securityHelper->verifyCsrfToken($_POST['csrf_token'])) {
                header('HTTP/1.1 403 Forbidden');
                exit('Invalid CSRF token');
            }
        }
        return true;
    }

    /**
     * Check required parameters
     */
    public function requireParams($params = []) {
        foreach ($params as $param) {
            if (!isset($_REQUEST[$param]) || empty($_REQUEST[$param])) {
                header('Location: error.php?code=400&message=' . urlencode('Missing required parameters.'));
                exit;
            }
        }
        return true;
    }

    /**
     * Validate request method
     */
    public function validateMethod($methods = ['GET']) {
        if (!in_array($_SERVER['REQUEST_METHOD'], $methods)) {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: ' . implode(', ', $methods));
            exit('Method not allowed');
        }
        return true;
    }
}

// Initialize middleware
$middleware = new Middleware($pdo, $securityHelper);

// Run common checks
$middleware->checkMaintenance();
$middleware->verifyCsrf();
$middleware->authenticate();
?>
