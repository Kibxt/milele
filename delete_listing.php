<?php
// MILELE - Secure Soft-Delete Engine

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Gate
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$listing_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$listing_id) {
    header("Location: profile.php");
    exit();
}

// Database Connection
$db_host = 'localhost'; $db_name = 'milele_escrow'; $db_user = 'root'; $db_pass = ''; 
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("System Offline.");
}

// The Soft Delete: We ensure the user deleting it is actually the seller
$sql = "UPDATE listings SET listing_status = 'deleted' WHERE listing_id = :lid AND seller_id = :uid";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':lid' => $listing_id, 
    ':uid' => $_SESSION['user_id']
]);

// Bounce them back to the profile page
header("Location: profile.php");
exit();
?>