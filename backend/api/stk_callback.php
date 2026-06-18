<?php
// backend/api/stk_callback.php
header("Content-Type: application/json");

$timestamp = date("Y-m-d H:i:s");
$safaricomPayload = file_get_contents('php://input');

// Write response payload directly to clean text files for local monitoring
$logEntry = "[$timestamp] DARAJA STK CALLBACK WEBHOOK RECORD: \n" . $safaricomPayload . "\n---------------------------------------------------\n";
file_put_contents("mpesa_stk_logs.txt", $logEntry, FILE_APPEND);

$data = json_decode($safaricomPayload, true);

if (isset($data['Body']['stkCallback'])) {
    
    $callback = $data['Body']['stkCallback'];
    $resultCode = $callback['ResultCode'];
    $checkoutRequestID = $callback['CheckoutRequestID'];

    // ResultCode 0 dictates explicit transactional verification success
    if ($resultCode == 0) {
        
        $host = 'localhost';
        $db   = 'milele_escrow'; 
        $user = 'root';      
        $pass = '';          

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Generate a random 6-digit secure PIN
            $secretPin = sprintf("%06d", mt_rand(100000, 999999));

            // Shift status to 'funded' AND save the secret PIN safely
            $stmt = $pdo->prepare("UPDATE escrow_transactions SET status = 'funded', secret_pin = ? WHERE checkout_id = ?");
            $stmt->execute([$secretPin, $checkoutRequestID]);

        } catch (PDOException $e) {
            $dbError = "[$timestamp] WEBHOOK TRANSACTION SQL DATABASE ERROR: " . $e->getMessage() . "\n---------------------------------------------------\n";
            file_put_contents("mpesa_stk_logs.txt", $dbError, FILE_APPEND);
        }
    }
}

// Respond back to Safaricom API clusters to safely terminate thread lifecycle
echo json_encode([
    "ResultCode" => 0,
    "ResultDesc" => "STK Callback acknowledged successfully"
]);
?>