<?php
// backend/api/upload_image.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once '../SessionGuard.php';

// Zero-Trust: Only logged-in users can upload images
SessionGuard::protect();

// 1. Block anything that isn't a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed."]);
    exit();
}

// 2. Validate file existence
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Upload failed or no file selected."]);
    exit();
}

// 3. Validate MIME type strictly (Only Images)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
finfo_close($finfo);

$allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowed_types)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid file type. Only JPEG, PNG, or WEBP allowed."]);
    exit();
}

// 4. Absolute Directory Resolution
// __DIR__ is 'api/'. dirname(__DIR__) is 'backend/'. dirname(dirname(__DIR__)) is 'MILELE/'
$base_dir = dirname(dirname(__DIR__)); 
$upload_dir = $base_dir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

// Auto-create directory with full write permissions if it is missing
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); 
}

// 5. Cryptographic renaming to prevent malicious file execution
$extension = ($mime === 'image/png') ? '.png' : (($mime === 'image/webp') ? '.webp' : '.jpg');
$new_filename = bin2hex(random_bytes(16)) . $extension;
$upload_path = $upload_dir . $new_filename;

// 6. Execute the move and return the path
if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "file_url" => "uploads/" . $new_filename 
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to save image. Check server permissions."]);
}
?>