<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

$page_title = "Two-Factor Authentication";

// Check if user has a pending 2FA session
if (!isset($_SESSION['2fa_pending']) || !isset($_SESSION['2fa_user_id'])) {
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

if (!$user) {
    header('Location: login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!$securityHelper->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header('Location: verify_2fa.php');
        exit();
    }

    // Validate code
    if (empty($_POST['code'])) {
        $_SESSION['error'] = "Please enter the verification code.";
        header('Location: verify_2fa.php');
        exit();
    }

    $code = trim($_POST['code']);

    // Verify the code
    if ($securityHelper->verify2FACode($_SESSION['2fa_user_id'], $code)) {
        // Code is valid, complete login
        $_SESSION['uid'] = $_SESSION['2fa_user_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];

        // Get user role
        $stmt = $pdo->prepare("
            SELECT r.role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.role_id 
            WHERE u.uid = ?
        ");
        $stmt->execute([$_SESSION['uid']]);
        $_SESSION['role'] = $stmt->fetchColumn();

        // Clear 2FA session data
        unset($_SESSION['2fa_pending']);
        unset($_SESSION['2fa_user_id']);

        // Initialize session security
        $securityHelper->initializeSession($_SESSION['uid']);

        // Log successful login
        $securityHelper->logSecurityEvent(
            $_SESSION['uid'],
            'LOGIN_SUCCESS_2FA',
            'User logged in successfully with 2FA'
        );

        // Redirect to dashboard
        header('Location: ' . $_SESSION['role'] . '_dashboard.php');
        exit();
    } else {
        $_SESSION['error'] = "Invalid verification code. Please try again.";
        
        // Log failed attempt
        $securityHelper->logSecurityEvent(
            $_SESSION['2fa_user_id'],
            'LOGIN_FAILED_2FA',
            'Invalid 2FA code attempt'
        );

        header('Location: verify_2fa.php');
        exit();
    }
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
                                placeholder="Enter 6-digit code"
                                pattern="[0-9]{6}"
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
                                Or
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-3">
                        <a href="resend_2fa.php" 
                           class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Resend Code
                        </a>
                        <a href="logout.php" 
                           class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Cancel Login
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-8 text-center">
                <p class="text-xs text-gray-500">
                    Having trouble? <a href="contact.php" class="font-medium text-gray-900 hover:text-gray-800">Contact Support</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const codeInput = document.getElementById('code');
            
            // Focus code input on page load
            codeInput.focus();

            // Format code input to numbers only
            codeInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });

            // Auto-submit when 6 digits are entered
            codeInput.addEventListener('input', function(e) {
                if (this.value.length === 6) {
                    form.submit();
                }
            });
        });
    </script>
</body>
</html>
