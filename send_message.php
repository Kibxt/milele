<?php
// MILELE - Secure Message Processor

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$sender_id = $_SESSION['user_id'];
$receiver_id = filter_input(INPUT_POST, 'receiver_id', FILTER_VALIDATE_INT);
$listing_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
$message_text = trim(filter_input(INPUT_POST, 'message_text', FILTER_SANITIZE_SPECIAL_CHARS));

if ($receiver_id && !empty($message_text)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, listing_id, message_text, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$sender_id, $receiver_id, $listing_id ?: null, $message_text]);
    } catch (PDOException $e) {
        // Silently catch in production
    }
}

// Bounce them back to the chat thread
header("Location: inbox.php?user=" . $receiver_id);
exit();
?>