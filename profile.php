<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

$page_title = "My Profile";

// Require authentication
require_once 'middleware.php';

try {
    // Get user details
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name,
            (SELECT COUNT(*) FROM requests WHERE user_id = u.uid) as total_requests,
            (SELECT COUNT(*) FROM requests WHERE user_id = u.uid AND status = 'approved') as approved_requests,
            (SELECT COUNT(*) FROM requests WHERE user_id = u.uid AND status = 'pending') as pending_requests
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.uid = ?
    ");
    $stmt->execute([$_SESSION['uid']]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User not found.");
    }

    // Get recent activity
    $stmt = $pdo->prepare("
        SELECT event_type, description, created_at
        FROM security_logs
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['uid']]);
    $activities = $stmt->fetchAll();

    // Get active sessions
    $stmt = $pdo->prepare("
        SELECT device_info, ip_address, last_activity
        FROM sessions
        WHERE user_id = ?
        AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ORDER BY last_activity DESC
    ");
    $stmt->execute([$_SESSION['uid']]);
    $sessions = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Profile Error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while loading your profile.";
    header('Location: ' . $_SESSION['role'] . '_dashboard.php');
    exit();
}

// Include header
include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Profile Information -->
        <div class="lg:col-span-2">
            <div class="bg-white shadow rounded-lg">
                <div class="p-6">
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <img class="h-24 w-24 rounded-full" src="/images/default-avatar.png" alt="Profile Picture">
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">
                                <?php echo htmlspecialchars($user['name']); ?>
                            </h1>
                            <p class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            <p class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($user['matric']); ?> - 
                                <?php echo htmlspecialchars(DEPARTMENTS[$user['department']]); ?>
                            </p>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 mt-2">
                                <?php echo ucfirst($user['role_name']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-gray-200 pt-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="text-sm font-medium text-gray-500">Total Requests</div>
                                <div class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo $user['total_requests']; ?>
                                </div>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="text-sm font-medium text-gray-500">Approved</div>
                                <div class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo $user['approved_requests']; ?>
                                </div>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="text-sm font-medium text-gray-500">Pending</div>
                                <div class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo $user['pending_requests']; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-gray-200 pt-6">
                        <h2 class="text-lg font-medium text-gray-900">Account Information</h2>
                        <dl class="mt-4 grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Account Status</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <?php if ($user['status'] === 'active'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Member Since</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Last Login</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <?php echo $user['last_login'] ? date('F j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Two-Factor Authentication</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <?php if ($user['two_factor_enabled']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Enabled
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            Disabled
                                        </span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="mt-6 border-t border-gray-200 pt-6">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-medium text-gray-900">Active Sessions</h2>
                            <button type="button" onclick="if(confirm('Are you sure you want to end all other sessions?')) window.location.href='logout_all.php';"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                End All Other Sessions
                            </button>
                        </div>
                        <div class="mt-4 space-y-4">
                            <?php foreach ($sessions as $session): ?>
                                <div class="flex items-center justify-between bg-gray-50 p-4 rounded-lg">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($session['device_info']); ?>
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            IP: <?php echo htmlspecialchars($session['ip_address']); ?>
                                        </p>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Last active: <?php echo timeAgo($session['last_activity']); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="lg:col-span-1">
            <div class="bg-white shadow rounded-lg">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Recent Activity</h2>
                    <div class="mt-4 flow-root">
                        <ul role="list" class="-mb-8">
                            <?php foreach ($activities as $index => $activity): ?>
                                <li>
                                    <div class="relative pb-8">
                                        <?php if ($index !== count($activities) - 1): ?>
                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full bg-gray-400 flex items-center justify-center ring-8 ring-white">
                                                    <?php
                                                    $icon = 'user';
                                                    if (strpos($activity['event_type'], 'LOGIN') !== false) {
                                                        $icon = 'sign-in-alt';
                                                    } elseif (strpos($activity['event_type'], 'LOGOUT') !== false) {
                                                        $icon = 'sign-out-alt';
                                                    } elseif (strpos($activity['event_type'], 'PASSWORD') !== false) {
                                                        $icon = 'key';
                                                    } elseif (strpos($activity['event_type'], '2FA') !== false) {
                                                        $icon = 'shield-alt';
                                                    }
                                                    ?>
                                                    <i class="fas fa-<?php echo $icon; ?> text-white"></i>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div>
                                                    <p class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($activity['description']); ?>
                                                    </p>
                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                        <?php echo timeAgo($activity['created_at']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (empty($activities)): ?>
                            <p class="text-sm text-gray-500 text-center py-4">No recent activity</p>
                        <?php endif; ?>
                    </div>
                    <div class="mt-6">
                        <a href="activity_log.php" class="block w-full text-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            View Full Activity Log
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-8 bg-white shadow rounded-lg">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Account Security</h2>
                    <div class="mt-4 space-y-4">
                        <a href="change_password.php" 
                           class="block w-full text-left px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <i class="fas fa-key mr-2"></i>
                            Change Password
                        </a>
                        <?php if ($user['two_factor_enabled']): ?>
                            <a href="disable_2fa.php" 
                               class="block w-full text-left px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <i class="fas fa-shield-alt mr-2"></i>
                                Disable Two-Factor Authentication
                            </a>
                        <?php else: ?>
                            <a href="enable_2fa.php" 
                               class="block w-full text-left px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <i class="fas fa-shield-alt mr-2"></i>
                                Enable Two-Factor Authentication
                            </a>
                        <?php endif; ?>
                        <a href="#" onclick="if(confirm('Are you sure you want to deactivate your account?')) window.location.href='deactivate_account.php';"
                           class="block w-full text-left px-4 py-2 border border-red-300 shadow-sm text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50">
                            <i class="fas fa-user-times mr-2"></i>
                            Deactivate Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>
