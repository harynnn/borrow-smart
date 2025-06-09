<?php
session_start();
require 'dbconnection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle the return action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $instrumentID = $_POST['instrument_id'];

    // Fetch the borrow date and user ID
    $stmt = $pdo->prepare("SELECT borrowDate, borrowedBy FROM instruments WHERE instrumentID = ?");
    $stmt->execute([$instrumentID]);
    $instrument = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($instrument) {
        $borrowDate = $instrument['borrowDate'];
        $userID = $instrument['borrowedBy'];
        $returnDate = date('Y-m-d');

        // Insert the return record into the history table
        $stmt = $pdo->prepare("INSERT INTO return_history (instrumentID, uid, borrowDate, returnDate) VALUES (?, ?, ?, ?)");
        $stmt->execute([$instrumentID, $userID, $borrowDate, $returnDate]);

        // Update the instrument's status to 'Available'
        $stmt = $pdo->prepare("UPDATE instruments SET availabilityStatus = 'Available', borrowedBy = NULL, borrowDate = NULL WHERE instrumentID = ?");
        $stmt->execute([$instrumentID]);

        $message = "Instrument successfully returned!";
    }
}

// Fetch all borrowed instruments by the user
$userID = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM instruments WHERE borrowedBy = ?");
$stmt->execute([$userID]);
$instruments = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Return Item</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        /* Additional styles for return_item.php */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        h2 {
            text-align: center;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th, td {
            padding: 10px;
            text-align: center;
        }

        th {
            background-color: #f4f4f4;
        }

        .return-button {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 5px;
        }

        .return-button:hover {
            background-color: #e53935;
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
        <h2>Return Item</h2>

        <?php if (isset($message)): ?>
            <p class="message"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Image</th>
                    <th>Instrument</th>
                    <th>Borrow Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($instruments as $index => $instrument): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td>
                            <img src="<?= $instrumentImages[$instrument['name']] ?? 'images/default.png' ?>" alt="<?= htmlspecialchars($instrument['name']) ?>" width="50">
                        </td>
                        <td><?= htmlspecialchars($instrument['name']) ?></td>
                        <td><?= htmlspecialchars($instrument['borrowDate']) ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="instrument_id" value="<?= $instrument['instrumentID'] ?>">
                                <button type="submit" class="return-button">Return</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
