<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

// Require authentication
Middleware::requireAuth();

// Check if 2FA is pending
if (!isset($_SESSION['2fa_required']) || isset($_SESSION['2fa_verified'])) {
    header('Location: ' . SessionHandler::getUserRole() . '_dashboard.php');
    exit();
}

try {
    // Generate new 2FA code
    $code = $securityHelper->generate2FACode($_SESSION['uid']);

    // Get user details
    $stmt = $pdo->prepare("
        SELECT name, email 
        FROM users 
        WHERE uid = ?
    ");
    $stmt->execute([$_SESSION['uid']]);
    $user = $stmt->fetch();

    // Send 2FA code via email
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
                border-radius: 4px;
                margin: 20px 0;
            }
            .warning {
                background-color: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 12px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>Two-Factor Authentication Code</h2>
            <p>Hello ' . htmlspecialchars($user['name']) . ',</p>
            <p>Your new verification code is:</p>
            <div class="code">' . $code . '</div>
            <div class="warning">
                <p><strong>Important:</strong></p>
                <ul>
                    <li>This code will expire in 5 minutes</li>
                    <li>If you did not request this code, please secure your account</li>
                </ul>
            </div>
            <p>Best regards,<br>' . APP_NAME . ' Team</p>
        </div>
    </body>
    </html>';
    
    $mail->AltBody = "Hello " . $user['name'] . ",\n\n"
                   . "Your new verification code is: " . $code . "\n\n"
                   . "This code will expire in 5 minutes.\n"
                   . "If you did not request this code, please secure your account.\n\n"
                   . "Best regards,\n"
                   . APP_NAME . " Team";

    $mail->send();

    // Log 2FA code resend
    $securityHelper->logSecurityEvent(
        $_SESSION['uid'],
        '2FA_CODE_RESENT',
        '2FA code resent from ' . $_SERVER['REMOTE_ADDR']
    );

    // Set success message
    $_SESSION['success'] = "A new verification code has been sent to your email.";

} catch (Exception $e) {
    // Log error
    error_log("2FA Code Resend Error: " . $e->getMessage());
    
    // Set error message
    $_SESSION['error'] = "Failed to send verification code. Please try again.";
}

// Redirect back to verify 2FA page
header('Location: verify_2fa.php');
exit();
?>
