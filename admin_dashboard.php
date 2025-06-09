<?php
session_start();
require 'dbconnection.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Fetch statistics
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStaff = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetchColumn();
$totalInstruments = $pdo->query("SELECT COUNT(*) FROM instruments")->fetchColumn();
$totalReports = $pdo->query("SELECT COUNT(*) FROM generatereports")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - BorrowSmart</title>
    <link rel="stylesheet" href="style1.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header">
        <img src="/images/borrowsmart.png" alt="BorrowSmart Logo" class="logo">
    </div>
    <ul class="sidebar-menu">
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="manage_users.php">Manage Users</a></li>
        <li><a href="manage_instruments.php">Manage Instruments</a></li>
        <li><a href="generatereport.php">Generate Reports</a></li>
        <li><a href="logout.php" class="logout">Logout</a></li>
    </ul>
</aside>

<!-- Main Content -->
<main class="main-content">
    <header class="main-header">
        <img src="/images/uthmlogo.png" alt="UTHM Logo" class="uthm-logo">
        <h1>Admin Dashboard</h1>
    </header>

    <div class="dashboard-cards">
        <div class="card">
            <h3>Total Users</h3>
            <p><?= $totalUsers ?></p>
            <a href="manage_users.php" class="more-info">Manage Users</a>
        </div>

        <div class="card">
            <h3>Total Staff</h3>
            <p><?= $totalStaff ?></p>
            <a href="manage_users.php" class="more-info">View Staff</a>
        </div>

        <div class="card">
            <h3>Total Instruments</h3>
            <p><?= $totalInstruments ?></p>
            <a href="manage_instruments.php" class="more-info">Manage Instruments</a>
        </div>

        <div class="card">
            <h3>Reports Generated</h3>
            <p><?= $totalReports ?></p>
            <a href="generatereport.php" class="more-info">Generate Reports</a>
        </div>
    </div>
</main>

</body>
</html>
