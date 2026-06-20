<?php
// MILELE - Database Upgrader (Saved Items Table)
require 'db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS saved_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        listing_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, listing_id),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (listing_id) REFERENCES listings(listing_id) ON DELETE CASCADE
    )");
    echo "<h1 style='color: #2DD4BF; background: #000; padding: 50px;'>✅ Saved Items Table Created in Cloud!</h1>";
} catch (PDOException $e) {
    echo "<h1 style='color: #F87171; background: #000; padding: 50px;'>Error: " . $e->getMessage() . "</h1>";
}
?>