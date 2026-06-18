<?php
// MILELE - Background AJAX Save Processor

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Tell the browser we are sending back JSON data, not HTML
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['listing_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$listing_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);

if (!$listing_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid item']);
    exit();
}

// Database Connection
$db_host = 'localhost'; $db_name = 'milele_escrow'; $db_user = 'root'; $db_pass = ''; 
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database offline']);
    exit();
}

// Check if the item is already saved
$stmt = $pdo->prepare("SELECT save_id FROM saved_items WHERE user_id = :uid AND listing_id = :lid");
$stmt->execute([':uid' => $user_id, ':lid' => $listing_id]);
$existing = $stmt->fetch();

if ($existing) {
    // If it exists, UN-SAVE it (delete)
    $del = $pdo->prepare("DELETE FROM saved_items WHERE user_id = :uid AND listing_id = :lid");
    $del->execute([':uid' => $user_id, ':lid' => $listing_id]);
    echo json_encode(['status' => 'removed']);
} else {
    // If it doesn't exist, SAVE it (insert)
    $ins = $pdo->prepare("INSERT INTO saved_items (user_id, listing_id) VALUES (:uid, :lid)");
    $ins->execute([':uid' => $user_id, ':lid' => $listing_id]);
    echo json_encode(['status' => 'saved']);
}