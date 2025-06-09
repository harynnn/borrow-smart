<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

// Check if user is in 2FA verification state
if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: login.php');
    exit();
}

try {
    // Get user details
    $stmt = $pdo->prepare("
        SELECT name, email 
        FROM users 
        WHERE uid = ?
    ");
    $stmt->execute([$_SESSION['2fa_user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("Invalid user session.");
    }

    // Check for rate limiting
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM two_factor_codes 
        WHERE user_id = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$_SESSION['2fa_user_id']]);
    $recentAttempts = $stmt->fetchColumn();

    if ($recentAttempts >= 3) {
        throw new Exception("Too many code requests. Please wait 5 minutes before trying again.");
    }

    // Generate new 2FA code
    $code = $securityHelper->generate2FACode($_SESSION['2fa_user_id']);

    // Send email with new code
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
    $mail->Subject = 'New Verification Code - ' . APP_NAME;
    
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
                text-align: center;
                font-size: 32px;
                letter-spacing: 4px;
                margin: 20px 0;
                padding: 10px;
                background-color: #fff;
                border-radius: 4px;
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
                <h2>New Verification Code</h2>
            </div>
            <p>Hello ' . htmlspecialchars($user['name']) . ',</p>
            <p>You requested a new verification code. Here is your new code:</p>
            <div class="code">' . $code . '</div>
            <div class="warning">
                <p><strong>Important:</strong></p>
                <ul>
                    <li>This code will expire in 5 minutes</li>
                    <li>If you did not request this code, please ignore this email</li>
                    <li>Never share this code with anyone</li>
                </ul>
            </div>
            <div class="footer">
                <p>This is an automated message from ' . APP_NAME . '. Please do not reply.</p>
                <p>&copy; ' . date('Y') . ' ' . APP_NAME . ' - UTHM. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    $mail->Body = $emailContent;
    $mail->AltBody = "Hello " . $user['name'] . ",\n\n"
                   . "You requested a new verification code. Here is your new code:\n\n"
                   . $code . "\n\n"
                   . "This code will expire in 5 minutes.\n"
                   . "If you did not request this code, please ignore this email.\n"
                   . "Never share this code with anyone.";

    $mail->send();

    // Log the event
    $securityHelper->logSecurityEvent(
        $_SESSION['2fa_user_id'],
        '2FA_RESEND',
        'New 2FA code sent'
    );

    $_SESSION['success'] = "A new verification code has been sent to your email.";

} catch (Exception $e) {
    error_log("2FA Resend Error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back to verification page
header('Location: verify_2fa.php');
exit();
?>
