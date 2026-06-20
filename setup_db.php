<?php
// MILELE - Live Cloud Database Generator

require 'db.php';

try {
    // 1. Build Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        university_name VARCHAR(100),
        account_state VARCHAR(20) DEFAULT 'registered',
        completed_escrows INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Build Listings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS listings (
        listing_id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        category VARCHAR(50) NOT NULL,
        description TEXT,
        file_path VARCHAR(255),
        item_type VARCHAR(20) DEFAULT 'physical',
        listing_status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (seller_id) REFERENCES users(user_id)
    )");

    // 3. Build Escrow Transactions Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS escrow_transactions (
        transaction_id INT AUTO_INCREMENT PRIMARY KEY,
        listing_id INT NOT NULL,
        buyer_id INT NOT NULL,
        seller_id INT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        platform_fee DECIMAL(10,2) NOT NULL,
        net_payout DECIMAL(10,2) NOT NULL,
        escrow_pin VARCHAR(10),
        transaction_status VARCHAR(20) DEFAULT 'funded',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (listing_id) REFERENCES listings(listing_id),
        FOREIGN KEY (buyer_id) REFERENCES users(user_id),
        FOREIGN KEY (seller_id) REFERENCES users(user_id)
    )");

    // 4. Build Notifications Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        icon VARCHAR(10),
        link VARCHAR(255),
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )");

    echo "<div style='font-family: sans-serif; background: #0A0A0C; color: #fff; padding: 50px; text-align: center; height: 100vh;'>";
    echo "<h1 style='color: #2DD4BF;'>✅ LIVE CLOUD DATABASE GENERATED!</h1>";
    echo "<p>Your AWS JawsDB is now fully wired and structured.</p>";
    echo "<a href='index.php' style='display: inline-block; margin-top: 20px; padding: 15px 30px; background: #2DD4BF; color: #000; text-decoration: none; font-weight: bold; border-radius: 10px;'>Launch MILELE Live</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div style='font-family: sans-serif; background: #0A0A0C; color: #F87171; padding: 50px; text-align: center; height: 100vh;'>";
    echo "<h1>❌ ERROR BUILDING DATABASE</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>