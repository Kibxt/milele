<?php
// MILELE - Secure Message Processor

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

require 'db.php';

$sender_id = $_SESSION['user_id'];
$receiver_id = filter_input(INPUT_POST, 'receiver_id', FILTER_VALIDATE_INT);
$listing_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
$message_text = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS);

if (!$receiver_id || !$listing_id || empty(trim($message_text))) {
    header("Location: index.php");
    exit();
}

try {
    // 1. Save the message to the cloud
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, listing_id, message_text) VALUES (:sender, :receiver, :listing, :msg)");
    $stmt->execute([
        ':sender' => $sender_id,
        ':receiver' => $receiver_id,
        ':listing' => $listing_id,
        ':msg' => trim($message_text)
    ]);

    // 2. Trigger a notification for the receiver
    $sender_name = explode(' ', $_SESSION['full_name'])[0];
    
    // Check if they already have an unread notification for this chat to avoid spam
    $check_notif = $pdo->prepare("SELECT id FROM notifications WHERE user_id = :uid AND title LIKE 'New Message%' AND is_read = 0");
    $check_notif->execute([':uid' => $receiver_id]);
    
    if (!$check_notif->fetch()) {
        $notif_sql = "INSERT INTO notifications (user_id, title, message, icon, link) VALUES (:uid, :title, :msg, :icon, :link)";
        $pdo->prepare($notif_sql)->execute([
            ':uid' => $receiver_id,
            ':title' => "New Message 💬",
            ':msg' => "{$sender_name} sent you a message regarding an item.",
            ':icon' => "💬",
            ':link' => "chat.php?seller=" . ($sender_id == $receiver_id ? $receiver_id : $sender_id) . "&item=" . $listing_id
        ]);
    }

    // 3. Send them right back to the chat room
    header("Location: chat.php?seller=" . $receiver_id . "&item=" . $listing_id);
    exit();

} catch (PDOException $e) {
    die("System Error processing message.");
}
?>