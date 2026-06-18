<?php
// backend/api/marketplace.php

// 1. Allow the frontend to securely talk to this API (CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 2. Database Credentials
$host = "localhost";
$db_name = "milele_escrow";
$username = "root";
$password = "";

try {
    // 3. Connect to the Database
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 4. Fetch only the items that are legally available to be locked
    // We order by listing_id DESC so the newest items appear at the top of the feed
    $query = "SELECT listing_id, seller_id, title, description, price, status 
              FROM listings 
              WHERE status = 'available' 
              ORDER BY listing_id DESC";
              
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    // 5. Package the data into an array
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Send the successful JSON payload back to the JavaScript frontend
    echo json_encode([
        "status" => "success",
        "count" => count($items),
        "data" => $items
    ]);

} catch (PDOException $e) {
    // If the database connection fails, tell the frontend exactly why
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $e->getMessage()
    ]);
}
?>