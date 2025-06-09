<?php
session_start();
require 'dbconnection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch the return history for the logged-in user
$uid = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT rh.*, i.name AS instrumentName
    FROM return_history rh
    JOIN instruments i ON rh.instrumentID = i.instrumentID
    WHERE rh.uid = ?
    ORDER BY rh.returnDate DESC
");
$stmt->execute([$uid]);
$returnHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define instrument images
$instrumentImages = [
    'Trumpet' => 'images/trumpet.jpg',
    'Trombone' => 'images/trombone.jpg',
    'Snare Drum' => 'images/snare.jpeg',
    'Euphonium' => 'images/euphonium.jpg'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return History</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        h2 {
            text-align: center;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }

        th {
            background-color: #f4f4f4;
        }
    </style>
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
            <h1>Return History</h1>
        </header>
    <div class="container">
        <h2>Your Return History</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Image</th>
                    <th>Instrument</th>
                    <th>Borrow Date</th>
                    <th>Return Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($returnHistory as $index => $record): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td>
                            <img src="<?= $instrumentImages[$record['instrumentName']] ?? 'images/default.png' ?>" alt="<?= htmlspecialchars($record['instrumentName']) ?>" width="50">
                        </td>
                        <td><?= htmlspecialchars($record['instrumentName']) ?></td>
                        <td><?= htmlspecialchars($record['borrowDate']) ?></td>
                        <td><?= htmlspecialchars($record['returnDate']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
