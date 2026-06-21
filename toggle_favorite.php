<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$listing_id = (int)$_GET['id'];

// Check if it's already favorited
$stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND listing_id = ?");
$stmt->execute([$user_id, $listing_id]);

if ($stmt->fetch()) {
    // Un-favorite
    $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND listing_id = ?")->execute([$user_id, $listing_id]);
} else {
    // Favorite
    $pdo->prepare("INSERT INTO favorites (user_id, listing_id) VALUES (?, ?)")->execute([$user_id, $listing_id]);
}

// Send them back to exactly where they clicked the button
header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
?>