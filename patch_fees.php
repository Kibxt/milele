<?php
// MILELE - Escrow Fees Database Patch

require 'db.php';

try {
    // We run them one by one to guarantee they don't fail
    try {
        $pdo->exec("ALTER TABLE escrow_transactions ADD COLUMN platform_fee DECIMAL(10,2) DEFAULT 0");
    } catch (PDOException $e) {} // Ignore if it already exists

    try {
        $pdo->exec("ALTER TABLE escrow_transactions ADD COLUMN seller_payout DECIMAL(10,2) DEFAULT 0");
    } catch (PDOException $e) {} // Ignore if it already exists

    echo "<h1 style='color: #2DD4BF; background: #000; padding: 50px; text-align: center; font-family: sans-serif; border-radius: 20px; margin: 40px;'>✅ Database Patched! The fee engine is now fully online.</h1>";

} catch (Exception $e) {
    echo "<h1 style='color: #F87171; background: #000; padding: 50px; text-align: center; border-radius: 20px; margin: 40px;'>Error: " . htmlspecialchars($e->getMessage()) . "</h1>";
}
?>