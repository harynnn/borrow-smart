<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

// Redirect if already logged in
if (isset($_SESSION['uid'])) {
    header('Location: ' . $_SESSION['role'] . '_dashboard.php');
    exit();
}

try {
    // Verify CSRF token
    if (!$securityHelper->verifyCsrfToken($_POST['csrf_token'])) {
        throw new Exception("Invalid request. Please try again.");
    }

    // Validate input
    if (empty($_POST['email']) || empty($_POST['password'])) {
        throw new Exception("Please fill in all fields.");
    }

    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);

    // Check if IP is blocked
    if ($securityHelper->isIPBlocked()) {
        throw new Exception("Access denied. Please try again later.");
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

    if (!$user) {
        // Log failed attempt with non-existent user
        $securityHelper->logSecurityEvent(
            0,
            'LOGIN_FAILED',
            'Login attempt with non-existent email: ' . $email
        );
        
        throw new Exception("Invalid email or password.");
    }

    // Check if account is locked
    if ($securityHelper->isAccountLocked($user['uid'])) {
        throw new Exception("Account is temporarily locked. Please try again later.");
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        // Log failed attempt
        $securityHelper->handleFailedLogin($user['uid']);
        
        throw new Exception("Invalid email or password.");
    }

    // Check account status
    if ($user['status'] !== 'active') {
        // Log attempt on inactive account
        $securityHelper->logSecurityEvent(
            $user['uid'],
            'LOGIN_FAILED',
            'Login attempt on ' . $user['status'] . ' account'
        );
        
        switch ($user['status']) {
            case 'pending':
                throw new Exception("Please verify your email address to activate your account.");
            case 'suspended':
                throw new Exception("Your account has been suspended. Please contact support.");
            case 'deleted':
                throw new Exception("This account has been deleted.");
            default:
                throw new Exception("Account is not active. Please contact support.");
        }
    }

    // Check email verification
    if (!$user['email_verified']) {
        // Generate new verification token
        $token = $securityHelper->generateRandomString(32);
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET verification_token = ? 
            WHERE uid = ?
        ");
        $stmt->execute([$token, $user['uid']]);

        // Send verification email
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
        $mail->Subject = 'Verify Your Email - ' . APP_NAME;
        
        $verificationLink = APP_URL . '/verify_email.php?token=' . $token;
        
        $mail->Body = '
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
                }
                .button {
                    display: inline-block;
                    padding: 10px 20px;
                    background-color: #000;
                    color: #fff;
                    text-decoration: none;
                    border-radius: 5px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Verify Your Email Address</h2>
                <p>Hello ' . htmlspecialchars($user['name']) . ',</p>
                <p>Please verify your email address by clicking the button below:</p>
                <p>
                    <a href="' . $verificationLink . '" class="button">Verify Email</a>
                </p>
                <p>Or copy and paste this link in your browser:</p>
                <p>' . $verificationLink . '</p>
                <p>This link will expire in 24 hours.</p>
                <p>If you did not create an account, please ignore this email.</p>
                <p>Best regards,<br>' . APP_NAME . ' Team</p>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "Hello " . $user['name'] . ",\n\n"
                       . "Please verify your email address by clicking this link:\n\n"
                       . $verificationLink . "\n\n"
                       . "This link will expire in 24 hours.\n\n"
                       . "If you did not create an account, please ignore this email.\n\n"
                       . "Best regards,\n"
                       . APP_NAME . " Team";

        $mail->send();

        throw new Exception("Please verify your email address. A new verification link has been sent.");
    }

    // Check if 2FA is enabled
    if ($user['two_factor_enabled']) {
        // Generate and send 2FA code
        $code = $securityHelper->generate2FACode($user['uid']);

        // Send 2FA code via email
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
        $mail->Subject = '2FA Code - ' . APP_NAME;
        
        $mail->Body = '
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
                }
                .code {
                    font-size: 24px;
                    font-weight: bold;
                    text-align: center;
                    padding: 20px;
                    background-color: #f8f9fa;
                    border-radius: 5px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Two-Factor Authentication Code</h2>
                <p>Hello ' . htmlspecialchars($user['name']) . ',</p>
                <p>Your verification code is:</p>
                <div class="code">' . $code . '</div>
                <p>This code will expire in 5 minutes.</p>
                <p>If you did not attempt to sign in, please change your password immediately.</p>
                <p>Best regards,<br>' . APP_NAME . ' Team</p>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "Hello " . $user['name'] . ",\n\n"
                       . "Your verification code is: " . $code . "\n\n"
                       . "This code will expire in 5 minutes.\n\n"
                       . "If you did not attempt to sign in, please change your password immediately.\n\n"
                       . "Best regards,\n"
                       . APP_NAME . " Team";

        $mail->send();

        // Store user ID for 2FA verification
        $_SESSION['2fa_user_id'] = $user['uid'];

        // Log 2FA initiation
        $securityHelper->logSecurityEvent(
            $user['uid'],
            '2FA_INITIATED',
            '2FA verification initiated'
        );

        // Redirect to 2FA verification
        header('Location: verify_2fa.php');
        exit();
    }

    // Login successful
    $_SESSION['uid'] = $user['uid'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role_name'];

    // Initialize session security
    $securityHelper->initializeSession($user['uid']);

    // Handle remember me
    if ($remember_me) {
        $token = $securityHelper->generateRandomString(32);
        $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $pdo->prepare("
            INSERT INTO remember_tokens (
                user_id, token, expires_at
            ) VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $user['uid'],
            password_hash($token, PASSWORD_DEFAULT),
            $expiry
        ]);

        setcookie(
            'remember_token',
            $token,
            strtotime('+30 days'),
            '/',
            '',
            true,
            true
        );
    }

    // Update last login
    $stmt = $pdo->prepare("
        UPDATE users 
        SET last_login = NOW()
        WHERE uid = ?
    ");
    $stmt->execute([$user['uid']]);

    // Reset failed login attempts
    $securityHelper->resetFailedAttempts($user['uid']);

    // Log successful login
    $securityHelper->logSecurityEvent(
        $user['uid'],
        'LOGIN_SUCCESS',
        'Successful login'
    );

    // Redirect to intended URL or dashboard
    $redirect_url = $_SESSION['intended_url'] ?? $user['role_name'] . '_dashboard.php';
    unset($_SESSION['intended_url']);
    
    header('Location: ' . $redirect_url);
    exit();

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: login.php');
    exit();
}
?>
