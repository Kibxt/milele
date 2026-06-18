<?php
// backend/api/process_stk.php
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['phone_number']) || empty($_POST['amount']) || empty($_POST['escrow_id'])) {
        echo json_encode(["status" => "error", "customerMessage" => "Missing processing arguments."]);
        exit;
    }

    require_once 'MpesaGateway.php';

    $phoneNumber = trim($_POST['phone_number']);
    $amount      = trim($_POST['amount']);
    $escrowId    = trim($_POST['escrow_id']); 

    // 1. Fire the Safaricom STK Request
    $gateway = new MpesaGateway();
    $mpesaResponse = $gateway->triggerStkPush($phoneNumber, $amount, $escrowId);

    if ($mpesaResponse['status'] === 'success' && isset($mpesaResponse['raw']['CheckoutRequestID'])) {
        
        $checkoutRequestId = $mpesaResponse['raw']['CheckoutRequestID'];
        $responseCode      = $mpesaResponse['raw']['ResponseCode'];
        $customerMessage   = $mpesaResponse['raw']['CustomerMessage'];

        // 2. Open local ledger and map tracking keys
        $host = 'localhost';
        $db   = 'milele_escrow';
        $user = 'root';
        $pass = '';

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Log entry with state 'pending', bound completely to the checkout signature
            $stmt = $pdo->prepare("INSERT INTO escrow_transactions (escrow_id, amount, status, checkout_id) VALUES (?, ?, 'pending', ?)");
            $stmt->execute([$escrowId, $amount, $checkoutRequestId]);

            echo json_encode([
                "status" => "success",
                "ResponseCode" => $responseCode,
                "customerMessage" => $customerMessage
            ]);

        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "customerMessage" => "Local Database Entry Error: " . $e->getMessage()]);
        }

    } else {
        $errDesc = $mpesaResponse['raw']['errorMessage'] ?? $mpesaResponse['message'] ?? 'Connection to Daraja Gateway failed.';
        echo json_encode([
            "status" => "error",
            "customerMessage" => $errDesc
        ]);
    }
} else {
    echo json_encode(["status" => "error", "customerMessage" => "Invalid request method."]);
}
?>