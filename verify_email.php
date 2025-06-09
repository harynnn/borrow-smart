<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

$page_title = "Verify Email";

// Handle verification token
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Get user by verification token
        $stmt = $pdo->prepare("
            SELECT uid, name, email, verification_token, created_at 
            FROM users 
            WHERE verification_token = ? 
            AND status = 'pending'
            AND email_verified = 0
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception("Invalid or expired verification token.");
        }

        // Check if token is expired (24 hours)
        $created = new DateTime($user['created_at']);
        $now = new DateTime();
        $interval = $created->diff($now);

        if ($interval->days >= 1) {
            throw new Exception("Verification token has expired. Please request a new one.");
        }

        // Update user status
        $stmt = $pdo->prepare("
            UPDATE users 
            SET status = 'active',
                email_verified = 1,
                verification_token = NULL,
                email_verified_at = NOW()
            WHERE uid = ?
        ");
        $stmt->execute([$user['uid']]);

        // Log verification event
        $securityHelper->logSecurityEvent(
            $user['uid'],
            'EMAIL_VERIFIED',
            'Email address verified successfully'
        );

        // Commit transaction
        $pdo->commit();

        $_SESSION['success'] = "Email verified successfully! You can now sign in.";
        header('Location: login.php');
        exit();

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();

        error_log("Email Verification Error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }
}

// Handle resend verification email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!$securityHelper->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header('Location: verify_email.php');
        exit();
    }

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $_SESSION['error'] = "Please enter your email address.";
        header('Location: verify_email.php');
        exit();
    }

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Get unverified user
        $stmt = $pdo->prepare("
            SELECT uid, name, email 
            FROM users 
            WHERE email = ? 
            AND status = 'pending'
            AND email_verified = 0
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception("No pending verification found for this email address.");
        }

        // Check rate limiting
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM security_logs 
            WHERE user_id = ? 
            AND event_type = 'RESEND_VERIFICATION'
            AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$user['uid']]);
        
        if ($stmt->fetchColumn() >= 3) {
            throw new Exception("Too many resend attempts. Please wait 15 minutes and try again.");
        }

        // Generate new verification token
        $verificationToken = bin2hex(random_bytes(32));

        // Update verification token
        $stmt = $pdo->prepare("
            UPDATE users 
            SET verification_token = ?
            WHERE uid = ?
        ");
        $stmt->execute([$verificationToken, $user['uid']]);

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
        $mail->Subject = 'Verify Your Email - BorrowSmart';
        
        $verificationUrl = APP_URL . '/verify_email.php?token=' . $verificationToken;
        
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
                    <h2>Email Verification</h2>
                </div>
                <p>Hello ' . htmlspecialchars($user['name']) . ',</p>
                <p>You requested a new verification link. To verify your email address, please click the button below:</p>
                <div style="text-align: center;">
                    <a href="' . $verificationUrl . '" class="button">Verify Email Address</a>
                </div>
                <p>Or copy and paste this URL into your browser:</p>
                <p>' . $verificationUrl . '</p>
                <div class="warning">
                    <p><strong>Important:</strong></p>
                    <ul>
                        <li>This verification link will expire in 24 hours</li>
                        <li>If you did not request this email, please ignore it</li>
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
                       . "You requested a new verification link. Please verify your email address by clicking this link:\n"
                       . $verificationUrl . "\n\n"
                       . "This link will expire in 24 hours.";

        $mail->send();

        // Log resend event
        $securityHelper->logSecurityEvent(
            $user['uid'],
            'RESEND_VERIFICATION',
            'Verification email resent'
        );

        // Commit transaction
        $pdo->commit();

        $_SESSION['success'] = "A new verification email has been sent. Please check your inbox.";

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();

        error_log("Verification Resend Error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: verify_email.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - BorrowSmart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <!-- Logo Container -->
        <div class="flex justify-center space-x-4 mb-8">
            <img src="/images/borrowsmart.png" alt="BorrowSmart Logo" class="h-12">
            <img src="/images/uthmlogo.png" alt="UTHM Logo" class="h-12">
        </div>

        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <h2 class="text-center text-3xl font-bold text-gray-900">
                Verify Your Email
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Or
                <a href="login.php" class="font-medium text-gray-900 hover:text-gray-800">
                    return to sign in
                </a>
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">
                                    <?php 
                                    echo htmlspecialchars($_SESSION['error']);
                                    unset($_SESSION['error']);
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700">
                                    <?php 
                                    echo htmlspecialchars($_SESSION['success']);
                                    unset($_SESSION['success']);
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form class="space-y-6" action="verify_email.php" method="POST">
                    <?php echo csrf_field(); ?>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">
                            Email address
                        </label>
                        <div class="mt-1">
                            <input id="email" name="email" type="email" required
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-gray-500 focus:border-gray-500 sm:text-sm">
                        </div>
                    </div>

                    <div>
                        <button type="submit" 
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Resend Verification Email
                        </button>
                    </div>
                </form>

                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">
                                Need help?
                            </span>
                        </div>
                    </div>

                    <div class="mt-6">
                        <a href="contact.php" 
                           class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Contact Support
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-8 text-center">
                <p class="text-xs text-gray-500">
                    Already verified? 
                    <a href="login.php" class="font-medium text-gray-900 hover:text-gray-800">Sign in</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const emailInput = document.getElementById('email');
            const submitButton = form.querySelector('button[type="submit"]');

            // Focus email input on page load
            emailInput.focus();

            // Form validation
            form.addEventListener('submit', function(e) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending verification email...';
            });
        });
    </script>
</body>
</html>
