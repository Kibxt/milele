<?php
// MILELE - Premium Checkout Interface

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$listing_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$listing_id) {
    header("Location: index.php");
    exit();
}

try {
    // Fetch the item details
    $stmt = $pdo->prepare("SELECT l.*, u.full_name as seller_name FROM listings l JOIN users u ON l.seller_id = u.user_id WHERE l.listing_id = :id AND l.listing_status = 'active'");
    $stmt->execute([':id' => $listing_id]);
    $item = $stmt->fetch();

    if (!$item) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:sans-serif;'>This item is no longer available.</div>");
    }

    // Prevent self-purchasing
    if ($item['seller_id'] == $_SESSION['user_id']) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:sans-serif;'>You cannot purchase your own item.</div>");
    }

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:sans-serif;'>System error loading checkout.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | MILELE</title>
    <style>
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; background-image: radial-gradient(circle at 50% 0%, rgba(45, 212, 191, 0.05), transparent 50%); }
        .checkout-box { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.08); padding: 40px; border-radius: 32px; max-width: 450px; width: 100%; box-shadow: 0 24px 48px rgba(0,0,0,0.5); }
        
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { margin: 0 0 10px 0; font-size: 1.8rem; }
        .header p { color: #888; font-size: 0.95rem; margin: 0; }

        .order-summary { background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 16px; margin-bottom: 30px; }
        .order-item { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .order-item:last-child { margin-bottom: 0; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); font-weight: bold; font-size: 1.2rem; color: #2DD4BF; }
        .item-name { color: #ccc; }
        .item-value { color: #fff; }

        .input-group { margin-bottom: 25px; }
        .input-group label { display: block; font-size: 0.85rem; color: #888; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .phone-input-wrapper { display: flex; align-items: center; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 5px 15px; transition: 0.3s; }
        .phone-input-wrapper:focus-within { border-color: #2DD4BF; background: rgba(255, 255, 255, 0.08); }
        .prefix { color: #2DD4BF; font-weight: bold; margin-right: 10px; font-size: 1.1rem; }
        .input-group input { flex-grow: 1; background: transparent; border: none; color: #fff; padding: 12px 0; font-size: 1.1rem; outline: none; }

        .btn-pay { width: 100%; padding: 16px; background: #2DD4BF; color: #000; border: none; border-radius: 16px; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: 0.2s; }
        .btn-pay:hover { background: #fff; transform: translateY(-2px); }
        
        .btn-cancel { display: block; text-align: center; margin-top: 15px; color: #888; text-decoration: none; font-size: 0.9rem; transition: 0.2s; }
        .btn-cancel:hover { color: #fff; }

        .secure-badge { text-align: center; font-size: 0.8rem; color: #666; margin-top: 25px; display: flex; align-items: center; justify-content: center; gap: 5px; }
    </style>
</head>
<body>

<div class="checkout-box">
    <div class="header">
        <h1>Secure Checkout</h1>
        <p>Your funds will be held in escrow until you receive the item.</p>
    </div>

    <div class="order-summary">
        <div class="order-item">
            <span class="item-name"><?php echo htmlspecialchars($item['title']); ?></span>
            <span class="item-value">KES <?php echo number_format($item['price'], 2); ?></span>
        </div>
        <div class="order-item" style="font-size: 0.9rem;">
            <span class="item-name">Seller</span>
            <span class="item-value"><?php echo htmlspecialchars(explode(' ', $item['seller_name'])[0]); ?></span>
        </div>
        <div class="order-item">
            <span class="item-name">Total to Pay</span>
            <span class="item-value">KES <?php echo number_format($item['price'], 2); ?></span>
        </div>
    </div>

    <form action="process_checkout.php" method="POST">
        <input type="hidden" name="listing_id" value="<?php echo $item['listing_id']; ?>">
        
        <div class="input-group">
            <label>M-Pesa Phone Number</label>
            <div class="phone-input-wrapper">
                <span class="prefix">📱</span>
                <input type="text" name="phone_number" placeholder="07XX XXX XXX" required autocomplete="off">
            </div>
        </div>

        <button type="submit" class="btn-pay">Pay KES <?php echo number_format($item['price'], 2); ?></button>
    </form>
    
    <a href="item.php?id=<?php echo $item['listing_id']; ?>" class="btn-cancel">Cancel</a>

    <div class="secure-badge">
        🔒 Secured by Safaricom Daraja API
    </div>
</div>

</body>
</html>