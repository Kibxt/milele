<?php
// MILELE - Secure Edit Processor

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

require 'db.php';

$user_id = $_SESSION['user_id'];
$listing_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
$title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS);
$price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
$category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS);
$description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS);

if (!$listing_id || empty($title) || !$price) {
    header("Location: profile.php");
    exit();
}

try {
    // 1. Verify Ownership
    $check = $pdo->prepare("SELECT listing_id FROM listings WHERE listing_id = :id AND seller_id = :seller");
    $check->execute([':id' => $listing_id, ':seller' => $user_id]);
    
    if (!$check->fetch()) {
        header("Location: profile.php");
        exit();
    }

    // 2. Handle Optional File Update
    if (isset($_FILES['listing_file']) && $_FILES['listing_file']['error'] === UPLOAD_ERR_OK) {
        // They uploaded a new file. Encode it to Base64
        $tmp_name = $_FILES['listing_file']['tmp_name'];
        $file_type = $_FILES['listing_file']['type'];
        $file_data = file_get_contents($tmp_name);
        $new_file_path = 'data:' . $file_type . ';base64,' . base64_encode($file_data);

        // Update row including the new file
        $update = $pdo->prepare("UPDATE listings SET title = :t, price = :p, category = :c, description = :d, file_path = :f WHERE listing_id = :id");
        $update->execute([':t' => $title, ':p' => $price, ':c' => $category, ':d' => $description, ':f' => $new_file_path, ':id' => $listing_id]);
    } else {
        // They didn't upload a new file. Just update the text fields
        $update = $pdo->prepare("UPDATE listings SET title = :t, price = :p, category = :c, description = :d WHERE listing_id = :id");
        $update->execute([':t' => $title, ':p' => $price, ':c' => $category, ':d' => $description, ':id' => $listing_id]);
    }

    // 3. Send them back to their newly updated profile
    header("Location: profile.php");
    exit();

} catch (PDOException $e) {
    die("System error saving updates.");
}
?>