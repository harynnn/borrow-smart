<?php
class SecurityHelper {
    private $pdo;
    private $max_attempts = 5;
    private $lockout_time = 900; // 15 minutes in seconds
    private $ip_max_attempts = 50;
    private $ip_lockout_time = 3600; // 1 hour in seconds

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
     * Generate random string
     */
    public function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Generate 2FA code
     */
    public function generate2FACode($user_id) {
        // Generate 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store code in database
        $stmt = $this->pdo->prepare("
            INSERT INTO two_factor_codes (
                user_id, code, expires_at
            ) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))
        ");
        $stmt->execute([$user_id, $code]);
        
        return $code;
    }

    /**
     * Verify 2FA code
     */
    public function verify2FACode($user_id, $code) {
        $stmt = $this->pdo->prepare("
            SELECT id 
            FROM two_factor_codes 
            WHERE user_id = ? 
            AND code = ? 
            AND used = 0 
            AND expires_at > NOW()
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_id, $code]);
        $result = $stmt->fetch();

        if ($result) {
            // Mark code as used
            $stmt = $this->pdo->prepare("
                UPDATE two_factor_codes 
                SET used = 1,
                    used_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$result['id']]);
            return true;
        }

        return false;
    }

    /**
     * Initialize session security
     */
    public function initializeSession($user_id) {
        // Regenerate session ID
        session_regenerate_id(true);

        // Store session in database
        $stmt = $this->pdo->prepare("
            INSERT INTO sessions (
                session_id, user_id, ip_address, user_agent, last_activity
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            session_id(),
            $user_id,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        // Set session security flags
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    }

    /**
     * Validate session security
     */
    public function validateSession() {
        if (!isset($_SESSION['uid'])) {
            return false;
        }

        // Check session lifetime
        if (isset($_SESSION['created_at']) && 
            (time() - $_SESSION['created_at'] > SESSION_LIFETIME)) {
            return false;
        }

        // Check session inactivity
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > SESSION_INACTIVITY)) {
            return false;
        }

        // Check IP address
        if (isset($_SESSION['ip_address']) && 
            $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            return false;
        }

        // Check user agent
        if (isset($_SESSION['user_agent']) && 
            $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            return false;
        }

        // Update last activity
        $_SESSION['last_activity'] = time();
        $this->updateSessionActivity();

        return true;
    }

    /**
     * Update session activity
     */
    private function updateSessionActivity() {
        $stmt = $this->pdo->prepare("
            UPDATE sessions 
            SET last_activity = NOW()
            WHERE session_id = ?
        ");
        $stmt->execute([session_id()]);
    }

    /**
     * Handle failed login attempt
     */
    public function handleFailedLogin($user_id) {
        // Log failed attempt for user
        $stmt = $this->pdo->prepare("
            INSERT INTO security_logs (
                user_id, event_type, description, ip_address, user_agent
            ) VALUES (?, 'LOGIN_FAILED', 'Failed login attempt', ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        // Check if account should be locked
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM security_logs 
            WHERE user_id = ? 
            AND event_type = 'LOGIN_FAILED'
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$user_id, $this->lockout_time]);
        
        if ($stmt->fetchColumn() >= $this->max_attempts) {
            // Lock account
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET status = 'suspended'
                WHERE uid = ?
            ");
            $stmt->execute([$user_id]);

            // Log account lock
            $this->logSecurityEvent(
                $user_id,
                'ACCOUNT_LOCKED',
                'Account locked due to too many failed login attempts'
            );
        }
    }

    /**
     * Check if account is locked
     */
    public function isAccountLocked($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM security_logs 
            WHERE user_id = ? 
            AND event_type = 'LOGIN_FAILED'
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$user_id, $this->lockout_time]);
        
        return $stmt->fetchColumn() >= $this->max_attempts;
    }

    /**
     * Check if IP is blocked
     */
    public function isIPBlocked() {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM security_logs 
            WHERE ip_address = ? 
            AND event_type = 'LOGIN_FAILED'
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'],
            $this->ip_lockout_time
        ]);
        
        return $stmt->fetchColumn() >= $this->ip_max_attempts;
    }

    /**
     * Reset failed login attempts
     */
    public function resetFailedAttempts($user_id) {
        $stmt = $this->pdo->prepare("
            DELETE FROM security_logs 
            WHERE user_id = ? 
            AND event_type = 'LOGIN_FAILED'
        ");
        $stmt->execute([$user_id]);
    }

    /**
     * Log security event
     */
    public function logSecurityEvent($user_id, $event_type, $description) {
        $stmt = $this->pdo->prepare("
            INSERT INTO security_logs (
                user_id, event_type, description, ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $event_type,
            $description,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions() {
        // Remove expired sessions
        $stmt = $this->pdo->prepare("
            DELETE FROM sessions 
            WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([SESSION_INACTIVITY]);

        // Remove expired remember tokens
        $stmt = $this->pdo->prepare("
            DELETE FROM remember_tokens 
            WHERE expires_at < NOW()
        ");
        $stmt->execute();

        // Remove expired password reset tokens
        $stmt = $this->pdo->prepare("
            DELETE FROM password_resets 
            WHERE (used = 1 AND used_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
            OR (used = 0 AND expires_at < NOW())
        ");
        $stmt->execute();

        // Remove expired 2FA codes
        $stmt = $this->pdo->prepare("
            DELETE FROM two_factor_codes 
            WHERE (used = 1 AND used_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
            OR (used = 0 AND expires_at < NOW())
        ");
        $stmt->execute();
    }

    /**
     * Validate password strength
     */
    public function validatePassword($password) {
        $errors = [];

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter.";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter.";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number.";
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character.";
        }

        return $errors;
    }

    /**
     * Sanitize input
     */
    public function sanitizeInput($input) {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = $this->sanitizeInput($value);
            }
        } else {
            $input = trim($input);
            $input = stripslashes($input);
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
        return $input;
    }

    /**
     * Validate email
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) &&
               preg_match('/@(uthm\.edu\.my|student\.uthm\.edu\.my)$/', $email);
    }

    /**
     * Validate matric number
     */
    public function validateMatric($matric) {
        return preg_match('/^[A-Z]{2}[0-9]{2}[A-Z]{2}[0-9]{4}$/', $matric);
    }
}
?>
