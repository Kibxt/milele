<?php
// backend/api/verify_handshake.php
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $escrowId = trim($_POST['escrow_id']);
    $inputPin = trim($_POST['pin']);

    if (empty($escrowId) || empty($inputPin)) {
        echo json_encode(["status" => "error", "message" => "Missing required verification data."]);
        exit;
    }

    $host = 'localhost';
    $db   = 'milele_escrow';
    $user = 'root';
    $pass = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Locate the specific funded transaction
        $stmt = $pdo->prepare("SELECT * FROM escrow_transactions WHERE escrow_id = ? AND status = 'funded'");
        $stmt->execute([$escrowId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            echo json_encode(["status" => "error", "message" => "Transaction not found or funds are not actively locked."]);
            exit;
        }

        // 2. Security Check: Does the PIN match?
        if ($order['secret_pin'] !== $inputPin) {
            echo json_encode(["status" => "error", "message" => "Authentication failed. Incorrect meetup code."]);
            exit;
        }

        // 3. Lock the database row immediately to prevent double-payouts (Using escrow_id now)
        $lockStmt = $pdo->prepare("UPDATE escrow_transactions SET status = 'processing_payout' WHERE escrow_id = ?");
        $lockStmt->execute([$order['escrow_id']]);

        // 4. Wake up the M-Pesa Gateway
        require_once 'MpesaGateway.php';
        $gateway = new MpesaGateway();

        // 5. Fire the B2C command
        $sellerMpesaNumber = "254708374149"; 
        $amountToPay = $order['amount'];

        $b2cResponse = $gateway->sendPayout($sellerMpesaNumber, $amountToPay, $escrowId);

        if ($b2cResponse['status'] === 'success') {
            echo json_encode(["status" => "success", "message" => "Funds successfully released to M-Pesa."]);
        } else {
            // Revert lock if the API completely failed to connect (Using escrow_id now)
            $revertStmt = $pdo->prepare("UPDATE escrow_transactions SET status = 'funded' WHERE escrow_id = ?");
            $revertStmt->execute([$order['escrow_id']]);
            
            echo json_encode(["status" => "error", "message" => "Gateway Error: " . $b2cResponse['message']]);
        }

    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Secure Database Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid protocol method."]);
}
?>