<?php
session_start();
require_once 'dbconnection.php';
require_once 'includes/functions.php';

$token = $_GET['token'] ?? '';
$valid_token = false;
$error_message = '';

if (empty($token)) {
    $error_message = "Invalid or missing reset token.";
} else {
    try {
        // Check if token exists and is valid
        $stmt = $pdo->prepare("
            SELECT pr.*, u.email, u.name 
            FROM password_resets pr
            JOIN users u ON u.uid = pr.user_id
            WHERE pr.token = ? 
            AND pr.expires_at > NOW()
            AND pr.used = 0
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if ($reset) {
            $valid_token = true;
        } else {
            $error_message = "This password reset link has expired or is invalid.";
        }
    } catch (PDOException $e) {
        error_log("Token Validation Error: " . $e->getMessage());
        $error_message = "An error occurred. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - BorrowSmart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .password-strength-meter {
            height: 4px;
            background-color: #edf2f7;
            border-radius: 2px;
            overflow: hidden;
        }
        .password-strength-meter div {
            height: 100%;
            width: 0;
            transition: width 0.3s ease-in-out;
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
                Reset your password
            </h2>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <?php if (!$valid_token): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">
                                    <?php echo htmlspecialchars($error_message); ?>
                                </p>
                                <p class="mt-2">
                                    <a href="forgotpassword.php" class="text-sm font-medium text-red-700 hover:text-red-600">
                                        Request a new password reset link →
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
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

                    <form class="space-y-6" action="resetpassword_action.php" method="POST" id="resetForm">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                New Password
                            </label>
                            <div class="mt-1">
                                <input id="password" name="password" type="password" required
                                    class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-gray-500 focus:border-gray-500 sm:text-sm"
                                    placeholder="Enter your new password">
                            </div>
                            <div class="mt-2">
                                <div class="password-strength-meter">
                                    <div id="strengthMeter"></div>
                                </div>
                                <p id="passwordStrength" class="mt-1 text-xs text-gray-500"></p>
                            </div>
                            <div class="mt-2">
                                <?php echo getPasswordRequirements(); ?>
                            </div>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">
                                Confirm New Password
                            </label>
                            <div class="mt-1">
                                <input id="confirm_password" name="confirm_password" type="password" required
                                    class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-gray-500 focus:border-gray-500 sm:text-sm"
                                    placeholder="Confirm your new password">
                            </div>
                        </div>

                        <div>
                            <button type="submit" 
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                Reset Password
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

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

                    <div class="mt-6">
                        <a href="login.php" 
                            class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetForm');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthMeter = document.getElementById('strengthMeter');
            const strengthText = document.getElementById('passwordStrength');

            if (form) {
                function checkPasswordStrength(password) {
                    let strength = 0;
                    const checks = {
                        'length': password.length >= 8,
                        'uppercase': /[A-Z]/.test(password),
                        'lowercase': /[a-z]/.test(password),
                        'numbers': /[0-9]/.test(password),
                        'special': /[!@#$%^&*()\-_=+{};:,<.>]/.test(password)
                    };

                    // Calculate strength percentage
                    strength = (Object.values(checks).filter(Boolean).length / Object.keys(checks).length) * 100;

                    // Update strength meter
                    strengthMeter.style.width = strength + '%';
                    strengthMeter.style.backgroundColor = 
                        strength < 40 ? '#f56565' :  // red
                        strength < 80 ? '#ed8936' :  // orange
                        '#48bb78';                   // green

                    // Update strength text
                    strengthText.textContent = 
                        strength < 40 ? 'Weak' :
                        strength < 80 ? 'Medium' :
                        'Strong';
                    strengthText.style.color = strengthMeter.style.backgroundColor;
                }

                passwordInput.addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                });

                form.addEventListener('submit', function(e) {
                    const password = passwordInput.value;
                    const confirmPassword = confirmPasswordInput.value;

                    // Check password match
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('Passwords do not match!');
                        return;
                    }

                    // Check password strength
                    if (!isValidPassword(password)) {
                        e.preventDefault();
                        alert('Password does not meet the requirements!');
                        return;
                    }
                });
            }

            function isValidPassword(password) {
                return password.length >= 8 &&
                       /[A-Z]/.test(password) &&
                       /[a-z]/.test(password) &&
                       /[0-9]/.test(password) &&
                       /[!@#$%^&*()\-_=+{};:,<.>]/.test(password);
            }
        });
    </script>
</body>
</html>
