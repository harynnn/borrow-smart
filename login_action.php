<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

// Redirect if already logged in
if (isset($_SESSION['uid'])) {
    header('Location: ' . $_SESSION['role'] . '_dashboard.php');
    exit();
}

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

// Verify CSRF token
if (!$securityHelper->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = "Invalid request. Please try again.";
    header('Location: login.php');
    exit();
}

// Validate required fields
if (empty($_POST['email']) || empty($_POST['password'])) {
    $_SESSION['error'] = "Please fill in all required fields.";
    header('Location: login.php');
    exit();
}

$email = trim($_POST['email']);
$password = $_POST['password'];
$remember_me = isset($_POST['remember_me']);

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Check if IP is blocked
    if ($securityHelper->isIPBlocked()) {
        throw new Exception("Too many failed attempts. Please try again later.");
    }

    // Get user by email
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name 
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Check if user exists
    if (!$user) {
        throw new Exception("Invalid email or password.");
    }

    // Check if account is locked
    if ($securityHelper->isAccountLocked($user['uid'])) {
        throw new Exception("Account is temporarily locked. Please try again later.");
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        // Handle failed login attempt
        $securityHelper->handleFailedLogin($user['uid']);
        
        // Log failed attempt
        $securityHelper->logSecurityEvent(
            $user['uid'],
            'LOGIN_FAILED',
            'Invalid password attempt'
        );

        throw new Exception("Invalid email or password.");
    }

    // Check account status
    if ($user['status'] !== 'active') {
        switch ($user['status']) {
            case 'pending':
                throw new Exception("Please verify your email address to activate your account.");
            case 'suspended':
                throw new Exception("Your account has been suspended. Please contact support.");
            case 'deleted':
                throw new Exception("This account has been deleted.");
            default:
                throw new Exception("Account is not active.");
        }
    }

    // Check email verification
    if (!$user['email_verified']) {
        $_SESSION['pending_verification'] = $user['uid'];
        header('Location: verify_email.php');
        exit();
    }

    // Reset failed login attempts
    $securityHelper->resetFailedAttempts($user['uid']);

    // Handle 2FA if enabled
    if ($user['two_factor_enabled']) {
        // Generate and send 2FA code
        $code = $securityHelper->generate2FACode($user['uid']);

        // Send email with 2FA code
        require_once 'PHPMailer-master/src/PHPMailer.php';
        require_once 'PHPMailer-master/src/SMTP.php';
        require_once 'PHPMailer-master/src/Exception.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($user['email'], $user['name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Login Verification Code - BorrowSmart';
        
        $emailContent = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    padding: 20px;
                    background-color: #f8f9fa;
                }
                .header { 
                    text-align: center; 
                    margin-bottom: 30px;
                    padding: 20px;
                    background-color: #fff;
                    border-radius: 8px;
                }
                .code {
                    font-size: 32px;
                    font-weight: bold;
                    text-align: center;
                    letter-spacing: 4px;
                    margin: 20px 0;
                    color: #1a1a1a;
                }
                .warning {
                    background-color: #fff3cd;
                    border-left: 4px solid #ffc107;
                    padding: 12px;
                    margin: 20px 0;
                }
                .footer { 
                    text-align: center; 
                    margin-top: 30px;
                    font-size: 12px;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Login Verification Code</h2>
                </div>
                <p>Hello ' . htmlspecialchars($user['name']) . ',</p>
                <p>A login attempt was made to your BorrowSmart account. To complete the login, please use this verification code:</p>
                <div class="code">' . $code . '</div>
                <div class="warning">
                    <p><strong>Important:</strong></p>
                    <ul>
                        <li>This code will expire in 5 minutes</li>
                        <li>If you did not attempt to login, please secure your account</li>
                        <li>Never share this code with anyone</li>
                    </ul>
                </div>
                <div class="footer">
                    <p>This is an automated message from BorrowSmart System. Please do not reply.</p>
                    <p>&copy; ' . date('Y') . ' BorrowSmart - UTHM. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->Body = $emailContent;
        $mail->AltBody = "Your verification code is: " . $code . "\n\nThis code will expire in 5 minutes.";

        $mail->send();

        // Set 2FA pending session
        $_SESSION['2fa_pending'] = true;
        $_SESSION['2fa_user_id'] = $user['uid'];

        // Redirect to 2FA verification
        header('Location: verify_2fa.php');
        exit();
    }

    // Set session variables
    $_SESSION['uid'] = $user['uid'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role_name'];

    // Initialize session security
    $securityHelper->initializeSession($user['uid']);

    // Handle remember me
    if ($remember_me) {
        $token = generateRandomString(32);
        $expires = time() + REMEMBER_ME_EXPIRY;

        // Store token in database
        $stmt = $pdo->prepare("
            INSERT INTO remember_tokens (
                user_id, token, expires_at
            ) VALUES (?, ?, FROM_UNIXTIME(?))
        ");
        $stmt->execute([
            $user['uid'],
            password_hash($token, PASSWORD_DEFAULT),
            $expires
        ]);

        // Set cookie
        setcookie(
            'remember_token',
            $token,
            $expires,
            COOKIE_PATH,
            COOKIE_DOMAIN,
            COOKIE_SECURE,
            COOKIE_HTTPONLY
        );
    }

    // Update last login
    $stmt = $pdo->prepare("
        UPDATE users 
        SET last_login = NOW()
        WHERE uid = ?
    ");
    $stmt->execute([$user['uid']]);

    // Log successful login
    $securityHelper->logSecurityEvent(
        $user['uid'],
        'LOGIN_SUCCESS',
        'User logged in successfully'
    );

    // Commit transaction
    $pdo->commit();

    // Redirect to intended URL or dashboard
    $redirect_url = $_SESSION['intended_url'] ?? $user['role_name'] . '_dashboard.php';
    unset($_SESSION['intended_url']);
    
    header('Location: ' . $redirect_url);
    exit();

} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollBack();

    error_log("Login Error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header('Location: login.php');
    exit();
}
?>
