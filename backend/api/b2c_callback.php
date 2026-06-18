<?php
// backend/api/b2c_callback.php

// Tell Safaricom we are speaking JSON
header("Content-Type: application/json");

// 1. Capture the exact timestamp for your logs
$timestamp = date("Y-m-d H:i:s");

// 2. Read the raw POST data coming through the Ngrok tunnel
$safaricomPayload = file_get_contents('php://input');

// 3. Write it directly to your text file so you have a physical record
$logEntry = "[$timestamp] DARAJA B2C CALLBACK: \n" . $safaricomPayload . "\n---------------------------------------------------\n";
file_put_contents("mpesa_b2c_logs.txt", $logEntry, FILE_APPEND);

// 4. Decode the JSON so PHP can read the variables
$data = json_decode($safaricomPayload, true);

// 5. Verify the payload has actual result data before trying to update the database
if (isset($data['Result'])) {
    
    $resultCode = $data['Result']['ResultCode'];
    $resultDesc = $data['Result']['ResultDesc'];
    $transactionId = $data['Result']['TransactionID'];
    $conversationId = $data['Result']['ConversationID'];

    // 6. Check for the Golden Success Code
    if ($resultCode == 0) {
        
        // =========================================================
        // SUCCESS: CONNECT TO MYSQL AND UPDATE ESCROW STATUS
        // =========================================================
        $host = 'localhost';
        $db   = 'milele_escrow'; // Locked in to your true database name
        $user = 'root';      
        $pass = '';          

        try {
            // Securely connect to the database
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // =========================================================
            // QUICK FIX: Updating status without the mpesa_receipt column
            // =========================================================
            $stmt = $pdo->prepare("UPDATE escrow_transactions SET status = 'completed' WHERE status = 'pending'");
            $stmt->execute();

            /* 
             * NOTE: If you ever add the 'mpesa_receipt' column to your database 
             * in the future, delete the two lines above and uncomment the two lines below!
             * 
             * $stmt = $pdo->prepare("UPDATE escrow_transactions SET status = 'completed', mpesa_receipt = ? WHERE status = 'pending'");
             * $stmt->execute([$transactionId]);
             */

        } catch (PDOException $e) {
            // If the database connection fails, log the exact error
            $dbError = "[$timestamp] DB ERROR: " . $e->getMessage() . "\n---------------------------------------------------\n";
            file_put_contents("mpesa_b2c_logs.txt", $dbError, FILE_APPEND);
        }
        
    }
}

// 7. Send the required acknowledgment back to Safaricom/Bypass
echo json_encode([
    "ResultCode" => 0,
    "ResultDesc" => "Callback received successfully"
]);
?>