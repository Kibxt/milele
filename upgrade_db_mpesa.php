<?php
// MILELE - Database Upgrader (M-Pesa Escrow Tracking)

require 'db.php';

try {
    // Add the two missing columns Safaricom needs to track the payment
    $pdo->exec("
        ALTER TABLE escrow_transactions 
        ADD COLUMN mpesa_checkout_id VARCHAR(255) NULL AFTER transaction_status,
        ADD COLUMN mpesa_receipt VARCHAR(100) NULL AFTER mpesa_checkout_id
    ");
    echo "<h1 style='color: #2DD4BF; background: #000; padding: 50px; font-family: sans-serif; text-align: center; border-radius: 20px; margin: 40px;'>✅ M-Pesa Tracking Matrix Added to Cloud Database!</h1>";
} catch (PDOException $e) {
    // If it fails, it usually means the columns were already added!
    echo "<h1 style='color: #F87171; background: #000; padding: 50px; font-family: sans-serif; text-align: center; border-radius: 20px; margin: 40px;'>Database Status: " . htmlspecialchars($e->getMessage()) . "</h1>";
}
?>