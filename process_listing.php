<?php
// MILELE - Secure Listing Processor & Image Handler

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Security Gate
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Ensure the user is verified
if ($_SESSION['account_state'] === 'registered') {
    $_SESSION['error_msg'] = "You must verify your student email before posting items.";
    header("Location: verification_center.php");
    exit();
}

// 2. Connect to the Vault
$db_host = 'localhost'; $db_name = 'milele_escrow'; $db_user = 'root'; $db_pass = ''; 
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("System Error: Cannot connect to the database.");
}

// 3. Capture & Sanitize Text Inputs
$seller_id   = $_SESSION['user_id'];
$title       = trim(filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS));
$description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS));
$price       = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
$category    = $_POST['category'] ?? 'electronics';
$item_type   = $_POST['item_type'] ?? 'physical';

// Basic Validation - Routes back to Notesing.php on failure
if (!$title || !$description || !$price || $price < 50) {
    $_SESSION['error_msg'] = "Please fill out all fields correctly. Minimum price is KES 50.";
    header("Location: Notesing.php");
    exit();
}

// 4. Secure Image Upload Pipeline
$final_image_path = null;

if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
    
    $file_tmp_path  = $_FILES['item_image']['tmp_name'];
    $file_name      = $_FILES['item_image']['name'];
    $file_size      = $_FILES['item_image']['size'];
    
    // Size check
    if ($file_size > 5000000) {
        $_SESSION['error_msg'] = "Image is too large. Upload under 5MB.";
        header("Location: Notesing.php");
        exit();
    }

    // Strict MIME type check
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_tmp_path);
    finfo_close($finfo);

    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    
    if (in_array($mime_type, $allowed_mimes)) {
        $extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $secure_filename = uniqid('milele_', true) . '.' . strtolower($extension);
        
        $upload_dir = 'uploads/';
        
        // AUTO-CREATE UPLOADS FOLDER IF IT DOES NOT EXIST
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $destination = $upload_dir . $secure_filename;

        // Move the file
        if (move_uploaded_file($file_tmp_path, $destination)) {
            $final_image_path = $destination;
        } else {
            $_SESSION['error_msg'] = "Server error: Could not save image to server.";
            header("Location: Notesing.php");
            exit();
        }
    } else {
        $_SESSION['error_msg'] = "Invalid file format. Use JPG, PNG, or WEBP.";
        header("Location: Notesing.php");
        exit();
    }
}

// 5. Database Injection
try {
    $insert_sql = "INSERT INTO listings (seller_id, title, description, price, category, item_type, file_path, listing_status) 
                   VALUES (:seller_id, :title, :description, :price, :category, :item_type, :file_path, 'active')";
    
    $stmt = $pdo->prepare($insert_sql);
    $stmt->execute([
        ':seller_id'      => $seller_id,
        ':title'          => $title,
        ':description'    => $description,
        ':price'          => $price,
        ':category'       => $category,
        ':item_type'      => $item_type,
        ':file_path'      => $final_image_path
    ]);

    // Complete Success! Send the user back to the feed to view their live item.
    header("Location: index.php");
    exit();

} catch (PDOException $e) {
    $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    header("Location: Notesing.php");
    exit();
}