<?php
// MILELE - Database Upgrader
require 'db.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN phone_number VARCHAR(20) NULL AFTER email");
    echo "<h1 style='color: #2DD4BF; background: #000; padding: 50px;'>✅ Phone Number added to Cloud Database!</h1>";
} catch (PDOException $e) {
    echo "<h1 style='color: #F87171; background: #000; padding: 50px;'>Error: " . $e->getMessage() . "</h1>";
}
?>