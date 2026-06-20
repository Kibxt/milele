<?php
// MILELE - Smart Checkout Engine (With Double-Failsafe Notifications)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];
$buyer_name = explode(' ', $_SESSION['full_name'] ?? 'Buyer')[0];
$listing_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
$phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_SPECIAL_CHARS);

if (!$listing_id || empty($phone_number)) {
    $_SESSION['error_msg'] = "Invalid payment details.";
    header("Location: checkout.php?id=" . $listing_id);
    exit();
}

// ⚡ THE MASTER CONNECTION
require 'db.php';

try {
    $pdo->beginTransaction();

    // 1. Fetch Item Details
    $stmt = $pdo->prepare("SELECT price, seller_id, listing_status, item_type, title FROM listings WHERE listing_id = :id FOR UPDATE");
    $stmt->execute([':id' => $listing_id]);
    $item = $stmt->fetch();

    if (!$item || $item['listing_status'] !== 'active') {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Sorry, this item is no longer available.";
        header("Location: index.php");
        exit();
    }

    if ($item['seller_id'] == $buyer_id) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "You cannot buy your own item.";
        header("Location: index.php");
        exit();
    }

    // 2. Calculate Financials
    $price = (float)$item['price'];
    $platform_fee = $price * 0.03; 
    $net_payout = $price - $platform_fee; 
    $total_amount_paid_by_buyer = $price + $platform_fee; 

    // 3. The Y-Junction: Digital vs Physical
    if ($item['item_type'] === 'digital') {
        
        // BRANCH B: DIGITAL ITEM (Instant Release)
        $insert_sql = "INSERT INTO escrow_transactions 
                       (listing_id, buyer_id, seller_id, total_amount, platform_fee, net_payout, transaction_status) 
                       VALUES (:listing, :buyer, :seller, :total, :fee, :net, 'released')";
        $tx_stmt = $pdo->prepare($insert_sql);
        $tx_stmt->execute([
            ':listing' => $listing_id,
            ':buyer'   => $buyer_id,
            ':seller'  => $item['seller_id'],
            ':total'   => $total_amount_paid_by_buyer,
            ':fee'     => $platform_fee,
            ':net'     => $price
        ]);
        
        $transaction_id = $pdo->lastInsertId();

        $update_user = "UPDATE users SET completed_escrows = completed_escrows + 1 WHERE user_id = :seller";
        $pdo->prepare($update_user)->execute([':seller' => $item['seller_id']]);

        $notif_sql = "INSERT INTO notifications (user_id, title, message, icon, link) VALUES (:uid, :title, :msg, :icon, :link)";
        $pdo->prepare($notif_sql)->execute([
            ':uid' => $item['seller_id'],
            ':title' => "Digital Item Sold! ⚡",
            ':msg' => "{$buyer_name} just bought your '{$item['title']}'. KES " . number_format($price) . " has been credited to your deals.",
            ':icon' => "💸",
            ':link' => "profile.php"
        ]);

        $pdo->commit();
        header("Location: download.php?tx=" . $transaction_id);
        exit();

    } else {
        
        // BRANCH A: PHYSICAL ITEM (Escrow Vault)
        $escrow_pin = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)); 

        $insert_sql = "INSERT INTO escrow_transactions 
                       (listing_id, buyer_id, seller_id, total_amount, platform_fee, net_payout, escrow_pin, transaction_status) 
                       VALUES (:listing, :buyer, :seller, :total, :fee, :net, :pin, 'funded')";
        $tx_stmt = $pdo->prepare($insert_sql);
        $tx_stmt->execute([
            ':listing' => $listing_id,
            ':buyer'   => $buyer_id,
            ':seller'  => $item['seller_id'],
            ':total'   => $total_amount_paid_by_buyer,
            ':fee'     => $platform_fee,
            ':net'     => $price,
            ':pin'     => $escrow_pin
        ]);

        $transaction_id = $pdo->lastInsertId();

        $update_sql = "UPDATE listings SET listing_status = 'hidden' WHERE listing_id = :id";
        $pdo->prepare($update_sql)->execute([':id' => $listing_id]);

        $notif_sql = "INSERT INTO notifications (user_id, title, message, icon, link) VALUES (:uid, :title, :msg, :icon, :link)";
        $pdo->prepare($notif_sql)->execute([
            ':uid' => $item['seller_id'],
            ':title' => "New Escrow Deal! 🤝",
            ':msg' => "{$buyer_name} just locked funds for your '{$item['title']}'. Check your pending payouts to view the deal.",
            ':icon' => "🔒",
            ':link' => "payout.php"
        ]);

        $pdo->prepare($notif_sql)->execute([
            ':uid' => $buyer_id,
            ':title' => "Escrow PIN Secured 🔐",
            ':msg' => "Your funds are safely locked. Click here to view your 6-digit release PIN for '{$item['title']}'.",
            ':icon' => "🔑",
            ':link' => "vault.php?tx=" . $transaction_id
        ]);

        $pdo->commit();
        $_SESSION['latest_pin'] = $escrow_pin;
        header("Location: vault.php?tx=" . $transaction_id);
        exit();
    }

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_msg'] = "Transaction failed. Please try again.";
    header("Location: checkout.php?id=" . $listing_id);
    exit();
}
?>