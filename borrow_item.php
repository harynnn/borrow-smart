<?php
session_start();
require 'dbconnection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle the borrowing action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userID = $_SESSION['user_id'];
    $instrumentID = $_POST['instrument_id'];
    $borrowDate = date('Y-m-d');

    // Check instrument availability
    $stmt = $pdo->prepare("SELECT availabilityStatus FROM instruments WHERE instrumentID = ?");
    $stmt->execute([$instrumentID]);
    $instrument = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($instrument && $instrument['availabilityStatus'] == 1) { // 1 = Available
        // Update the instrument's status to 'Borrowed'
        $stmt = $pdo->prepare("UPDATE instruments SET availabilityStatus = 'Borrowed', borrowedBy = ?, borrowDate = ? WHERE instrumentID = ?");
        $stmt->execute([$userID, $borrowDate, $instrumentID]);

        $message = "Instrument successfully borrowed!";
    } else {
        $message = "Instrument is not available.";
    }
}

// Fetch all instruments
$stmt = $pdo->query("SELECT * FROM instruments");
$instruments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define instrument images
$instrumentImages = [
    'Trumpet' => '/images/trumpet.jpg',
    'Trombone' => '/images/trombone.jpg',
    'Snare Drum' => '/images/snare.jpeg',
    'Euphonium' => '/images/euphonium.jpg'
];
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Item</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        /* Additional styles for borrow_item.php */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        h2 {
            text-align: center;
        }

        th, td {
            padding: 10px;
            text-align: center;
        }

        th {
            background-color: #f4f4f4;
        }

        .borrow-button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 5px;
        }

        .borrow-button:hover {
            background-color: #45a049;
        }

        .message {
            margin-top: 10px;
            color: green;
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
            <h1>Instruments</h1>
        </header>

    <div class="container">
        <h2>Borrow Item</h2>

        <?php if (isset($message)): ?>
            <p class="message"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Image</th>
                    <th>Instrument</th>
                    <th>Status</th>
                    <th>Instrument Category</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($instruments as $index => $instrument): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td>
                            <img src="<?= $instrumentImages[$instrument['name']] ?? '/images/default.png' ?>" alt="<?= htmlspecialchars($instrument['name']) ?>" width="50">
                        </td>
                        <td><?= htmlspecialchars($instrument['name']) ?></td>
                        <td><?= htmlspecialchars($instrument['availabilityStatus']) ?></td>
                        <td><?= htmlspecialchars($instrument['category']) ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="instrument_id" value="<?= $instrument['instrumentID'] ?>">
                                <button type="submit" class="borrow-button">Borrow</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>