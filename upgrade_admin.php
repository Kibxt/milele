<?php
// MILELE - Multi-Account Admin Crowning Script

require 'db.php';

// The executive triad of admin accounts
$admin_emails = [
    'kibeta425@gmail.com',
    'yegonkibe4@gmail.com',
    'alvin.kibet@strathmore.edu'
];

try {
    // 1. Force-add the column to the very end of the table
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
    } catch (PDOException $e) {
        // If the column already exists, silently keep moving
    }

    // 2. Prepare the dynamic SQL query to accept multiple emails
    $inQuery = implode(',', array_fill(0, count($admin_emails), '?'));
    $stmt = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE email IN ($inQuery)");

    // 3. Execute the upgrade
    $stmt->execute($admin_emails);

    // 4. Verify it worked
    $updatedCount = $stmt->rowCount();
    
    if ($updatedCount > 0) {
        echo "<h1 style='color: #2DD4BF; background: #000; padding: 50px; font-family: sans-serif; text-align: center; border-radius: 20px; margin: 40px;'>👑 Crown Secured. Admin privileges granted to $updatedCount account(s)!</h1>";
        
        echo "<div style='color: #888; text-align: center; font-family: sans-serif;'>Authorized Emails:<br>";
        foreach ($admin_emails as $email) {
            echo "<strong style='color: #fff; line-height: 2;'>" . htmlspecialchars($email) . "</strong><br>";
        }
        echo "</div>";

    } else {
        echo "<h1 style='color: #FBBF24; background: #000; padding: 50px; font-family: sans-serif; text-align: center; border-radius: 20px; margin: 40px;'>⚠️ No accounts found matching those emails. Make sure you have signed up on the platform with them first!</h1>";
    }

} catch (PDOException $e) {
    echo "<h1 style='color: #F87171; background: #000; padding: 50px; text-align: center;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</h1>";
}
?>