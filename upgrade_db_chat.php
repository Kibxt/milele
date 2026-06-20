<?php
// MILELE - Database Upgrader (Messaging System)
require 'db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        message_id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        listing_id INT NOT NULL,
        message_text TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (listing_id) REFERENCES listings(listing_id) ON DELETE CASCADE
    )");
    echo "<h1 style='color: #2DD4BF; background: #000; padding: 50px;'>✅ Secure Messaging Table Created in Cloud!</h1>";
} catch (PDOException $e) {
    echo "<h1 style='color: #F87171; background: #000; padding: 50px;'>Error: " . $e->getMessage() . "</h1>";
}
?>