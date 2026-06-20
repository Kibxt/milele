<?php
// MILELE - Safaricom B2C (Payout) Webhook Listener

header("Content-Type: application/json");
require '../../db.php';

// Capture the raw JSON payload
$callbackJSONData = file_get_contents('php://input');

if (!empty($callbackJSONData)) {
    // Log it silently so we can debug payouts in the cloud
    $logFile = "b2c_responses.log";
    file_put_contents($logFile, $callbackJSONData . PHP_EOL, FILE_APPEND);
    
    // Here we would normally extract the exact B2C Transaction Receipt 
    // and bind it to the escrow_transactions table for accounting purposes.
}

echo json_encode(["status" => "success", "message" => "B2C Callback Logged"]);
?>