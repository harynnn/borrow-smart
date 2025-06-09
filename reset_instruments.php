<?php
require 'dbconnection.php';

// Update all instruments to make them available
$stmt = $pdo->prepare("UPDATE instruments SET availabilityStatus = '1', borrowedBy = NULL, borrowDate = NULL");
$stmt->execute();

echo "All instruments have been reset to available.";
?>
