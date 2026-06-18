<?php
// backend/api/seller_escrows.php

// 1. Strict Headers for JSON and CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$host = "localhost";
$db_name = "milele_escrow";
$username = "root";
$password = "";

try {
    // 2. Connect to the Database
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 3. Fetch all 'funded' escrows waiting for a handshake.
    // (Note: In production, we will filter this by the logged-in seller's ID)
    $query = "SELECT e.escrow_id, e.amount, e.status, l.title 
              FROM escrow_transactions e
              JOIN listings l ON e.listing_id = l.listing_id
              WHERE e.status = 'funded'
              ORDER BY e.escrow_id DESC";
              
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $escrows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Send clean JSON back to the Comrades Network dashboard
    echo json_encode([
        "status" => "success", 
        "data" => $escrows
    ]);

} catch (PDOException $e) {
    // 5. Catch any database crashes and send a clean error
    echo json_encode([
        "status" => "error", 
        "message" => "Secure Ledger Connection Failed: " . $e->getMessage()
    ]);
}
?>