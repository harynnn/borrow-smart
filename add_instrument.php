<?php
session_start();
require 'dbconnection.php';

// Allow only staff and admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['staff', 'admin'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type = trim($_POST['type']);
    $availabilityStatus = 1; // Available by default

    $stmt = $pdo->prepare("INSERT INTO instruments (name, type, availabilityStatus) VALUES (?, ?, ?)");
    $stmt->execute([$name, $type, $availabilityStatus]);

    header("Location: manage_instruments.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Instrument - BorrowSmart</title>
    <link rel="stylesheet" href="style1.css">
</head>
<body>
<div class="container">
    <h2>Add New Instrument</h2>
    <form action="" method="POST">
        <input type="text" name="name" placeholder="Instrument Name" required>
        <input type="text" name="type" placeholder="Instrument Type" required>
        <button type="submit">Add Instrument</button>
    </form>
    <a href="manage_instruments.php">Back to Instruments</a>
</div>
</body>
</html>
