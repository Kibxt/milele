<?php
// MILELE - Secure Listing Processor

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Block unauthorized access
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// ⚡ THE MASTER CONNECTION
require 'db.php';

$seller_id = $_SESSION['user_id'];
$title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS);
$price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
$category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS);
$description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS);
$item_type = filter_input(INPUT_POST, 'item_type', FILTER_SANITIZE_SPECIAL_CHARS); // 'physical' or 'digital'

// Handle the image/file upload safely
$file_path = null;
if (isset($_FILES['listing_file']) && $_FILES['listing_file']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/';
    
    // Create the uploads folder if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate a unique filename so images don't overwrite each other
    $file_extension = pathinfo($_FILES['listing_file']['name'], PATHINFO_EXTENSION);
    $new_file_name = 'milele_' . uniqid('', true) . '.' . $file_extension;
    $target_file = $upload_dir . $new_file_name;
    
    if (move_uploaded_file($_FILES['listing_file']['tmp_name'], $target_file)) {
        $file_path = $target_file;
    }
}

try {
    // Post the item to the live cloud database
    $stmt = $pdo->prepare("INSERT INTO listings (seller_id, title, price, category, description, file_path, item_type, listing_status) 
                           VALUES (:seller, :title, :price, :category, :desc, :file, :type, 'active')");
    
    $stmt->execute([
        ':seller' => $seller_id,
        ':title' => $title,
        ':price' => $price,
        ':category' => $category,
        ':desc' => $description,
        ':file' => $file_path,
        ':type' => $item_type ?? 'physical'
    ]);

    // Send the user back to the main feed to see their new item
    header("Location: index.php");
    exit();

} catch (PDOException $e) {
    $_SESSION['error_msg'] = "Error posting your listing to the live server.";
    header("Location: Notesing.php");
    exit();
}
?>