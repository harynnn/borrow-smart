<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

$page_title = "Verify Two-Factor Authentication";

// Check if user is in 2FA verification state
if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user details
$stmt = $pdo->prepare("
    SELECT name, email 
    FROM users 
    WHERE uid = ?
");
$stmt->execute([$_SESSION['2fa_user_id']]);
$user = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        if (!$securityHelper->verifyCsrfToken($_POST['csrf_token'])) {
            throw new Exception("Invalid request. Please try again.");
        }

        // Validate code
        if (empty($_POST['code'])) {
            throw new Exception("Please enter the verification code.");
        }

        $code = trim($_POST['code']);

        // Verify 2FA code
        if (!$securityHelper->verify2FACode($_SESSION['2fa_user_id'], $code)) {
            // Log failed attempt
            $securityHelper->logSecurityEvent(
                $_SESSION['2fa_user_id'],
                '2FA_FAILED',
                'Invalid 2FA code attempt'
            );

            throw new Exception("Invalid verification code. Please try again.");
        }

        // Log successful verification
        $securityHelper->logSecurityEvent(
            $_SESSION['2fa_user_id'],
            '2FA_SUCCESS',
            '2FA verification successful'
        );

        // Complete login process
        $_SESSION['uid'] = $_SESSION['2fa_user_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        
        // Get user role
        $stmt = $pdo->prepare("
            SELECT r.role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.role_id 
            WHERE u.uid = ?
        ");
        $stmt->execute([$_SESSION['uid']]);
        $_SESSION['role'] = $stmt->fetchColumn();

        // Initialize session security
        $securityHelper->initializeSession($_SESSION['uid']);

        // Clear 2FA session data
        unset($_SESSION['2fa_user_id']);

        // Redirect to intended URL or dashboard
        $redirect_url = $_SESSION['intended_url'] ?? $_SESSION['role'] . '_dashboard.php';
        unset($_SESSION['intended_url']);
        
        header('Location: ' . $redirect_url);
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
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
                Two-Factor Authentication
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Please enter the verification code sent to your email
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

                <form class="space-y-6" action="verify_2fa.php" method="POST">
                    <?php echo csrf_field(); ?>

                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700">
                            Verification Code
                        </label>
                        <div class="mt-1">
                            <input id="code" name="code" type="text" required
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-gray-500 focus:border-gray-500 sm:text-sm"
                                pattern="[0-9]{6}"
                                title="Please enter the 6-digit verification code"
                                maxlength="6"
                                autocomplete="one-time-code">
                        </div>
                    </div>

                    <div>
                        <button type="submit" 
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Verify Code
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

                    <div class="mt-6 grid grid-cols-1 gap-3">
                        <a href="resend_2fa.php" 
                           class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Resend Code
                        </a>
                        <a href="contact.php" 
                           class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 text-center">
            <p class="text-xs text-gray-500">
                &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        // Auto-focus code input
        document.getElementById('code').focus();

        // Format code input
        document.getElementById('code').addEventListener('input', function(e) {
            // Remove non-digits
            this.value = this.value.replace(/\D/g, '');
            
            // Limit to 6 digits
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
        });

        // Auto-submit when code is complete
        document.getElementById('code').addEventListener('input', function(e) {
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
