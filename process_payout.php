<?php
// MILELE - Secure PIN Verification & Live B2C Payout Engine (Bulletproof V2)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

require 'db.php';
require 'backend/api/MpesaGateway.php'; 

$seller_id = $_SESSION['user_id'];

// Bypassing filter_input to directly and forcefully grab the raw POST data
$transaction_id = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
$submitted_pin = isset($_POST['escrow_pin']) ? trim(htmlspecialchars($_POST['escrow_pin'])) : '';

// Diagnostic trap: Tell us exactly what is missing if it fails
if ($transaction_id === 0 || empty($submitted_pin)) {
    $_SESSION['payout_error'] = "Data Error: Missing Transaction ID (" . $transaction_id . ") or PIN.";
    header("Location: payout.php");
    exit();
}

try {
    // 1. Fetch Transaction & Seller details
    $stmt = $pdo->prepare("
        SELECT t.transaction_id, t.escrow_pin, t.transaction_status, t.total_amount, u.phone_number 
        FROM escrow_transactions t
        JOIN users u ON t.seller_id = u.user_id
        WHERE t.transaction_id = :tx_id AND t.seller_id = :seller
    ");
    $stmt->execute([':tx_id' => $transaction_id, ':seller' => $seller_id]);
    $tx = $stmt->fetch();

    if (!$tx) {
        $_SESSION['payout_error'] = "Transaction not found or unauthorized.";
        header("Location: payout.php");
        exit();
    }

    if ($tx['transaction_status'] !== 'funded') {
        $_SESSION['payout_error'] = "These funds have already been claimed or are unavailable.";
        header("Location: payout.php");
        exit();
    }

    // 2. The Final Verification Gate
    if ($submitted_pin === $tx['escrow_pin']) {
        
        // 3. Initiate the live M-Pesa B2C Payout!
        $mpesa = new MpesaGateway();
        
        $seller_phone = !empty($tx['phone_number']) ? $tx['phone_number'] : '0708374149'; 
        
        $response = $mpesa->b2cPayment($seller_phone, $tx['total_amount'], $transaction_id);

        if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
            // Success! Release the funds.
            $update = $pdo->prepare("UPDATE escrow_transactions SET transaction_status = 'released' WHERE transaction_id = :tx_id");
            $update->execute([':tx_id' => $transaction_id]);

            $rep_update = $pdo->prepare("UPDATE users SET completed_escrows = completed_escrows + 1 WHERE user_id = :seller");
            $rep_update->execute([':seller' => $seller_id]);

            $_SESSION['payout_success'] = "PIN Verified! Safaricom is processing your payout directly to " . htmlspecialchars($seller_phone) . ".";
        } else {
            // Safaricom API Error
            $error_msg = $response['errorMessage'] ?? "Connection to Safaricom B2C failed.";
            $_SESSION['payout_error'] = "Bank Error: " . htmlspecialchars($error_msg);
        }
        
    } else {
        $_SESSION['payout_error'] = "Incorrect Handover PIN. Please ask the buyer for the correct code.";
    }

} catch (PDOException $e) {
    $_SESSION['payout_error'] = "System error processing payout.";
}

header("Location: payout.php");
exit();
?>