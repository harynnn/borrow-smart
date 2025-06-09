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
    if (empty($_POST['token']) || empty($_POST['password']) || empty($_POST['confirm_password'])) {
        throw new Exception("All fields are required.");
    }

    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate password
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        throw new Exception("Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.");
    }
    if (!preg_match('/[A-Z]/', $password)) {
        throw new Exception("Password must contain at least one uppercase letter.");
    }
    if (!preg_match('/[a-z]/', $password)) {
        throw new Exception("Password must contain at least one lowercase letter.");
    }
    if (!preg_match('/[0-9]/', $password)) {
        throw new Exception("Password must contain at least one number.");
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        throw new Exception("Password must contain at least one special character.");
    }

    // Check if passwords match
    if ($password !== $confirm_password) {
        throw new Exception("Passwords do not match.");
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Get reset request
        $stmt = $pdo->prepare("
            SELECT pr.*, u.name, u.email 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.uid
            WHERE pr.token = ? 
            AND pr.used = 0
            AND pr.expires_at > NOW()
            ORDER BY pr.created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            throw new Exception("Invalid or expired reset link. Please request a new one.");
        }

        // Check if new password is different from current
        $stmt = $pdo->prepare("
            SELECT password 
            FROM users 
            WHERE uid = ?
        ");
        $stmt->execute([$reset['user_id']]);
        $current_password = $stmt->fetchColumn();

        if (password_verify($password, $current_password)) {
            throw new Exception("New password must be different from current password.");
        }

        // Update password
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?,
                password_changed_at = NOW()
            WHERE uid = ?
        ");
        $stmt->execute([
            password_hash($password, PASSWORD_DEFAULT),
            $reset['user_id']
        ]);

        // Mark reset token as used
        $stmt = $pdo->prepare("
            UPDATE password_resets 
            SET used = 1,
                used_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$reset['id']]);

        // Invalidate all sessions for this user
        $stmt = $pdo->prepare("
            DELETE FROM sessions 
            WHERE user_id = ?
        ");
        $stmt->execute([$reset['user_id']]);

        // Remove remember me tokens
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
        $mail->Subject = 'Password Changed - ' . APP_NAME;
        
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
                <h2>Password Changed Successfully</h2>
                <p>Hello ' . htmlspecialchars($reset['name']) . ',</p>
                <p>Your password has been successfully changed.</p>
                <div class="warning">
                    <p><strong>Important:</strong></p>
                    <ul>
                        <li>All active sessions have been logged out</li>
                        <li>If you did not make this change, please contact support immediately</li>
                    </ul>
                </div>
                <p>For security reasons, we recommend:</p>
                <ul>
                    <li>Using unique passwords for different accounts</li>
                    <li>Enabling two-factor authentication</li>
                    <li>Never sharing your password with anyone</li>
                </ul>
                <p>Best regards,<br>' . APP_NAME . ' Team</p>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "Hello " . $reset['name'] . ",\n\n"
                       . "Your password has been successfully changed.\n\n"
                       . "Important:\n"
                       . "- All active sessions have been logged out\n"
                       . "- If you did not make this change, please contact support immediately\n\n"
                       . "For security reasons, we recommend:\n"
                       . "- Using unique passwords for different accounts\n"
                       . "- Enabling two-factor authentication\n"
                       . "- Never sharing your password with anyone\n\n"
                       . "Best regards,\n"
                       . APP_NAME . " Team";

        $mail->send();

        // Log password change
        $securityHelper->logSecurityEvent(
            $reset['user_id'],
            'PASSWORD_RESET',
            'Password reset successful'
        );

        // Commit transaction
        $pdo->commit();

        $_SESSION['success'] = "Your password has been reset successfully. Please sign in with your new password.";

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Password Reset Error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header('Location: resetpassword.php?token=' . urlencode($token));
    exit();
}

// Redirect to login page
header('Location: login.php');
exit();
?>
