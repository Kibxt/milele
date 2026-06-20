<?php
// MILELE - Secure Listing Processor (Permanent Cloud Storage)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

require 'db.php';

$seller_id = $_SESSION['user_id'];
$title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS);
$price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
$category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS);
$description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS);
$item_type = filter_input(INPUT_POST, 'item_type', FILTER_SANITIZE_SPECIAL_CHARS);

// Convert the uploaded file into a permanent Base64 string
$file_path = null;
if (isset($_FILES['listing_file']) && $_FILES['listing_file']['error'] === UPLOAD_ERR_OK) {
    $tmp_name = $_FILES['listing_file']['tmp_name'];
    $file_type = $_FILES['listing_file']['type'];
    $file_data = file_get_contents($tmp_name);
    
    // Create the Data URI string that the database can hold forever
    $file_path = 'data:' . $file_type . ';base64,' . base64_encode($file_data);
}

try {
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

    header("Location: index.php");
    exit();

} catch (PDOException $e) {
    $_SESSION['error_msg'] = "Error posting your listing to the live server.";
    header("Location: Notesing.php");
    exit();
}
?>