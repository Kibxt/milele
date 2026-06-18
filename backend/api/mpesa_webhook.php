<?php
// backend/api/mpesa_webhook.php

header("Content-Type: application/json");

// 1. Capture Safaricom's raw JSON input stream
$raw_payload = file_get_contents('php://input');

// 2. Log the raw payload instantly to a text file for local debugging
file_put_contents('mpesa_response_log.txt', $raw_payload . PHP_EOL, FILE_APPEND);

$data = json_decode($raw_payload, true);

if (!$data || !isset($data['Body']['stkCallback'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid payload structure."]);
    exit();
}

$callback = $data['Body']['stkCallback'];
$resultCode = $callback['ResultCode'];
$resultDesc = $callback['ResultDesc'];
$checkoutRequestID = $callback['CheckoutRequestID'];

// 3. Establish Database Connection
$host = "localhost";
$db_name = "milele_escrow";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Webhook DB Error: " . $e->getMessage());
    exit();
}

// 4. Process the Transaction Result State
if ($resultCode === 0) {
    // Payment Success! Extract metadata parameters
    $metaItems = $callback['CallbackMetadata']['Item'];
    $mpesaReceiptNumber = '';
    $amountPaid = 0;
    
    foreach ($metaItems as $item) {
        if ($item['Name'] === 'MpesaReceiptNumber') {
            $mpesaReceiptNumber = $item['Value'];
        }
        if ($item['Name'] === 'Amount') {
            $amountPaid = $item['Value'];
        }
    }
    
    // Update the escrow system status to 'funded'
    $query = "UPDATE escrow_transactions 
              SET status = 'funded' 
              WHERE escrow_id = (SELECT escrow_id FROM (SELECT escrow_id FROM escrow_transactions WHERE status = 'locked' LIMIT 1) as tmp)";
              
    // Note: In production, you map this using the unique CheckoutRequestID.
    // For this sandbox test, we update our active locked transaction.
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    error_log("Transaction successfully funded via M-Pesa. Receipt: " . $mpesaReceiptNumber);
} else {
    // Payment Failed or Cancelled by User
    $query = "UPDATE escrow_transactions 
              SET status = 'failed' 
              WHERE status = 'locked' LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    error_log("M-Pesa Payment failed or cancelled. Code: " . $resultCode . " - " . $resultDesc);
}

// 5. Safaricom requires a formal acknowledgement response
http_response_code(200);
echo json_encode(["ResponseCode" => "0", "ResponseDescription" => "Callback received successfully."]);
?>