<?php
// MILELE - Admin Crowning Script

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    die("<h1 style='color: #F87171; background: #000; padding: 50px; text-align: center;'>You must be logged in to run this script.</h1>");
}

require 'db.php';
$user_id = $_SESSION['user_id'];

try {
    // 1. Add the secret Admin column to the users table (if it doesn't exist yet)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0 AFTER role");
    } catch (PDOException $e) {
        // If it fails, the column already exists, which is fine! We just keep going.
    }

    // 2. Upgrade the currently logged-in user to Admin status
    $stmt = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE user_id = :id");
    $stmt->execute([':id' => $user_id]);

    echo "<h1 style='color: #2DD4BF; background: #000; padding: 50px; font-family: sans-serif; text-align: center; border-radius: 20px; margin: 40px;'>👑 Crown Secured. You are now the Platform Admin!</h1>";

} catch (PDOException $e) {
    echo "<h1 style='color: #F87171; background: #000; padding: 50px; text-align: center;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</h1>";
}
?>