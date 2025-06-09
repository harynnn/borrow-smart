<?php
class SecurityHelper {
    private $pdo;
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 900; // 15 minutes in seconds
    private $ipBlockDuration = 3600; // 1 hour in seconds
    private $twoFactorExpiry = 300; // 5 minutes in seconds

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Generate CSRF token
     */
    public function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Initialize session security
     */
    public function initializeSession($userId) {
        // Regenerate session ID
        session_regenerate_id(true);

        // Set session variables
        $_SESSION['last_activity'] = time();
        $_SESSION['created'] = time();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];

        // Store session in database
        $stmt = $this->pdo->prepare("
            INSERT INTO sessions (
                user_id, session_id, ip_address, device_info, last_activity
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            session_id(),
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    }

    /**
     * Update session activity
     */
    public function updateSessionActivity() {
        if (isset($_SESSION['uid'])) {
            $stmt = $this->pdo->prepare("
                UPDATE sessions 
                SET last_activity = NOW()
                WHERE session_id = ?
            ");
            $stmt->execute([session_id()]);
        }
    }

    /**
     * Check if IP is blocked
     */
    public function isIPBlocked() {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM ip_blacklist 
            WHERE ip_address = ? 
            AND (expires_at > NOW() OR expires_at IS NULL)
        ");
        $stmt->execute([$_SERVER['REMOTE_ADDR']]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Check if account is locked
     */
    public function isAccountLocked($userId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM failed_logins 
            WHERE user_id = ? 
            AND attempt_count >= ? 
            AND last_attempt > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$userId, $this->maxLoginAttempts, $this->lockoutDuration]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Handle failed login attempt
     */
    public function handleFailedLogin($userId) {
        $stmt = $this->pdo->prepare("
            SELECT id, attempt_count 
            FROM failed_logins 
            WHERE user_id = ? 
            AND ip_address = ? 
            AND last_attempt > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$userId, $_SERVER['REMOTE_ADDR'], $this->lockoutDuration]);
        $failedLogin = $stmt->fetch();

        if ($failedLogin) {
            // Update existing record
            $newCount = $failedLogin['attempt_count'] + 1;
            $stmt = $this->pdo->prepare("
                UPDATE failed_logins 
                SET attempt_count = ?, last_attempt = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$newCount, $failedLogin['id']]);

            // Block IP if too many attempts
            if ($newCount >= $this->maxLoginAttempts) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO ip_blacklist (
                        ip_address, reason, expires_at
                    ) VALUES (
                        ?, 'Too many failed login attempts', 
                        DATE_ADD(NOW(), INTERVAL ? SECOND)
                    )
                    ON DUPLICATE KEY UPDATE 
                        expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
                ");
                $stmt->execute([
                    $_SERVER['REMOTE_ADDR'],
                    $this->ipBlockDuration,
                    $this->ipBlockDuration
                ]);
            }
        } else {
            // Create new record
            $stmt = $this->pdo->prepare("
                INSERT INTO failed_logins (
                    user_id, ip_address, attempt_count, last_attempt
                ) VALUES (?, ?, 1, NOW())
            ");
            $stmt->execute([$userId, $_SERVER['REMOTE_ADDR']]);
        }
    }

    /**
     * Reset failed login attempts
     */
    public function resetFailedAttempts($userId) {
        $stmt = $this->pdo->prepare("
            DELETE FROM failed_logins 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
    }

    /**
     * Generate 2FA code
     */
    public function generate2FACode($userId) {
        // Generate random 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store code in database
        $stmt = $this->pdo->prepare("
            INSERT INTO two_factor_codes (
                user_id, code, expires_at
            ) VALUES (
                ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND)
            )
        ");
        $stmt->execute([
            $userId,
            password_hash($code, PASSWORD_DEFAULT),
            $this->twoFactorExpiry
        ]);

        return $code;
    }

    /**
     * Verify 2FA code
     */
    public function verify2FACode($userId, $code) {
        $stmt = $this->pdo->prepare("
            SELECT id, code 
            FROM two_factor_codes 
            WHERE user_id = ? 
            AND used = 0 
            AND expires_at > NOW()
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $twoFactor = $stmt->fetch();

        if (!$twoFactor || !password_verify($code, $twoFactor['code'])) {
            return false;
        }

        // Mark code as used
        $stmt = $this->pdo->prepare("
            UPDATE two_factor_codes 
            SET used = 1, used_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$twoFactor['id']]);

        return true;
    }

    /**
     * Log security event
     */
    public function logSecurityEvent($userId, $eventType, $description) {
        $stmt = $this->pdo->prepare("
            INSERT INTO security_logs (
                user_id, event_type, description, ip_address, device_info
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $eventType,
            $description,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    }

    /**
     * Validate session security
     */
    public function validateSession() {
        // Check if security-related session variables are set
        if (!isset($_SESSION['created']) || !isset($_SESSION['last_activity']) ||
            !isset($_SESSION['user_agent']) || !isset($_SESSION['ip_address'])) {
            return false;
        }

        // Check session age
        if (time() - $_SESSION['created'] > SESSION_LIFETIME) {
            return false;
        }

        // Check for session hijacking
        if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] ||
            $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            return false;
        }

        // Check session activity
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            return false;
        }

        // Update last activity
        $_SESSION['last_activity'] = time();

        return true;
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupSessions() {
        // Remove expired sessions
        $stmt = $this->pdo->prepare("
            DELETE FROM sessions 
            WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([SESSION_LIFETIME]);

        // Remove expired remember tokens
        $stmt = $this->pdo->prepare("
            DELETE FROM remember_tokens 
            WHERE expires_at < NOW()
        ");
        $stmt->execute();

        // Remove expired 2FA codes
        $stmt = $this->pdo->prepare("
            DELETE FROM two_factor_codes 
            WHERE expires_at < NOW()
        ");
        $stmt->execute();

        // Remove expired IP blocks
        $stmt = $this->pdo->prepare("
            DELETE FROM ip_blacklist 
            WHERE expires_at < NOW()
        ");
        $stmt->execute();

        // Remove old failed login attempts
        $stmt = $this->pdo->prepare("
            DELETE FROM failed_logins 
            WHERE last_attempt < DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$this->lockoutDuration]);
    }

    /**
     * Validate password strength
     */
    public function validatePassword($password) {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must include at least one uppercase letter";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must include at least one lowercase letter";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must include at least one number";
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must include at least one special character";
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Generate random string
     */
    public function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Clean input data
     */
    public function cleanInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}
?>
