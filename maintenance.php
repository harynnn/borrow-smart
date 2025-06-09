<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

$page_title = "System Maintenance";

// Get maintenance message from settings
$stmt = $pdo->prepare("
    SELECT setting_value 
    FROM settings 
    WHERE setting_key = 'maintenance_message'
");
$stmt->execute();
$maintenance_message = $stmt->fetchColumn() ?: 'System is undergoing scheduled maintenance.';

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
        <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10 text-center">
            <div class="mb-6">
                <i class="fas fa-tools text-6xl text-blue-500"></i>
            </div>
            
            <h2 class="text-3xl font-bold text-gray-900 mb-4">
                System Maintenance
            </h2>
            
            <div class="rounded-md bg-blue-50 p-4 mb-8">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            Maintenance in Progress
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p><?php echo htmlspecialchars($maintenance_message); ?></p>
                            <p class="mt-2">Please check back later.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="text-sm text-gray-600">
                    <p>Expected completion time:</p>
                    <p class="font-semibold">
                        <?php 
                        $completion_time = $pdo->prepare("
                            SELECT setting_value 
                            FROM settings 
                            WHERE setting_key = 'maintenance_end_time'
                        ");
                        $completion_time->execute();
                        $time = $completion_time->fetchColumn();
                        echo $time ? date('F j, Y g:i A', strtotime($time)) : 'To be determined';
                        ?>
                    </p>
                </div>

                <div class="border-t border-gray-200 pt-4">
                    <p class="text-sm text-gray-600">
                        For urgent inquiries, please contact:
                    </p>
                    <div class="mt-2 space-y-2">
                        <a href="mailto:<?php echo CONTACT_EMAIL; ?>" 
                           class="text-sm text-gray-900 hover:text-gray-700">
                            <i class="fas fa-envelope mr-2"></i>
                            <?php echo CONTACT_EMAIL; ?>
                        </a>
                        <p class="text-sm text-gray-900">
                            <i class="fas fa-phone mr-2"></i>
                            <?php echo CONTACT_PHONE; ?>
                        </p>
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-4">
                    <p class="text-sm text-gray-600">
                        Follow us for updates:
                    </p>
                    <div class="mt-2 flex justify-center space-x-4">
                        <?php if (defined('SOCIAL_FACEBOOK')): ?>
                            <a href="<?php echo SOCIAL_FACEBOOK; ?>" target="_blank" rel="noopener"
                               class="text-gray-400 hover:text-gray-500">
                                <i class="fab fa-facebook text-xl"></i>
                            </a>
                        <?php endif; ?>

                        <?php if (defined('SOCIAL_TWITTER')): ?>
                            <a href="<?php echo SOCIAL_TWITTER; ?>" target="_blank" rel="noopener"
                               class="text-gray-400 hover:text-gray-500">
                                <i class="fab fa-twitter text-xl"></i>
                            </a>
                        <?php endif; ?>

                        <?php if (defined('SOCIAL_INSTAGRAM')): ?>
                            <a href="<?php echo SOCIAL_INSTAGRAM; ?>" target="_blank" rel="noopener"
                               class="text-gray-400 hover:text-gray-500">
                                <i class="fab fa-instagram text-xl"></i>
                            </a>
                        <?php endif; ?>
                    </div>
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
