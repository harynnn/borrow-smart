<?php
session_start();
require 'dbconnection.php';

// Allow only staff and admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['staff', 'admin'])) {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM instruments WHERE instrumentID = ?");
$stmt->execute([$id]);
$instrument = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$instrument) {
    header("Location: manage_instruments.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type = trim($_POST['type']);
    $availabilityStatus = $_POST['availabilityStatus'];

    $update = $pdo->prepare("UPDATE instruments SET name = ?, type = ?, availabilityStatus = ? WHERE instrumentID = ?");
    $update->execute([$name, $type, $availabilityStatus, $id]);

    header("Location: manage_instruments.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Instrument - BorrowSmart</title>
    <link rel="stylesheet" href="style1.css">
</head>
<body>
<div class="container">
    <h2>Edit Instrument</h2>
    <form action="" method="POST">
        <input type="text" name="name" value="<?= htmlspecialchars($instrument['name']) ?>" required>
        <input type="text" name="type" value="<?= htmlspecialchars($instrument['type']) ?>" required>
        <select name="availabilityStatus" required>
            <option value="1" <?= $instrument['availabilityStatus'] ? 'selected' : '' ?>>Available</option>
            <option value="0" <?= !$instrument['availabilityStatus'] ? 'selected' : '' ?>>Borrowed</option>
        </select>
        <button type="submit">Update Instrument</button>
    </form>
    <a href="manage_instruments.php">Back to Instruments</a>
</div>
</body>
</html>
