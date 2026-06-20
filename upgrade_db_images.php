<?php
// MILELE - Database Upgrader (Image Storage)
require 'db.php';
try {
    // Change the file_path column to LONGTEXT to hold Base64 image data
    $pdo->exec("ALTER TABLE listings MODIFY file_path LONGTEXT");
    echo "<h1 style='color: #2DD4BF; background: #000; padding: 50px;'>✅ Database Upgraded for Permanent Cloud Images!</h1>";
} catch (PDOException $e) {
    echo "<h1 style='color: #F87171; background: #000; padding: 50px;'>Error: " . $e->getMessage() . "</h1>";
}
?>