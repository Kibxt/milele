<?php
// MILELE - Database Patch (Adding Image Path Column)

require 'db.php';

try {
    // Add the missing column to the listings table
    $pdo->exec("ALTER TABLE listings ADD COLUMN image_path VARCHAR(500) DEFAULT NULL");
    
    echo "<h1 style='color: #2DD4BF; background: #000; padding: 50px; text-align: center; font-family: sans-serif; border-radius: 20px; margin: 40px;'>✅ Database Patched! The 'image_path' column is now live.</h1>";
} catch (PDOException $e) {
    // If you run it twice or it fails, it will let you know safely
    echo "<h1 style='color: #FBBF24; background: #000; padding: 50px; text-align: center; font-family: sans-serif; border-radius: 20px; margin: 40px;'>Status: " . htmlspecialchars($e->getMessage()) . "</h1>";
}
?>