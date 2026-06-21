<?php
// MILELE - Feature Upgrade Script (Categories & Favorites)
require 'db.php';

try {
    // 1. Add Category Column (Silently fails if it already exists)
    try {
        $pdo->exec("ALTER TABLE listings ADD COLUMN category VARCHAR(100) DEFAULT 'General'");
    } catch (PDOException $e) {}

    // 2. Create the Favorites Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            listing_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY user_listing (user_id, listing_id)
        )
    ");

    echo "<h1 style='color: #2DD4BF; background: #000; padding: 50px; text-align: center; font-family: sans-serif;'>✅ Database Upgraded! Categories and Favorites are now online.</h1>";
} catch (PDOException $e) {
    echo "<h1 style='color: #F87171; background: #000; padding: 50px; text-align: center;'>Error: " . $e->getMessage() . "</h1>";
}
?>