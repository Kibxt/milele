<?php
// MILELE - Chat Database Patch

require 'db.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            message_id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            receiver_id INT NOT NULL,
            listing_id INT DEFAULT NULL,
            message_text TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (sender_id),
            INDEX (receiver_id)
        )
    ");
    
    echo "<h1 style='color: #2DD4BF; background: #000; padding: 50px; text-align: center; font-family: sans-serif; border-radius: 20px; margin: 40px;'>💬 Database Patched! The messaging engine is now online.</h1>";
} catch (PDOException $e) {
    echo "<h1 style='color: #F87171; background: #000; padding: 50px; text-align: center; border-radius: 20px; margin: 40px;'>Error: " . htmlspecialchars($e->getMessage()) . "</h1>";
}
?>