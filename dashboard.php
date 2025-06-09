<?php
session_start();
require 'dbconnection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch counts for dashboard cards
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalInstruments = $pdo->query("SELECT COUNT(*) FROM instruments")->fetchColumn();
$totalBorrowed = $pdo->query("SELECT COUNT(*) FROM borrowingrecord")->fetchColumn();
$totalReturns = $pdo->query("SELECT COUNT(*) FROM returningrecord")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style1.css">
    <title>Dashboard</title>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="/images/borrowsmart.png" alt="BorrowSmart Logo">
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="borrow_item.php">Borrow Item</a></li>
            <li><a href="return_item.php">Return Item</a></li>
            <li><a href="return_history.php">Return History</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="main-header">
            <img src="/images/uthmlogo.png" alt="UTHM Logo">
            <h1>Dashboard</h1>
        </header>
        <div class="dashboard-cards">
            <div class="card">
                <h3>Total Users</h3>
                <p><?= $totalUsers ?></p>
                <a href="users.php">More info</a>
            </div>
            <div class="card">
                <h3>Total Instruments</h3>
                <p><?= $totalInstruments ?></p>
                <a href="instruments.php">More info</a>
            </div>
            <div class="card">
                <h3>Total Borrowed</h3>
                <p><?= $totalBorrowed ?></p>
                <a href="borrow_item.php">More info</a>
            </div>
            <div class="card">
                <h3>Total Returns</h3>
                <p><?= $totalReturns ?></p>
                <a href="return_history.php">More info</a>
            </div>
        </div>
    </main>
</body>
</html>
