<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

$page_title = "Unauthorized Access";

// Include header
include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10 text-center">
            <div class="mb-6">
                <i class="fas fa-exclamation-triangle text-6xl text-red-500"></i>
            </div>
            
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
                Unauthorized Access
            </h2>
            
            <p class="text-gray-600 mb-6">
                You do not have permission to access this page.
                If you believe this is an error, please contact support.
            </p>

            <div class="space-y-4">
                <a href="javascript:history.back()" 
                   class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Go Back
                </a>

                <?php if (isset($_SESSION['role'])): ?>
                    <a href="<?php echo $_SESSION['role']; ?>_dashboard.php" 
                       class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        <i class="fas fa-home mr-2"></i>
                        Return to Dashboard
                    </a>
                <?php else: ?>
                    <a href="login.php" 
                       class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Sign In
                    </a>
                <?php endif; ?>

                <a href="contact.php" 
                   class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                    <i class="fas fa-envelope mr-2"></i>
                    Contact Support
                </a>
            </div>

            <div class="mt-6 text-center">
                <p class="text-xs text-gray-500">
                    Error Code: 403 Forbidden
                </p>
                <p class="text-xs text-gray-500">
                    <?php echo date('Y-m-d H:i:s'); ?>
                </p>
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
