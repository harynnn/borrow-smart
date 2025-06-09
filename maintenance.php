<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

$page_title = "System Maintenance";

// Check if maintenance mode is active
if (!isMaintenanceMode() && !isset($_SESSION['error'])) {
    header('Location: index.php');
    exit();
}

// Check if user has maintenance access
if (isset($_SESSION['uid']) && hasPermission('access_maintenance')) {
    // Redirect to intended URL or dashboard
    $redirect_url = $_SESSION['intended_url'] ?? $_SESSION['role'] . '_dashboard.php';
    unset($_SESSION['intended_url']);
    header('Location: ' . $redirect_url);
    exit();
}

// Get maintenance details
$maintenanceMessage = getSetting('maintenance_message', MAINTENANCE_MESSAGE);
$estimatedDuration = getSetting('maintenance_duration', '1 hour');
$startTime = getSetting('maintenance_start_time');
$contactEmail = SUPPORT_EMAIL;
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
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .animate-spin-slow {
            animation: spin 3s linear infinite;
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
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <div class="text-center">
                    <div class="mb-6">
                        <i class="fas fa-cog text-6xl text-gray-400 animate-spin-slow"></i>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-4">
                        System Maintenance
                    </h2>

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

                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                        <p class="text-gray-600">
                            <?php echo nl2br(htmlspecialchars($maintenanceMessage)); ?>
                        </p>
                    </div>

                    <div class="space-y-4 text-sm text-gray-500">
                        <?php if ($startTime): ?>
                            <p>
                                <i class="fas fa-clock mr-2"></i>
                                Started at: <?php echo formatDateTime($startTime); ?>
                            </p>
                        <?php endif; ?>
                        
                        <p>
                            <i class="fas fa-hourglass-half mr-2"></i>
                            Estimated duration: <?php echo htmlspecialchars($estimatedDuration); ?>
                        </p>
                        
                        <p>
                            <i class="fas fa-envelope mr-2"></i>
                            For urgent matters, please contact:
                            <a href="mailto:<?php echo $contactEmail; ?>" class="text-gray-900 hover:underline">
                                <?php echo $contactEmail; ?>
                            </a>
                        </p>
                    </div>

                    <div class="mt-6 space-y-4">
                        <button onclick="window.location.reload()" 
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            <i class="fas fa-sync-alt mr-2"></i>
                            Check Again
                        </button>

                        <?php if (isset($_SESSION['uid'])): ?>
                            <a href="logout.php" 
                               class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                <i class="fas fa-sign-out-alt mr-2"></i>
                                Sign Out
                            </a>
                        <?php endif; ?>
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

    <?php if (isMaintenanceMode()): ?>
    <script>
        // Auto-refresh every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000);
    </script>
    <?php endif; ?>
</body>
</html>
