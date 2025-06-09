<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

// Require guest (not authenticated)
Middleware::requireGuest();

$page_title = "Reset Password";

// Include header
include 'includes/header.php';
?>

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
        <p class="mt-2 text-center text-sm text-gray-600">
            Or
            <a href="login.php" class="font-medium text-gray-900 hover:text-gray-800">
                return to sign in
            </a>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-4 rounded-md bg-green-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">
                                <?php 
                                echo htmlspecialchars($_SESSION['success']);
                                unset($_SESSION['success']);
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 rounded-md bg-red-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">
                                <?php 
                                echo htmlspecialchars($_SESSION['error']);
                                unset($_SESSION['error']);
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="rounded-md bg-blue-50 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            Forgot your password?
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>Enter your UTHM email address and we'll send you a link to reset your password.</p>
                        </div>
                    </div>
                </div>
            </div>

            <form class="space-y-6" action="resetpassword_action.php" method="POST">
                <?php echo csrf_field(); ?>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        UTHM Email address
                    </label>
                    <div class="mt-1">
                        <input id="email" name="email" type="email" autocomplete="email" required
                            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-gray-500 focus:border-gray-500 sm:text-sm"
                            placeholder="Enter your UTHM email">
                    </div>
                </div>

                <div>
                    <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Send Reset Link
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
                    <a href="verify_email.php" 
                       class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Resend Verification Email
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

<?php
// Include footer
include 'includes/footer.php';
?>
