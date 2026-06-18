<?php
// backend/api/create_listing.php

// 1. Strict CORS and Header Definitions
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../SessionGuard.php';
require_once '../ListingController.php';

// 2. Zero-Trust Check: Immediately kill script if not logged in
SessionGuard::protect();

// 3. Block anything that isn't a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Use POST."]);
    exit();
}

// 4. Parse incoming JSON payload
$data = json_decode(file_get_contents("php://input"), true);

$title       = $data['title'] ?? ($_POST['title'] ?? null);
$description = $data['description'] ?? ($_POST['description'] ?? null);
$price       = $data['price'] ?? ($_POST['price'] ?? null);
$hostel_zone = $data['hostel_zone'] ?? ($_POST['hostel_zone'] ?? null);
$image_paths = $data['image_paths'] ?? ($_POST['image_paths'] ?? '[]'); // Default to empty JSON array

// 5. Input Validation
if (empty($title) || empty($description) || empty($price) || empty($hostel_zone)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required listing fields."]);
    exit();
}

// Ensure price is a valid number to prevent database corruption
if (!is_numeric($price) || $price <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Price must be a valid positive number."]);
    exit();
}

// 6. Execute Engine Logic
$controller = new ListingController();
$result = $controller->createListing($title, $description, $price, $hostel_zone, $image_paths);

// 7. Return Standardized HTTP Status Codes
if ($result['status'] === 'success') {
    http_response_code(201); // 201 Created
} else {
    http_response_code(500); // 500 Internal Server Error
}

echo json_encode($result);
?>