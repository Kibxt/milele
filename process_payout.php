<?php
// MILELE - Secure PIN Verification & Payout Engine

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

require 'db.php';

$seller_id = $_SESSION['user_id'];
$transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
$submitted_pin = filter_input(INPUT_POST, 'escrow_pin', FILTER_SANITIZE_SPECIAL_CHARS);

if (!$transaction_id || empty($submitted_pin)) {
    $_SESSION['payout_error'] = "Invalid data submitted.";
    header("Location: payout.php");
    exit();
}

try {
    // 1. Fetch the exact transaction to verify ownership and check the PIN
    $stmt = $pdo->prepare("SELECT transaction_id, escrow_pin, transaction_status FROM escrow_transactions WHERE transaction_id = :tx_id AND seller_id = :seller");
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

    // 2. The Verification Gate
    if ($submitted_pin === $tx['escrow_pin']) {
        
        // PIN Matches! Release the funds.
        $update = $pdo->prepare("UPDATE escrow_transactions SET transaction_status = 'released' WHERE transaction_id = :tx_id");
        $update->execute([':tx_id' => $transaction_id]);

        // Add +1 to the Seller's "Successful Deals" counter to build their reputation
        $rep_update = $pdo->prepare("UPDATE users SET completed_escrows = completed_escrows + 1 WHERE user_id = :seller");
        $rep_update->execute([':seller' => $seller_id]);

        // [FUTURE PHASE: This is where we will trigger the M-Pesa B2C API to actually wire the money from the cloud to the seller's phone]
        
        $_SESSION['payout_success'] = "PIN Verified! Funds have been cleared to your account.";
        
    } else {
        // PIN does not match
        $_SESSION['payout_error'] = "Incorrect Vault PIN. Please ask the buyer for the correct code.";
    }

} catch (PDOException $e) {
    $_SESSION['payout_error'] = "System error verifying PIN.";
}

// Send them back to the payout dashboard to see the result
header("Location: payout.php");
exit();
?>