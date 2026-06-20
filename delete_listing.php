<?php
// MILELE - Secure Listing Removal

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Ensure the user is logged in
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$user_id = $_SESSION['user_id'];
$listing_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

try {
    // 2. Security Check: Ensure the user actually owns the item they are trying to delete
    $check = $pdo->prepare("SELECT listing_id FROM listings WHERE listing_id = :id AND seller_id = :seller");
    $check->execute([':id' => $listing_id, ':seller' => $user_id]);
    
    if ($check->fetch()) {
        // 3. Soft Delete: Change the status to 'deleted' so it vanishes from the feed
        // but preserves transaction history and database integrity.
        $delete = $pdo->prepare("UPDATE listings SET listing_status = 'deleted' WHERE listing_id = :id");
        $delete->execute([':id' => $listing_id]);
        
        // Also remove it from anyone's saved favorites so they don't click a dead link
        $clean_saves = $pdo->prepare("DELETE FROM saved_items WHERE listing_id = :id");
        $clean_saves->execute([':id' => $listing_id]);
    }

} catch (PDOException $e) {
    // Fail silently to protect cloud details
}

// 4. Send them right back to their profile
header("Location: profile.php");
exit();
?>