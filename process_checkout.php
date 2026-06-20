<?php
// MILELE - Premium Checkout Processor & STK Push Initiator

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';
require 'backend/api/MpesaGateway.php';

$user_id = $_SESSION['user_id'];

// Catch data from checkout form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $listing_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
    $phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_SPECIAL_CHARS);

    if (!$listing_id || empty($phone_number)) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:sans-serif;'>Invalid request data. Please try again.</div>");
    }

    try {
        // 1. Fetch item details to verify availability and price
        $stmt = $pdo->prepare("SELECT title, price, seller_id FROM listings WHERE listing_id = :id AND listing_status = 'active'");
        $stmt->execute([':id' => $listing_id]);
        $item = $stmt->fetch();

        if (!$item) {
            die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:sans-serif;'>This item is no longer available.</div>");
        }

        // Prevent self-purchases
        if ($item['seller_id'] == $user_id) {
            die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:sans-serif;'>You cannot buy your own item.</div>");
        }

        // 2. Initialize Safaricom Daraja Gateway
        $mpesa = new MpesaGateway();
        
        // Generate reference tags for Safaricom statement tracking
        $reference = "MIL" . $listing_id;
        $description = "Escrow Pay";

        // Trigger STK Push
        $response = $mpesa->stkPush($phone_number, $item['price'], $reference, $description);

        // 3. Evaluate Safaricom Response
        if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
            // Success: STK Prompt sent to user's device
            $checkout_id = $response['CheckoutRequestID'];

            // Save transaction as 'pending' with the Checkout Request ID linked
            // This allows stk_callback.php to match the transaction when Safaricom replies
            $stmt_tx = $pdo->prepare("
                INSERT INTO escrow_transactions (buyer_id, seller_id, listing_id, total_amount, transaction_status, mpesa_checkout_id, created_at) 
                VALUES (:buyer, :seller, :listing, :amount, 'pending', :checkout, NOW())
            ");
            $stmt_tx->execute([
                ':buyer' => $user_id,
                ':seller' => $item['seller_id'],
                ':listing' => $listing_id,
                ':amount' => $item['price'],
                ':checkout' => $checkout_id
            ]);

        } else {
            // Daraja structural failure
            $error_msg = $response['ResponseDescription'] ?? "Connection to Safaricom API failed.";
            die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:sans-serif;'>M-Pesa Gateway Error: " . htmlspecialchars($error_msg) . "</div>");
        }

    } catch (PDOException $e) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:sans-serif;'>System processing error.</div>");
    }
} else {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Payment | MILELE</title>
    <style>
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .payment-box { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); padding: 40px; border-radius: 32px; max-width: 450px; width: 100%; text-align: center; box-shadow: 0 24px 48px rgba(0,0,0,0.4); }
        
        .pulse-icon { font-size: 3.5rem; margin-bottom: 20px; display: inline-block; animation: pulse 2s infinite ease-in-out; }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.6; }
            50% { transform: scale(1.1); opacity: 1; filter: drop-shadow(0 0 15px rgba(45,212,191,0.6)); }
            100% { transform: scale(1); opacity: 0.6; }
        }

        h2 { margin: 0 0 10px 0; font-size: 1.6rem; color: #fff; }
        p { color: #888; font-size: 0.95rem; line-height: 1.6; margin: 0 0 30px 0; }
        .highlight { color: #2DD4BF; font-weight: bold; }

        .loader-bar { width: 100%; height: 4px; background: rgba(255,255,255,0.05); border-radius: 2px; overflow: hidden; margin-bottom: 30px; }
        .loader-progress { width: 40%; height: 100%; background: #2DD4BF; border-radius: 2px; animation: slide 1.5s infinite linear; }
        @keyframes slide {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(250%); }
        }

        .btn-profile { display: block; padding: 14px; background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; text-decoration: none; font-weight: bold; font-size: 0.95rem; transition: 0.3s; }
        .btn-profile:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); }
    </style>
</head>
<body>

<div class="payment-box">
    <div class="pulse-icon">📱</div>
    <h2>Check Your Phone</h2>
    <p>We have sent an M-Pesa STK push prompt to <span class="highlight"><?php echo htmlspecialchars($phone_number); ?></span>.<br><br>Please enter your <span class="highlight">M-Pesa PIN</span> on your device to authorize the secure escrow deposit of <span class="highlight">KES <?php echo number_format($item['price'], 2); ?></span>.</p>
    
    <div class="loader-bar">
        <div class="loader-progress"></div>
    </div>

    <a href="profile.php" class="btn-profile">Go to Dashboard</a>
</div>

</body>
</html>