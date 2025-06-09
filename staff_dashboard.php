<?php
session_start();
require 'dbconnection.php';

// Check if staff is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    header("Location: login.php");
    exit;
}

// Fetch statistics
$totalInstruments = $pdo->query("SELECT COUNT(*) FROM instruments")->fetchColumn();
$totalBorrowed = $pdo->query("SELECT COUNT(*) FROM borrowingrecord")->fetchColumn();
$totalReturns = $pdo->query("SELECT COUNT(*) FROM returningrecord")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard - BorrowSmart</title>
    <link rel="stylesheet" href="style1.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header">
        <img src="/images/borrowsmart.png" alt="BorrowSmart Logo" class="logo">
    </div>
    <ul class="sidebar-menu">
        <li><a href="staff_dashboard.php">Dashboard</a></li>
        <li><a href="manage_instruments.php">Manage Instruments</a></li>
        <li><a href="return_item.php">Return Item</a></li>
        <li><a href="return_history.php">Return History</a></li>
        <li><a href="logout.php" class="logout">Logout</a></li>
    </ul>
</aside>

<!-- Main Content -->
<main class="main-content">
    <header class="main-header">
        <img src="/images/uthmlogo.png" alt="UTHM Logo" class="uthm-logo">
        <h1>Staff Dashboard</h1>
    </header>

    <div class="dashboard-cards">
        <div class="card">
            <h3>Total Instruments</h3>
            <p><?= $totalInstruments ?></p>
            <a href="manage_instruments.php" class="more-info">Manage Instruments</a>
        </div>

        <div class="card">
            <h3>Total Borrowed</h3>
            <p><?= $totalBorrowed ?></p>
            <a href="borrow_item.php" class="more-info">View Borrowed</a>
        </div>

        <div class="card">
            <h3>Total Returns</h3>
            <p><?= $totalReturns ?></p>
            <a href="return_history.php" class="more-info">View Returns</a>
        </div>
    </div>
</main>

</body>
</html>
