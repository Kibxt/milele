<?php
// MILELE - Premium Checkout UI

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
    $stmt = $pdo->prepare("SELECT l.*, u.full_name FROM listings l JOIN users u ON l.seller_id = u.user_id WHERE l.listing_id = :id AND l.listing_status = 'active'");
    $stmt->execute([':id' => $listing_id]);
    $item = $stmt->fetch();

    if (!$item) {
        header("Location: index.php");
        exit();
    }
    
    // Financials
    $price = (float)$item['price'];
    $platform_fee = $price * 0.03;
    $total = $price + $platform_fee;

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System error loading checkout.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | MILELE</title>
    <style>
        /* Ultra-Premium Glass Aesthetic */
        body { background: #000; color: #fff; font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .checkout-box { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 40px; border-radius: 24px; width: 100%; max-width: 450px; text-align: center; }
        .item-title { font-size: 1.5rem; margin-bottom: 10px; color: #2DD4BF; }
        .seller-name { color: #888; font-size: 0.9rem; margin-bottom: 30px; }
        .price-row { display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
        .total-row { display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: bold; margin-bottom: 30px; color: #2DD4BF; }
        input[type="text"] { width: 100%; padding: 15px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 12px; margin-bottom: 20px; box-sizing: border-box;}
        .btn-pay { width: 100%; padding: 15px; background: #2DD4BF; color: #000; border: none; border-radius: 12px; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: 0.2s; }
        .btn-pay:hover { background: #fff; }
        .back { display: block; margin-top: 20px; color: #888; text-decoration: none; }
    </style>
</head>
<body>

<div class="checkout-box">
    <h2>Secure Checkout</h2>
    <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
    <div class="seller-name">Sold by <?php echo htmlspecialchars(explode(' ', $item['full_name'])[0]); ?></div>

    <div class="price-row">
        <span>Item Price</span>
        <span>KES <?php echo number_format($price, 2); ?></span>
    </div>
    <div class="price-row">
        <span>Platform Fee (3%)</span>
        <span>KES <?php echo number_format($platform_fee, 2); ?></span>
    </div>
    <div class="total-row">
        <span>Total</span>
        <span>KES <?php echo number_format($total, 2); ?></span>
    </div>

    <?php if(isset($_SESSION['error_msg'])): ?>
        <p style="color: #F87171;"><?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></p>
    <?php endif; ?>

    <form action="process_checkout.php" method="POST">
        <input type="hidden" name="listing_id" value="<?php echo $item['listing_id']; ?>">
        <input type="text" name="phone_number" placeholder="M-Pesa Phone Number (07...)" required>
        <button type="submit" class="btn-pay">Pay Now</button>
    </form>

    <a href="index.php" class="back">Cancel</a>
</div>

</body>
</html>