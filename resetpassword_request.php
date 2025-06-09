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

    // Validate email
    if (empty($_POST['email'])) {
        throw new Exception("Please enter your email address.");
    }

    $email = strtolower(trim($_POST['email']));

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Please enter a valid email address.");
    }

    // Check if email is from UTHM domain
    if (!preg_match('/@(uthm\.edu\.my|student\.uthm\.edu\.my)$/', $email)) {
        throw new Exception("Please enter your UTHM email address.");
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Get user by email
        $stmt = $pdo->prepare("
            SELECT uid, name, email, status 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception("If your email is registered, you will receive password reset instructions.");
        }

        // Check account status
        if ($user['status'] !== 'active') {
            throw new Exception("If your email is registered, you will receive password reset instructions.");
        }

        // Check for recent reset requests
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM password_resets 
            WHERE user_id = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            AND used = 0
        ");
        $stmt->execute([$user['uid']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("A reset link was recently sent. Please wait 15 minutes before requesting another.");
        }

        // Generate reset token
        $token = $securityHelper->generateRandomString(32);
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Save reset token
        $stmt = $pdo->prepare("
            INSERT INTO password_resets (
                user_id, token, expires_at, created_at
            ) VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user['uid'],
            password_hash($token, PASSWORD_DEFAULT),
            $expiry
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
        $mail->Subject = 'Reset Your Password - ' . APP_NAME;
        
        $resetLink = APP_URL . '/resetpassword.php?token=' . $token;
        
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
                <p>
                    <a href="' . $resetLink . '" class="button">Reset Password</a>
                </p>
                <p>Or copy and paste this link in your browser:</p>
                <p>' . $resetLink . '</p>
                <div class="warning">
                    <p><strong>Important:</strong></p>
                    <ul>
                        <li>This link will expire in 1 hour</li>
                        <li>If you did not request this reset, please ignore this email</li>
                        <li>If you receive multiple reset emails, use only the most recent one</li>
                    </ul>
                </div>
                <p>For security reasons, we recommend changing your password regularly and never sharing it with anyone.</p>
                <p>Best regards,<br>' . APP_NAME . ' Team</p>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "Hello " . $user['name'] . ",\n\n"
                       . "We received a request to reset your password. Click this link to create a new password:\n\n"
                       . $resetLink . "\n\n"
                       . "This link will expire in 1 hour.\n\n"
                       . "If you did not request this reset, please ignore this email.\n"
                       . "If you receive multiple reset emails, use only the most recent one.\n\n"
                       . "For security reasons, we recommend changing your password regularly and never sharing it with anyone.\n\n"
                       . "Best regards,\n"
                       . APP_NAME . " Team";

        $mail->send();

        // Log password reset request
        $securityHelper->logSecurityEvent(
            $user['uid'],
            'PASSWORD_RESET_REQUEST',
            'Password reset requested'
        );

        // Commit transaction
        $pdo->commit();

        $_SESSION['success'] = "If your email is registered, you will receive password reset instructions.";

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Password Reset Request Error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back to forgot password page
header('Location: forgotpassword.php');
exit();
?>
