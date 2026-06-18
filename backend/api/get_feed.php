<?php
// backend/api/get_feed.php

// 1. Strict CORS and Header Definitions
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET"); // Notice this is GET, not POST
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../SessionGuard.php';
require_once '../ListingController.php';

// 2. Zero-Trust Check: You must be logged in to see the feed. No outsiders allowed.
SessionGuard::protect();

// 3. Block anything that isn't a GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Use GET."]);
    exit();
}

// 4. Execute Engine Logic
$controller = new ListingController();
$result = $controller->getCampusFeed();

// 5. Return Standardized HTTP Status Codes
if ($result['status'] === 'success') {
    http_response_code(200); // 200 OK
} else {
    http_response_code(500); // 500 Internal Server Error
}

echo json_encode($result);
?>