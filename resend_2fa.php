<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

// Check if user has a pending 2FA session
if (!isset($_SESSION['2fa_pending']) || !isset($_SESSION['2fa_user_id'])) {
    header('Location: login.php');
    exit();
}

// Verify CSRF token if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$securityHelper->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header('Location: verify_2fa.php');
        exit();
    }
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Get user details
    $stmt = $pdo->prepare("
        SELECT name, email 
        FROM users 
        WHERE uid = ?
    ");
    $stmt->execute([$_SESSION['2fa_user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User not found.");
    }

    // Check rate limiting
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM security_logs 
        WHERE user_id = ? 
        AND event_type = 'RESEND_2FA'
        AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$_SESSION['2fa_user_id']]);
    
    if ($stmt->fetchColumn() >= 3) {
        throw new Exception("Too many resend attempts. Please wait 15 minutes and try again.");
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
    $mail->Subject = 'New Verification Code - BorrowSmart';
    
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
                <h2>New Verification Code</h2>
            </div>
            <p>Hello ' . htmlspecialchars($user['name']) . ',</p>
            <p>You requested a new verification code. Here\'s your new code:</p>
            <div class="code">' . $code . '</div>
            <div class="warning">
                <p><strong>Important:</strong></p>
                <ul>
                    <li>This code will expire in 5 minutes</li>
                    <li>If you did not request this code, please secure your account</li>
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
    $mail->AltBody = "Your new verification code is: " . $code . "\n\nThis code will expire in 5 minutes.";

    $mail->send();

    // Log the resend event
    $securityHelper->logSecurityEvent(
        $_SESSION['2fa_user_id'],
        'RESEND_2FA',
        'New 2FA code requested and sent'
    );

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = "A new verification code has been sent to your email.";

} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollBack();

    error_log("2FA Resend Error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back to verification page
header('Location: verify_2fa.php');
exit();
?>
