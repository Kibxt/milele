<?php
// MILELE - Escrow Success & Instruction Guide

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || !isset($_GET['checkout'])) {
    header("Location: index.php");
    exit();
}

require 'db.php';

$checkout_id = filter_input(INPUT_GET, 'checkout', FILTER_SANITIZE_SPECIAL_CHARS);
$user_id = $_SESSION['user_id'];

try {
    // Fetch the specific transaction and item details
    $stmt = $pdo->prepare("
        SELECT t.*, l.title, u.full_name as seller_name 
        FROM escrow_transactions t
        JOIN listings l ON t.listing_id = l.listing_id
        JOIN users u ON t.seller_id = u.user_id
        WHERE t.mpesa_checkout_id = :checkout AND t.buyer_id = :buyer AND t.transaction_status = 'funded'
    ");
    $stmt->execute([':checkout' => $checkout_id, ':buyer' => $user_id]);
    $deal = $stmt->fetch();

    if (!$deal) {
        // Failsafe if they refresh the page or try to access a bad link
        header("Location: profile.php");
        exit();
    }
} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System error loading success screen.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Secured | MILELE</title>
    <style>
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; background-image: radial-gradient(circle at 50% 0%, rgba(45, 212, 191, 0.1), transparent 50%); }
        .success-box { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(24px); border: 1px solid rgba(45, 212, 191, 0.2); padding: 40px; border-radius: 32px; max-width: 500px; width: 100%; text-align: center; box-shadow: 0 24px 48px rgba(0,0,0,0.5); }
        
        .icon { font-size: 4rem; margin-bottom: 10px; }
        h1 { margin: 0 0 10px 0; font-size: 1.8rem; color: #2DD4BF; }
        .subtitle { color: #888; font-size: 0.95rem; margin-bottom: 30px; line-height: 1.5; }
        
        .pin-display { background: rgba(0,0,0,0.5); border: 1px solid rgba(45,212,191,0.3); padding: 20px; border-radius: 16px; margin-bottom: 30px; }
        .pin-label { color: #666; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; display: block; }
        .pin-code { font-size: 3.5rem; font-weight: bold; letter-spacing: 15px; color: #fff; text-shadow: 0 0 20px rgba(45,212,191,0.4); margin-left: 15px;}

        .steps-container { text-align: left; background: rgba(255,255,255,0.03); padding: 20px; border-radius: 16px; margin-bottom: 30px; }
        .step { display: flex; gap: 15px; margin-bottom: 15px; }
        .step:last-child { margin-bottom: 0; }
        .step-number { background: #2DD4BF; color: #000; width: 24px; height: 24px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 0.85rem; flex-shrink: 0; }
        .step-text h3 { margin: 0 0 5px 0; font-size: 1rem; color: #fff; }
        .step-text p { margin: 0; color: #888; font-size: 0.85rem; line-height: 1.4; }

        .btn-continue { display: block; padding: 16px; background: #fff; color: #000; border-radius: 16px; text-decoration: none; font-weight: bold; font-size: 1.05rem; transition: 0.3s; }
        .btn-continue:hover { background: #2DD4BF; transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="success-box">
    <div class="icon">🛡️</div>
    <h1>Funds Locked in Vault</h1>
    <div class="subtitle">Your payment of KES <?php echo number_format($deal['total_amount']); ?> is securely held by MILELE. The seller cannot access it until you release it.</div>
    
    <div class="pin-display">
        <span class="pin-label">Your Escrow Release PIN</span>
        <div class="pin-code"><?php echo htmlspecialchars($deal['escrow_pin']); ?></div>
    </div>

    <div class="steps-container">
        <div class="step">
            <div class="step-number">1</div>
            <div class="step-text">
                <h3>Meet the Seller</h3>
                <p>Use the chat feature to coordinate a safe meetup with <?php echo htmlspecialchars(explode(' ', $deal['seller_name'])[0]); ?>.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-number">2</div>
            <div class="step-text">
                <h3>Inspect the Item</h3>
                <p>Carefully review <strong><?php echo htmlspecialchars($deal['title']); ?></strong> to ensure it matches the description.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-number">3</div>
            <div class="step-text">
                <h3>Release the Funds</h3>
                <p>If you are satisfied, give the seller your PIN. Once they enter it, the funds will transfer to them.</p>
            </div>
        </div>
    </div>

    <a href="profile.php" class="btn-continue">Go to Dashboard</a>
</div>

</body>
</html>