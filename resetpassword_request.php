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
    header('Location: forgotpassword.php');
    exit();
}

// Verify CSRF token
if (!$securityHelper->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = "Invalid request. Please try again.";
    header('Location: forgotpassword.php');
    exit();
}

// Validate email
if (empty($_POST['email'])) {
    $_SESSION['error'] = "Please enter your email address.";
    header('Location: forgotpassword.php');
    exit();
}

$email = trim(strtolower($_POST['email']));

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Get user by email
    $stmt = $pdo->prepare("
        SELECT uid, name, email, status 
        FROM users 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("If an account exists with this email, you will receive a password reset link.");
    }

    // Check account status
    if ($user['status'] !== 'active') {
        switch ($user['status']) {
            case 'pending':
                throw new Exception("Please verify your email address before resetting your password.");
            case 'suspended':
                throw new Exception("This account has been suspended. Please contact support.");
            case 'deleted':
                throw new Exception("This account has been deleted.");
            default:
                throw new Exception("Account is not active.");
        }
    }

    // Check rate limiting
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM security_logs 
        WHERE user_id = ? 
        AND event_type = 'PASSWORD_RESET_REQUEST'
        AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$user['uid']]);
    
    if ($stmt->fetchColumn() >= 3) {
        throw new Exception("Too many reset attempts. Please wait 15 minutes and try again.");
    }

    // Generate reset token
    $resetToken = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store reset token
    $stmt = $pdo->prepare("
        INSERT INTO password_resets (
            user_id, token, expires_at, created_at
        ) VALUES (
            ?, ?, ?, NOW()
        )
    ");
    $stmt->execute([
        $user['uid'],
        password_hash($resetToken, PASSWORD_DEFAULT),
        $expires
    ]);

    // Send reset email
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
    $mail->Subject = 'Reset Your Password - BorrowSmart';
    
    $resetUrl = APP_URL . '/resetpassword.php?token=' . $resetToken;
    
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
            .button {
                display: inline-block;
                padding: 12px 24px;
                background-color: #1a1a1a;
                color: #ffffff;
                text-decoration: none;
                border-radius: 4px;
                margin: 20px 0;
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
                <h2>Reset Your Password</h2>
            </div>
            <p>Hello ' . htmlspecialchars($user['name']) . ',</p>
            <p>You requested to reset your password. Click the button below to create a new password:</p>
            <div style="text-align: center;">
                <a href="' . $resetUrl . '" class="button">Reset Password</a>
            </div>
            <p>Or copy and paste this URL into your browser:</p>
            <p>' . $resetUrl . '</p>
            <div class="warning">
                <p><strong>Important:</strong></p>
                <ul>
                    <li>This link will expire in 1 hour</li>
                    <li>If you did not request this reset, please ignore this email</li>
                    <li>If you receive multiple reset emails, use only the most recent one</li>
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
    $mail->AltBody = "Hello " . $user['name'] . ",\n\n"
                   . "You requested to reset your password. Please click this link to create a new password:\n"
                   . $resetUrl . "\n\n"
                   . "This link will expire in 1 hour.\n\n"
                   . "If you did not request this reset, please ignore this email.";

    $mail->send();

    // Log reset request
    $securityHelper->logSecurityEvent(
        $user['uid'],
        'PASSWORD_RESET_REQUEST',
        'Password reset requested'
    );

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = "If an account exists with this email, you will receive a password reset link.";

} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollBack();

    error_log("Password Reset Request Error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
}

// Always redirect back to forgot password page
header('Location: forgotpassword.php');
exit();
?>
