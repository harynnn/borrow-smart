<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

$page_title = "Error";

// Get error details
$error_code = $_GET['code'] ?? '404';
$error_message = $_GET['message'] ?? 'Page not found';

// Map error codes to messages and icons
$error_types = [
    '400' => [
        'title' => 'Bad Request',
        'message' => 'The request could not be understood by the server.',
        'icon' => 'exclamation-circle'
    ],
    '401' => [
        'title' => 'Unauthorized',
        'message' => 'Authentication is required to access this resource.',
        'icon' => 'lock'
    ],
    '403' => [
        'title' => 'Forbidden',
        'message' => 'You do not have permission to access this resource.',
        'icon' => 'ban'
    ],
    '404' => [
        'title' => 'Page Not Found',
        'message' => 'The requested page could not be found.',
        'icon' => 'search'
    ],
    '500' => [
        'title' => 'Internal Server Error',
        'message' => 'Something went wrong on our end.',
        'icon' => 'exclamation-triangle'
    ],
    '503' => [
        'title' => 'Service Unavailable',
        'message' => 'The service is temporarily unavailable.',
        'icon' => 'clock'
    ]
];

// Get error type details
$error_type = $error_types[$error_code] ?? $error_types['404'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $error_type['title']; ?> - <?php echo APP_NAME; ?></title>
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
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <div class="text-center">
                    <div class="mb-6">
                        <i class="fas fa-<?php echo $error_type['icon']; ?> text-6xl text-gray-400"></i>
                    </div>

                    <h2 class="text-6xl font-bold text-gray-900 mb-4">
                        <?php echo $error_code; ?>
                    </h2>

                    <h3 class="text-xl font-semibold text-gray-900 mb-2">
                        <?php echo $error_type['title']; ?>
                    </h3>

                    <p class="text-gray-600 mb-6">
                        <?php echo htmlspecialchars($error_message); ?>
                    </p>

                    <div class="space-y-4">
                        <button onclick="window.history.back()" 
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Go Back
                        </button>

                        <?php if (isset($_SESSION['uid'])): ?>
                            <a href="<?php echo $_SESSION['role']; ?>_dashboard.php" 
                               class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                <i class="fas fa-home mr-2"></i>
                                Return to Dashboard
                            </a>
                        <?php else: ?>
                            <a href="index.php" 
                               class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                <i class="fas fa-home mr-2"></i>
                                Return to Home
                            </a>
                        <?php endif; ?>

                        <a href="contact.php" 
                           class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            <i class="fas fa-envelope mr-2"></i>
                            Contact Support
                        </a>
                    </div>

                    <div class="mt-6">
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-300"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-2 bg-white text-gray-500">
                                    Quick Links
                                </span>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-2 gap-3">
                            <a href="login.php" 
                               class="flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Sign In
                            </a>
                            <a href="register.php" 
                               class="flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Register
                            </a>
                            <a href="help.php" 
                               class="flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Help Center
                            </a>
                            <a href="faq.php" 
                               class="flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                FAQ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 text-center">
            <p class="text-xs text-gray-500">
                Error ID: <?php echo uniqid(); ?>
            </p>
            <p class="text-xs text-gray-500">
                <?php echo date('Y-m-d H:i:s'); ?>
            </p>
            <p class="text-xs text-gray-500 mt-2">
                &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
            </p>
        </div>
    </div>

    <?php if (APP_ENV === 'development' && isset($_SERVER['HTTP_REFERER'])): ?>
        <div class="fixed bottom-0 left-0 right-0 bg-gray-800 text-white p-4 text-sm">
            <div class="max-w-7xl mx-auto">
                <div class="flex items-center justify-between">
                    <div>
                        <strong>Debug Info:</strong>
                        Referrer: <?php echo htmlspecialchars($_SERVER['HTTP_REFERER']); ?>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" 
                            class="text-white hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>
