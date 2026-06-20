<?php
// MILELE - Live Safaricom Webhook Listener (Callback Handler)

// Inform Safaricom that we are ready to receive JSON data
header("Content-Type: application/json");

require '../../db.php';

// 1. Capture the raw JSON payload arriving from Safaricom's banking servers
$callbackJSONData = file_get_contents('php://input');

if (empty($callbackJSONData)) {
    echo json_encode(["status" => "rejected", "message" => "No payload received"]);
    exit();
}

// Log data for internal cloud troubleshooting if necessary
$logFile = "mpesa_responses.log";
file_put_contents($logFile, $callbackJSONData . PHP_EOL, FILE_APPEND);

$data = json_decode($callbackJSONData, true);

// Check if the structurally standard M-Pesa response object exists
if (!isset($data['Body']['stkCallback'])) {
    echo json_encode(["status" => "rejected", "message" => "Invalid schema"]);
    exit();
}

$callback = $data['Body']['stkCallback'];
$resultCode = $callback['ResultCode']; // '0' means absolute success
$checkoutRequestID = $callback['CheckoutRequestID'];

try {
    if ($resultCode == 0) {
        // SUCCESS: Buyer typed their PIN and the money is now inside the MILELE system
        
        // Extract transaction meta-data sent back by Safaricom
        $mpesaReceiptNumber = "";
        $items = $callback['CallbackMetadata']['Item'] ?? [];
        foreach ($items as $item) {
            if ($item['Name'] === 'MpesaReceiptNumber') {
                $mpesaReceiptNumber = $item['Value'];
                break;
            }
        }

        // 1. Find the pending transaction matching this checkout ID
        $stmt_find = $pdo->prepare("SELECT transaction_id, listing_id FROM escrow_transactions WHERE mpesa_checkout_id = :id AND transaction_status = 'pending'");
        $stmt_find->execute([':id' => $checkoutRequestID]);
        $tx = $stmt_find->fetch();

        if ($tx) {
            // 2. Security Protocol: Generate a random 4-digit Vault Code (Escrow PIN)
            // The buyer must give this to the seller upon receiving the physical item/keys
            $escrow_pin = rand(1000, 9999);

            // 3. Update the transaction to 'funded' and bind the receipt & PIN
            $stmt_update_tx = $pdo->prepare("
                UPDATE escrow_transactions 
                SET transaction_status = 'funded', 
                    mpesa_receipt = :receipt, 
                    escrow_pin = :pin 
                WHERE transaction_id = :tx_id
            ");
            $stmt_update_tx->execute([
                ':receipt' => $mpesaReceiptNumber,
                ':pin' => $escrow_pin,
                ':tx_id' => $tx['transaction_id']
            ]);

            // 4. Mark the market listing as 'sold' so it instantly disappears from the public feed
            $stmt_update_listing = $pdo->prepare("UPDATE listings SET listing_status = 'sold' WHERE listing_id = :list_id");
            $stmt_update_listing->execute([':list_id' => $tx['listing_id']]);
        }

    } else {
        // FAILURE: Buyer canceled, entered wrong PIN, or had insufficient funds
        $stmt_fail = $pdo->prepare("UPDATE escrow_transactions SET transaction_status = 'failed' WHERE mpesa_checkout_id = :id AND transaction_status = 'pending'");
        $stmt_fail->execute([':id' => $checkoutRequestID]);
    }

    // Always respond with a clean HTTP 200 to let Safaricom know we processed it successfully
    echo json_encode(["status" => "success", "message" => "Callback completed cleanly"]);

} catch (PDOException $e) {
    // Log internal error safely without exposing crash architecture to public webhooks
    file_put_contents("errors.log", $e->getMessage() . PHP_EOL, FILE_APPEND);
    echo json_encode(["status" => "error", "message" => "Database exception handled"]);
}
?>