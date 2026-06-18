<?php
// MILELE - The Escrow Payout Engine

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header("Location: payout.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$seller_name = explode(' ', $_SESSION['full_name'] ?? 'Seller')[0];

$transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
$submitted_pin = strtoupper(trim(filter_input(INPUT_POST, 'escrow_pin', FILTER_SANITIZE_SPECIAL_CHARS)));

if (!$transaction_id || empty($submitted_pin)) {
    $_SESSION['error_msg'] = "Please enter the 6-digit PIN.";
    header("Location: payout.php");
    exit();
}

// Database Connection
$db_host = 'localhost'; $db_name = 'milele_escrow'; $db_user = 'root'; $db_pass = ''; 
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("System Error.");
}

try {
    $pdo->beginTransaction();

    // 1. Lock the transaction row to prevent double-spending
    $sql = "SELECT et.*, l.title, u.full_name as buyer_name 
            FROM escrow_transactions et 
            JOIN listings l ON et.listing_id = l.listing_id 
            JOIN users u ON et.buyer_id = u.user_id 
            WHERE et.transaction_id = :tx AND et.seller_id = :seller AND et.transaction_status = 'funded' 
            FOR UPDATE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':tx' => $transaction_id, ':seller' => $seller_id]);
    $deal = $stmt->fetch();

    if (!$deal) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Invalid or expired transaction.";
        header("Location: payout.php");
        exit();
    }

    // 2. The Ultimate Test: Does the PIN match?
    if ($submitted_pin !== $deal['escrow_pin']) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Incorrect PIN. The vault remains locked.";
        header("Location: payout.php");
        exit();
    }

    // ==========================================
    // THE PIN IS CORRECT. RELEASE THE FUNDS.
    // ==========================================

    // 3. Mark Escrow as Completed
    $update_tx = "UPDATE escrow_transactions SET transaction_status = 'completed' WHERE transaction_id = :tx";
    $pdo->prepare($update_tx)->execute([':tx' => $transaction_id]);

    // 4. Soft-delete the listing so it never appears in the feed again
    $delete_listing = "UPDATE listings SET listing_status = 'deleted' WHERE listing_id = :lid";
    $pdo->prepare($delete_listing)->execute([':lid' => $deal['listing_id']]);

    // 5. Increase the Seller's Trust Score (Completed Deals)
    $trust_score = "UPDATE users SET completed_escrows = completed_escrows + 1 WHERE user_id = :seller";
    $pdo->prepare($trust_score)->execute([':seller' => $seller_id]);

    // 6. 🔔 NOTIFY THE SELLER (Success)
    $notif_seller = "INSERT INTO notifications (user_id, title, message, icon, link) VALUES (:uid, :title, :msg, :icon, :link)";
    $pdo->prepare($notif_seller)->execute([
        ':uid' => $seller_id,
        ':title' => "Payout Successful! 💰",
        ':msg' => "PIN verified. KES " . number_format($deal['net_payout']) . " from {$deal['buyer_name']} has been officially released to your account.",
        ':icon' => "✅",
        ':link' => "profile.php"
    ]);

    // 7. 🔔 NOTIFY THE BUYER (Receipt)
    $notif_buyer = "INSERT INTO notifications (user_id, title, message, icon, link) VALUES (:uid, :title, :msg, :icon, :link)";
    $pdo->prepare($notif_buyer)->execute([
        ':uid' => $deal['buyer_id'],
        ':title' => "Item Received 🤝",
        ':msg' => "You successfully released the funds for '{$deal['title']}' to {$seller_name}. Thank you for using MILELE.",
        ':icon' => "🛍️",
        ':link' => "index.php"
    ]);

    $pdo->commit();

    $_SESSION['success_msg'] = "KES " . number_format($deal['net_payout']) . " has been cleared to your M-Pesa.";
    header("Location: payout.php");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_msg'] = "A system error occurred. Please try again.";
    header("Location: payout.php");
    exit();
}