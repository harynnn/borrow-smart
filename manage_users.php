<?php
session_start();
require 'dbconnection.php';

// Only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Fetch all users
$stmt = $pdo->query("SELECT * FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - BorrowSmart</title>
    <link rel="stylesheet" href="style1.css">
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <img src="/images/borrowsmart.png" alt="BorrowSmart Logo" class="logo">
    </div>
    <ul class="sidebar-menu">
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="manage_users.php">Manage Users</a></li>
        <li><a href="manage_instruments.php">Manage Instruments</a></li>
        <li><a href="logout.php" class="logout">Logout</a></li>
    </ul>
</aside>

<main class="main-content">
    <header class="main-header">
        <h1>Manage Users</h1>
    </header>

    <div class="container">
    <a href="add_user.php" class="btn-add">+ Add New User</a>
    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php if (!empty($users)): ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['phone']) ?></td>
                        <td><?= ucfirst($user['role']) ?></td>
                        <td>
                            <a href="edit_user.php?id=<?= $user['uid'] ?>">Edit</a> |
                            <a href="delete_user.php?id=<?= $user['uid'] ?>" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center;">No users found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</main>

</body>
</html>