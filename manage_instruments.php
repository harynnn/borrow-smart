<?php
session_start();
require 'dbconnection.php';

// Only allow admin and staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header("Location: login.php");
    exit;
}

// Fetch all instruments
$stmt = $pdo->query("SELECT * FROM instruments");
$instruments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Instruments - BorrowSmart</title>
    <link rel="stylesheet" href="style1.css">
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <img src="/images/borrowsmart.png" alt="BorrowSmart Logo" class="logo">
    </div>
    <ul class="sidebar-menu">
        <li><a href="<?= $_SESSION['user_role'] === 'admin' ? 'admin_dashboard.php' : 'staff_dashboard.php'; ?>">Dashboard</a></li>
        <li><a href="manage_instruments.php">Manage Instruments</a></li>
        <li><a href="logout.php" class="logout">Logout</a></li>
    </ul>
</aside>

<main class="main-content">
    <header class="main-header">
        <h1>Manage Instruments</h1>
    </header>

    <div class="container">
    <a href="add_instrument.php" class="btn-add">+ Add New Instrument</a>

    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Instrument Name</th>
                <th>Type</th>
                <th>Availability</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php if (!empty($instruments)): ?>
                <?php foreach ($instruments as $instrument): ?>
                    <tr>
                        <td><?= htmlspecialchars($instrument['name']) ?></td>
                        <td><?= htmlspecialchars($instrument['type']) ?></td>
                        <td><?= $instrument['availabilityStatus'] ? 'Available' : 'Borrowed'; ?></td>
                        <td>
                            <a href="edit_instrument.php?id=<?= $instrument['instrumentID'] ?>">Edit</a> |
                            <a href="delete_instrument.php?id=<?= $instrument['instrumentID'] ?>" onclick="return confirm('Are you sure you want to delete this instrument?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center;">No instruments found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</main>

</body>
</html>
