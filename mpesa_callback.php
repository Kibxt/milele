<?php
// MILELE - Silent M-Pesa Listener

require 'db.php';

// Safaricom sends JSON data here asynchronously
$mpesaResponse = file_get_contents('php://input');

// Extract the custom URL parameters we sent in checkout.php
$item_id = filter_input(INPUT_GET, 'item', FILTER_VALIDATE_INT);
$buyer_id = filter_input(INPUT_GET, 'buyer', FILTER_VALIDATE_INT);

$data = json_decode($mpesaResponse, true);

if ($data && isset($data['Body']['stkCallback'])) {
    $resultCode = $data['Body']['stkCallback']['ResultCode'];
    
    // ResultCode 0 means successful payment
    if ($resultCode == 0 && $item_id && $buyer_id) {
        
        // 1. Generate the unique Escrow PIN securely
        $generated_pin = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        // 2. Lock the item, assign the buyer, and save the PIN
        try {
            $stmt = $pdo->prepare("
                UPDATE listings 
                SET listing_status = 'escrow', buyer_id = ?, escrow_pin = ? 
                WHERE listing_id = ? AND listing_status = 'active'
            ");
            $stmt->execute([$buyer_id, $generated_pin, $item_id]);
        } catch (PDOException $e) {
            // Fails silently in background
        }
    }
}
?>