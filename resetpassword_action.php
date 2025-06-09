<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

// Require guest (not authenticated)
Middleware::requireGuest();

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle password reset form submission
        if (isset($_POST['token'])) {
            // Verify CSRF token
            if (!$securityHelper->verifyCsrfToken($_POST['csrf_token'])) {
                throw new Exception("Invalid request. Please try again.");
            }

            $token = $_POST['token'];
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            // Validate password
            $password_errors = $securityHelper->validatePassword($password);
            if (!empty($password_errors)) {
                throw new Exception(implode(" ", $password_errors));
            }

            if ($password !== $confirm_password) {
                throw new Exception("Passwords do not match.");
            }

            // Begin transaction
            $pdo->beginTransaction();

            // Get user by reset token
            $stmt = $pdo->prepare("
                SELECT uid, email 
                FROM users 
                WHERE reset_token = ? 
                AND reset_token IS NOT NULL 
                AND reset_token_expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new Exception("Invalid or expired reset link.");
            }

            // Update password
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = ?,
                    reset_token = NULL,
                    reset_token_expires_at = NULL,
                    password_changed_at = NOW(),
                    force_password_change = 0,
                    updated_at = NOW()
                WHERE uid = ?
            ");
            $stmt->execute([
                password_hash($password, PASSWORD_DEFAULT),
                $user['uid']
            ]);

            // Log password reset
            $securityHelper->logSecurityEvent(
                $user['uid'],
                'PASSWORD_RESET',
                'Password reset completed from ' . $_SERVER['REMOTE_ADDR']
            );

            // Send confirmation email
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
            $mail->addAddress($user['email']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Confirmation - ' . APP_NAME;
            
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
                    <h2>Password Reset Confirmation</h2>
                    <p>Hello,</p>
                    <p>Your password has been successfully reset.</p>
                    <div class="warning">
                        <p><strong>Important:</strong></p>
                        <p>If you did not request this change, please contact support immediately.</p>
                    </div>
                    <p>Best regards,<br>' . APP_NAME . ' Team</p>
                </div>
            </body>
            </html>';
            
            $mail->AltBody = "Hello,\n\n"
                           . "Your password has been successfully reset.\n\n"
                           . "If you did not request this change, please contact support immediately.\n\n"
                           . "Best regards,\n"
                           . APP_NAME . " Team";

            $mail->send();

            // Commit transaction
            $pdo->commit();

            // Set success message
            $_SESSION['success'] = "Your password has been reset successfully. You can now sign in with your new password.";

            // Redirect to login page
            header('Location: login.php');
            exit();

        } else {
            // Handle reset password request
            // Verify CSRF token
            if (!$securityHelper->verifyCsrfToken($_POST['csrf_token'])) {
                throw new Exception("Invalid request. Please try again.");
            }

            $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
            if (!$email) {
                throw new Exception("Invalid email format.");
            }

            // Check if email is institutional
            if (!$securityHelper->isInstitutionalEmail($email)) {
                throw new Exception("Please use your UTHM email address.");
            }

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
                // Don't reveal if email exists
                $_SESSION['success'] = "If your email is registered, you will receive password reset instructions shortly.";
                header('Location: resetpassword_request.php');
                exit();
            }

            if ($user['status'] !== 'active') {
                throw new Exception("This account is not active. Please contact support.");
            }

            // Generate reset token
            $token = $securityHelper->generateRandomString(32);

            // Store token in database
            $stmt = $pdo->prepare("
                UPDATE users 
                SET reset_token = ?,
                    reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR),
                    updated_at = NOW()
                WHERE uid = ?
            ");
            $stmt->execute([$token, $user['uid']]);

            // Send reset email
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
            $reset_url = APP_URL . '/resetpassword.php?token=' . $token;

            $mail->isHTML(true);
            $mail->Subject = 'Reset Your Password - ' . APP_NAME;
            
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
                </style>
            </head>
            <body>
                <div class="container">
                    <h2>Reset Your Password</h2>
                    <p>Hello ' . htmlspecialchars($user['name']) . ',</p>
                    <p>We received a request to reset your password. Click the button below to create a new password:</p>
                    <p style="text-align: center;">
                        <a href="' . $reset_url . '" class="button">Reset Password</a>
                    </p>
                    <div class="warning">
                        <p><strong>Important:</strong></p>
                        <ul>
                            <li>This link will expire in 1 hour</li>
                            <li>If you did not request this reset, please ignore this email</li>
                        </ul>
                    </div>
                    <p>
                        If the button above doesn\'t work, copy and paste this URL into your browser:<br>
                        ' . $reset_url . '
                    </p>
                    <p>Best regards,<br>' . APP_NAME . ' Team</p>
                </div>
            </body>
            </html>';
            
            $mail->AltBody = "Hello " . $user['name'] . ",\n\n"
                           . "We received a request to reset your password. "
                           . "Please click the link below to create a new password:\n\n"
                           . $reset_url . "\n\n"
                           . "This link will expire in 1 hour.\n"
                           . "If you did not request this reset, please ignore this email.\n\n"
                           . "Best regards,\n"
                           . APP_NAME . " Team";

            $mail->send();

            // Log reset request
            $securityHelper->logSecurityEvent(
                $user['uid'],
                'PASSWORD_RESET_REQUEST',
                'Password reset requested from ' . $_SERVER['REMOTE_ADDR']
            );

            // Commit transaction
            $pdo->commit();

            // Set success message (same as non-existent email for security)
            $_SESSION['success'] = "If your email is registered, you will receive password reset instructions shortly.";

            // Redirect back to request page
            header('Location: resetpassword_request.php');
            exit();
        }
    } else {
        // Invalid request method
        header('Location: login.php');
        exit();
    }

} catch (Exception $e) {
    // Rollback transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error'] = $e->getMessage();
    header('Location: ' . (isset($_POST['token']) ? 'resetpassword.php?token=' . $_POST['token'] : 'resetpassword_request.php'));
    exit();
}
?>
