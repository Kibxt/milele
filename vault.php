<?php
// MILELE - Secure Escrow Vault (Buyer View)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ⚡ THE MASTER CONNECTION
require 'db.php';

$transaction_id = filter_input(INPUT_GET, 'tx', FILTER_VALIDATE_INT);
if (!$transaction_id) {
    header("Location: index.php");
    exit();
}

try {
    // Verify the user actually bought this physical item
    $stmt = $pdo->prepare("
        SELECT t.*, l.title, u.full_name as seller_name 
        FROM escrow_transactions t
        JOIN listings l ON t.listing_id = l.listing_id
        JOIN users u ON t.seller_id = u.user_id
        WHERE t.transaction_id = :tx AND t.buyer_id = :buyer
    ");
    $stmt->execute([':tx' => $transaction_id, ':buyer' => $_SESSION['user_id']]);
    $vault = $stmt->fetch();

    if (!$vault) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:sans-serif;'>Access Denied. Vault not found.</div>");
    }

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:sans-serif;'>System error loading vault.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Vault | MILELE</title>
    <style>
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background-image: radial-gradient(circle at 50% 0%, rgba(45, 212, 191, 0.1), transparent 50%); }
        .vault-box { background: rgba(255,255,255,0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.05); padding: 50px; border-radius: 32px; text-align: center; max-width: 500px; width: 90%; box-shadow: 0 24px 48px rgba(0,0,0,0.5); }
        .icon { font-size: 3rem; margin-bottom: 20px; }
        h1 { color: #fff; margin: 0 0 10px 0; font-size: 1.8rem; }
        p { color: #888; line-height: 1.5; margin-bottom: 30px; }
        .item-name { color: #2DD4BF; font-weight: bold; }
        
        .pin-display { background: rgba(0,0,0,0.5); border: 1px solid rgba(45,212,191,0.3); padding: 20px; border-radius: 16px; margin-bottom: 30px; }
        .pin-label { color: #666; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; display: block; }
        .pin-code { font-size: 3rem; font-weight: bold; letter-spacing: 10px; color: #2DD4BF; text-shadow: 0 0 20px rgba(45,212,191,0.4); }
        
        .warning-box { background: rgba(248,113,113,0.1); border-left: 4px solid #F87171; padding: 15px; text-align: left; border-radius: 8px; font-size: 0.9rem; color: #ccc; margin-bottom: 30px; }
        
        .btn-back { display: inline-block; padding: 15px 30px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border-radius: 12px; transition: 0.2s; border: 1px solid rgba(255,255,255,0.1); font-weight: bold; }
        .btn-back:hover { background: rgba(255,255,255,0.1); }
    </style>
</head>
<body>

<div class="vault-box">
    <div class="icon">🔐</div>
    <h1>Funds Locked in Escrow</h1>
    <p>Your payment for <span class="item-name"><?php echo htmlspecialchars($vault['title']); ?></span> is safely held by MILELE. Meet with <strong><?php echo htmlspecialchars(explode(' ', $vault['seller_name'])[0]); ?></strong> to inspect the item.</p>
    
    <div class="pin-display">
        <span class="pin-label">Your Release PIN</span>
        <div class="pin-code"><?php echo htmlspecialchars($vault['escrow_pin']); ?></div>
    </div>

    <div class="warning-box">
        <strong>⚠️ Security Notice:</strong> Do not give this PIN to the seller until you have physically received and inspected the item. Giving them this PIN releases your money instantly.
    </div>

    <a href="index.php" class="btn-back">Return to Dashboard</a>
</div>

</body>
</html>