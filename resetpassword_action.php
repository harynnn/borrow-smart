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

// Check reset session
if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_token'])) {
    $_SESSION['error'] = "Invalid reset session. Please try again.";
    header('Location: forgotpassword.php');
    exit();
}

// Validate required fields
if (empty($_POST['password']) || empty($_POST['confirm_password'])) {
    $_SESSION['error'] = "Please fill in all required fields.";
    header('Location: resetpassword.php?token=' . $_SESSION['reset_token']);
    exit();
}

$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Verify token is still valid
    $stmt = $pdo->prepare("
        SELECT pr.*, u.email, u.name, u.status
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.uid
        WHERE pr.user_id = ?
        AND pr.used = 0
        AND pr.expires_at > NOW()
        ORDER BY pr.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['reset_user_id']]);
    $reset = $stmt->fetch();

    if (!$reset || !password_verify($_SESSION['reset_token'], $reset['token'])) {
        throw new Exception("Invalid or expired reset link. Please request a new one.");
    }

    // Check account status
    if ($reset['status'] !== 'active') {
        switch ($reset['status']) {
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

    // Validate password match
    if ($password !== $confirm_password) {
        throw new Exception("Passwords do not match.");
    }

    // Validate password strength
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        throw new Exception("Password must be at least 8 characters long and include uppercase, lowercase, number, and special character.");
    }

    // Hash new password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Update user password
    $stmt = $pdo->prepare("
        UPDATE users 
        SET password = ?,
            password_changed_at = NOW()
        WHERE uid = ?
    ");
    $stmt->execute([$hashedPassword, $reset['user_id']]);

    // Mark reset token as used
    $stmt = $pdo->prepare("
        UPDATE password_resets 
        SET used = 1,
            used_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$reset['id']]);

    // Invalidate all other reset tokens for this user
    $stmt = $pdo->prepare("
        UPDATE password_resets 
        SET used = 1,
            used_at = NOW()
        WHERE user_id = ?
        AND id != ?
        AND used = 0
    ");
    $stmt->execute([$reset['user_id'], $reset['id']]);

    // Invalidate remember me tokens
    $stmt = $pdo->prepare("
        DELETE FROM remember_tokens 
        WHERE user_id = ?
    ");
    $stmt->execute([$reset['user_id']]);

    // Send confirmation email
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
    $mail->addAddress($reset['email'], $reset['name']);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Password Changed Successfully - BorrowSmart';
    
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
                <h2>Password Changed Successfully</h2>
            </div>
            <p>Hello ' . htmlspecialchars($reset['name']) . ',</p>
            <p>Your password has been successfully changed. You can now sign in with your new password.</p>
            <div class="warning">
                <p><strong>Important:</strong></p>
                <ul>
                    <li>If you did not make this change, please contact support immediately</li>
                    <li>For security, you have been logged out of all devices</li>
                    <li>You will need to sign in again with your new password</li>
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
    $mail->AltBody = "Hello " . $reset['name'] . ",\n\n"
                   . "Your password has been successfully changed. You can now sign in with your new password.\n\n"
                   . "If you did not make this change, please contact support immediately.\n\n"
                   . "For security, you have been logged out of all devices.";

    $mail->send();

    // Log password change
    $securityHelper->logSecurityEvent(
        $reset['user_id'],
        'PASSWORD_RESET',
        'Password reset successful'
    );

    // Clear reset session
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_token']);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = "Your password has been reset successfully. You can now sign in with your new password.";
    header('Location: login.php');
    exit();

} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollBack();

    error_log("Password Reset Error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header('Location: resetpassword.php?token=' . $_SESSION['reset_token']);
    exit();
}
?>
