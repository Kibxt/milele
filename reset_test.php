<?php
// MILELE - Developer Environment Reset

require 'db.php';

try {
    // 1. Wipe all old, corrupted, or unfinished escrow transactions
    $pdo->exec("DELETE FROM escrow_transactions");
    
    // 2. Put any "sold" items back into the market so you can buy them again
    $pdo->exec("UPDATE listings SET listing_status = 'active' WHERE listing_status = 'sold'");
    
    echo "<h1 style='color: #2DD4BF; background: #000; padding: 50px; font-family: sans-serif; text-align: center; border-radius: 20px; margin: 40px;'>✅ Test Ledger Wiped Clean. Market Reset!</h1>";
} catch (PDOException $e) {
    echo "<h1 style='color: #F87171; background: #000; padding: 50px; font-family: sans-serif; text-align: center; border-radius: 20px; margin: 40px;'>Database Status: " . htmlspecialchars($e->getMessage()) . "</h1>";
}
?>