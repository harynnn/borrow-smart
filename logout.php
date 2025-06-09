<?php
define('SECURE_ACCESS', true);
require_once 'includes/init.php';

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Get user ID before destroying session
    $userId = $_SESSION['uid'] ?? null;

    // Check if this is a security logout
    $security = isset($_GET['security']) && $_GET['security'] === 'true';

    // Check if session expired
    $expired = isset($_GET['expired']) && $_GET['expired'] === 'true';

    if ($userId) {
        // Update last logout timestamp
        $stmt = $pdo->prepare("
            UPDATE users 
            SET last_logout = NOW()
            WHERE uid = ?
        ");
        $stmt->execute([$userId]);

        // Remove remember me token if exists
        if (isset($_COOKIE['remember_token'])) {
            $stmt = $pdo->prepare("
                DELETE FROM remember_tokens 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);

            // Remove cookie
            setcookie(
                'remember_token',
                '',
                time() - 3600,
                COOKIE_PATH,
                COOKIE_DOMAIN,
                COOKIE_SECURE,
                COOKIE_HTTPONLY
            );
        }

        // Log the logout event
        $eventType = $security ? 'SECURITY_LOGOUT' : 
                    ($expired ? 'SESSION_EXPIRED' : 'USER_LOGOUT');
        
        $description = $security ? 'User logged out for security reasons' : 
                      ($expired ? 'User session expired' : 'User logged out');

        $securityHelper->logSecurityEvent(
            $userId,
            $eventType,
            $description
        );
    }

    // Commit transaction
    $pdo->commit();

    // Clear all session data
    $_SESSION = array();

    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(
            session_name(),
            '',
            time() - 3600,
            COOKIE_PATH,
            COOKIE_DOMAIN,
            COOKIE_SECURE,
            COOKIE_HTTPONLY
        );
    }

    // Destroy the session
    session_destroy();

    // Start new session for messages
    session_start();

    // Set appropriate message
    if ($security) {
        $_SESSION['error'] = "You have been logged out for security reasons.";
    } elseif ($expired) {
        $_SESSION['error'] = "Your session has expired. Please sign in again.";
    } else {
        $_SESSION['success'] = "You have been successfully logged out.";
    }

} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollBack();

    error_log("Logout Error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred during logout. Please try again.";
}

// Redirect to login page
header('Location: login.php');
exit();
?>
