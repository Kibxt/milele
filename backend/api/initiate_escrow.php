<?php
// backend/api/initiate_escrow.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$host = "localhost";
$db_name = "milele_escrow";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $input = json_decode(file_get_contents("php://input"), true);
    $listing_id = $input['listing_id'] ?? null;

    if (!$listing_id) {
        echo json_encode(["status" => "error", "message" => "Error: No listing ID received from frontend."]);
        exit();
    }

    $conn->beginTransaction();

    // 1. Check the item WITHOUT the strict 'FOR UPDATE' memory lock
    $query = "SELECT * FROM listings WHERE listing_id = :listing_id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':listing_id' => $listing_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    // Diagnostic 1: Does the item exist at all?
    if (!$item) {
        $conn->rollBack();
        echo json_encode(["status" => "error", "message" => "Diagnostic: Item ID " . $listing_id . " does not exist in the database."]);
        exit();
    }

    // Diagnostic 2: Is the item actually available?
    if ($item['status'] !== 'available') {
        $conn->rollBack();
        echo json_encode(["status" => "error", "message" => "Diagnostic: Item ID " . $listing_id . " is currently marked as '" . $item['status'] . "'."]);
        exit();
    }

    // 2. Safely assign Buyer and Seller
    $seller_id = $item['seller_id'];
    $buyer_stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id != :seller_id LIMIT 1");
    $buyer_stmt->execute([':seller_id' => $seller_id]);
    $buyer = $buyer_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Diagnostic 3: Are there enough users?
    if (!$buyer) {
        $conn->rollBack();
        echo json_encode(["status" => "error", "message" => "Diagnostic: No valid buyer found in users table."]);
        exit();
    }
    
    $buyer_id = $buyer['user_id'];
    $amount = $item['price'];
    
    // GENERATE THE CRYPTOGRAPHIC 6-DIGIT HANDSHAKE
    $otp_code = rand(100000, 999999); 

    // 3. Lock the item in the marketplace
    $update_query = "UPDATE listings SET status = 'locked' WHERE listing_id = :listing_id";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->execute([':listing_id' => $listing_id]);

    // 4. Insert the Escrow Ledger Record
    $insert_query = "INSERT INTO escrow_transactions (listing_id, buyer_id, seller_id, amount, status, otp_code) 
                     VALUES (:listing_id, :buyer_id, :seller_id, :amount, 'funded', :otp_code)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->execute([
        ':listing_id' => $listing_id,
        ':buyer_id' => $buyer_id,
        ':seller_id' => $seller_id,
        ':amount' => $amount,
        ':otp_code' => $otp_code
    ]);

    $escrow_id = $conn->lastInsertId();

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Escrow locked successfully.",
        "escrow_details" => [
            "escrow_id" => $escrow_id,
            "otp_code" => $otp_code
        ]
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    // Diagnostic 4: Print the exact MySQL error
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
?>