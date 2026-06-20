<?php
// MILELE - Invisible Save/Unsave Processor

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$user_id = $_SESSION['user_id'];
$listing_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

try {
    // 1. Check if the item is already saved
    $stmt = $pdo->prepare("SELECT id FROM saved_items WHERE user_id = :uid AND listing_id = :lid");
    $stmt->execute([':uid' => $user_id, ':lid' => $listing_id]);
    
    if ($stmt->fetch()) {
        // 2. If it is already saved, clicking the button again REMOVES it (Unsave)
        $delete = $pdo->prepare("DELETE FROM saved_items WHERE user_id = :uid AND listing_id = :lid");
        $delete->execute([':uid' => $user_id, ':lid' => $listing_id]);
    } else {
        // 3. If it is not saved, ADD it to their favorites
        $insert = $pdo->prepare("INSERT INTO saved_items (user_id, listing_id) VALUES (:uid, :lid)");
        $insert->execute([':uid' => $user_id, ':lid' => $listing_id]);
    }

} catch (PDOException $e) {
    // Fail silently so the user experience isn't interrupted
}

// 4. Send the user back to the exact page they were just on
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: " . $referer);
exit();
?>