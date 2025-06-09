<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

$page_title = "My Profile";

// Check if user is logged in
if (!isset($_SESSION['uid'])) {
    $_SESSION['error'] = "Please sign in to access your profile.";
    $_SESSION['intended_url'] = $_SERVER['PHP_SELF'];
    header('Location: login.php');
    exit();
}

// Get user details
$stmt = $pdo->prepare("
    SELECT u.*, r.role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.role_id 
    WHERE u.uid = ?
");
$stmt->execute([$_SESSION['uid']]);
$user = $stmt->fetch();

// Get user's recent activity
$stmt = $pdo->prepare("
    SELECT * FROM security_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['uid']]);
$recent_activity = $stmt->fetchAll();

// Get active sessions
$stmt = $pdo->prepare("
    SELECT * FROM sessions 
    WHERE user_id = ? 
    AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ORDER BY created_at DESC
");
$stmt->execute([$_SESSION['uid']]);
$active_sessions = $stmt->fetchAll();

// Include header
include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-100">
    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow rounded-lg divide-y divide-gray-200">
                <!-- Profile Header -->
                <div class="px-4 py-5 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <img class="h-12 w-12 rounded-full" src="/images/default-avatar.png" alt="Profile">
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium leading-6 text-gray-900">
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </h3>
                                <p class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </p>
                            </div>
                        </div>
                        <div>
                            <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                <?php echo ucfirst(htmlspecialchars($user['role_name'])); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Profile Information -->
                <div class="px-4 py-5 sm:p-6">
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Basic Information -->
                        <section aria-labelledby="basic-info-title">
                            <div class="bg-gray-50 rounded-lg p-6">
                                <h2 id="basic-info-title" class="text-lg font-medium text-gray-900 mb-4">
                                    Basic Information
                                </h2>
                                <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                                        <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($user['name']); ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                                        <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Matric Number</dt>
                                        <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($user['matric']); ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Department</dt>
                                        <dd class="mt-1 text-sm text-gray-900"><?php echo DEPARTMENTS[$user['department']] ?? 'Unknown'; ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Member Since</dt>
                                        <dd class="mt-1 text-sm text-gray-900"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Last Login</dt>
                                        <dd class="mt-1 text-sm text-gray-900"><?php echo $user['last_login'] ? date('F j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></dd>
                                    </div>
                                </dl>
                            </div>
                        </section>

                        <!-- Security Settings -->
                        <section aria-labelledby="security-title">
                            <div class="bg-gray-50 rounded-lg p-6">
                                <h2 id="security-title" class="text-lg font-medium text-gray-900 mb-4">
                                    Security Settings
                                </h2>
                                <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Two-Factor Authentication</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            <?php if ($user['two_factor_enabled']): ?>
                                                <span class="text-green-600">Enabled</span>
                                            <?php else: ?>
                                                <span class="text-red-600">Disabled</span>
                                                <a href="enable_2fa.php" class="ml-2 text-gray-900 hover:underline">Enable</a>
                                            <?php endif; ?>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Password Last Changed</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            <?php echo $user['password_changed_at'] ? date('F j, Y', strtotime($user['password_changed_at'])) : 'Never'; ?>
                                            <a href="change_password.php" class="ml-2 text-gray-900 hover:underline">Change</a>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </section>

                        <!-- Recent Activity -->
                        <section aria-labelledby="activity-title">
                            <div class="bg-gray-50 rounded-lg p-6">
                                <h2 id="activity-title" class="text-lg font-medium text-gray-900 mb-4">
                                    Recent Activity
                                </h2>
                                <div class="flow-root">
                                    <ul class="-mb-8">
                                        <?php foreach ($recent_activity as $index => $activity): ?>
                                            <li>
                                                <div class="relative pb-8">
                                                    <?php if ($index !== count($recent_activity) - 1): ?>
                                                        <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                                    <?php endif; ?>
                                                    <div class="relative flex space-x-3">
                                                        <div>
                                                            <span class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center ring-8 ring-white">
                                                                <i class="fas fa-shield-alt text-gray-500"></i>
                                                            </span>
                                                        </div>
                                                        <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                            <div>
                                                                <p class="text-sm text-gray-500">
                                                                    <?php echo htmlspecialchars($activity['event_type']); ?>:
                                                                    <span class="font-medium text-gray-900">
                                                                        <?php echo htmlspecialchars($activity['description']); ?>
                                                                    </span>
                                                                </p>
                                                            </div>
                                                            <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                                <?php echo timeAgo($activity['created_at']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div class="mt-6">
                                    <a href="activity_log.php" class="text-sm font-medium text-gray-900 hover:underline">
                                        View full activity log
                                        <span aria-hidden="true"> &rarr;</span>
                                    </a>
                                </div>
                            </div>
                        </section>

                        <!-- Active Sessions -->
                        <section aria-labelledby="sessions-title">
                            <div class="bg-gray-50 rounded-lg p-6">
                                <h2 id="sessions-title" class="text-lg font-medium text-gray-900 mb-4">
                                    Active Sessions
                                </h2>
                                <div class="space-y-4">
                                    <?php foreach ($active_sessions as $session): ?>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <i class="fas fa-desktop text-gray-400 mr-3"></i>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($session['user_agent']); ?>
                                                    </p>
                                                    <p class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($session['ip_address']); ?> •
                                                        Last active <?php echo timeAgo($session['last_activity']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <?php if ($session['session_id'] !== session_id()): ?>
                                                <form action="terminate_session.php" method="POST" class="flex-shrink-0">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($session['session_id']); ?>">
                                                    <button type="submit" class="text-sm text-red-600 hover:text-red-900">
                                                        Terminate
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Current Session
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($active_sessions) > 1): ?>
                                    <div class="mt-6">
                                        <form action="terminate_all_sessions.php" method="POST">
                                            <?php echo csrf_field(); ?>
                                            <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-900">
                                                Sign out of all other sessions
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <!-- Account Actions -->
                        <section aria-labelledby="account-actions-title">
                            <div class="bg-gray-50 rounded-lg p-6">
                                <h2 id="account-actions-title" class="text-lg font-medium text-gray-900 mb-4">
                                    Account Actions
                                </h2>
                                <div class="space-y-4">
                                    <a href="edit_profile.php" 
                                       class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                        <i class="fas fa-edit mr-2"></i>
                                        Edit Profile
                                    </a>
                                    <a href="change_password.php" 
                                       class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                        <i class="fas fa-key mr-2"></i>
                                        Change Password
                                    </a>
                                    <?php if (!$user['two_factor_enabled']): ?>
                                        <a href="enable_2fa.php" 
                                           class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                            <i class="fas fa-shield-alt mr-2"></i>
                                            Enable Two-Factor Authentication
                                        </a>
                                    <?php endif; ?>
                                    <form action="deactivate_account.php" method="POST" 
                                          onsubmit="return confirm('Are you sure you want to deactivate your account? This action cannot be undone.');">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" 
                                                class="w-full inline-flex justify-center py-2 px-4 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                            <i class="fas fa-user-times mr-2"></i>
                                            Deactivate Account
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </section>
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
