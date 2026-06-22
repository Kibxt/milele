<?php
// MILELE - Premium M-Pesa Checkout Engine (Dynamic 3% Fee)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=checkout");
    exit();
}

require 'db.php';
$my_id = $_SESSION['user_id'];
$item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$error = '';

if (!$item_id) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>Invalid Item ID.</div>");
}

// ==========================================
// 🛒 FETCH ITEM DATA & VALIDATE
// ==========================================
try {
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name as seller_name, u.university_name 
        FROM listings l 
        JOIN users u ON l.seller_id = u.user_id 
        WHERE l.listing_id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();

    if (!$item) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>Item not found.</div>");
    }

    if ($item['listing_status'] !== 'active') {
        $error = "This item is no longer available. It is currently locked in escrow or sold.";
    }

    if ($item['seller_id'] == $my_id) {
        $error = "You cannot purchase your own item.";
    }

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}

// ==========================================
// 🧮 DYNAMIC 3% FEE CALCULATION
// ==========================================
$item_price = (float)$item['price'];
$escrow_fee = $item_price * 0.03; // Exactly 3% of the item price
$total_price = $item_price + $escrow_fee;

$images = json_decode($item['image_path'], true);
$thumbnail = (is_array($images) && count($images) > 0) ? $images[0] : $item['image_path'];

// ==========================================
// 💸 PROCESS PAYMENT (M-PESA SIMULATION)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay') {
    $phone = trim(filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_SPECIAL_CHARS));
    
    // Basic Phone Validation
    if (strlen($phone) < 9) {
        $error = "Please enter a valid Safaricom phone number.";
    } else {
        try {
            // 1. Generate the Unique Transaction PIN
            $generated_pin = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            
            // 2. Lock the item, assign buyer, set PIN
            $stmt_update = $pdo->prepare("
                UPDATE listings 
                SET listing_status = 'escrow', buyer_id = ?, escrow_pin = ? 
                WHERE listing_id = ? AND listing_status = 'active'
            ");
            $success = $stmt_update->execute([$my_id, $generated_pin, $item_id]);

            if ($success && $stmt_update->rowCount() > 0) {
                // Redirect straight to their vault to see the PIN
                header("Location: profile.php?payment=success");
                exit();
            } else {
                $error = "Transaction failed. Another buyer may have just locked this item.";
            }
        } catch (PDOException $e) {
            $error = "System Error during checkout: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout | MILELE</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; min-height: 100vh; display: flex; flex-direction: column;}
        
        .nav-bar { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(5,5,5,0.8); backdrop-filter: blur(20px);}
        .brand { font-size: 1.8rem; font-weight: 800; color: #2DD4BF; text-decoration: none; letter-spacing: -1px;}
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; font-weight: bold; transition: 0.3s;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }

        .checkout-container { max-width: 1000px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: 1.2fr 1fr; gap: 40px;}
        
        /* Left Column: Premium Receipt */
        .summary-card { background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0) 100%); border: 1px solid rgba(255,255,255,0.08); border-radius: 24px; padding: 40px;}
        .summary-title { font-size: 1.1rem; font-weight: bold; color: #aaa; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 30px;}
        
        .item-preview { display: flex; gap: 20px; margin-bottom: 40px; align-items: center;}
        .item-img { width: 100px; height: 100px; border-radius: 16px; object-fit: contain; background: #111; border: 1px solid rgba(255,255,255,0.05);}
        .item-details { flex-grow: 1; display: flex; flex-direction: column;}
        .item-name { font-size: 1.3rem; font-weight: bold; margin: 0 0 8px 0; color: #fff;}
        .seller-info { color: #888; font-size: 0.95rem;}
        .seller-name { color: #2DD4BF; font-weight: bold;}

        /* Math Breakdown */
        .receipt-box { background: rgba(0,0,0,0.4); border-radius: 16px; padding: 20px; border: 1px solid rgba(255,255,255,0.05);}
        .receipt-row { display: flex; justify-content: space-between; padding: 12px 0; color: #bbb; font-size: 1.1rem;}
        .receipt-row span:last-child { font-family: monospace; font-size: 1.2rem; color: #fff;}
        
        .receipt-divider { border-bottom: 1px dashed rgba(255,255,255,0.2); margin: 10px 0;}
        
        .receipt-total { display: flex; justify-content: space-between; padding-top: 15px; font-size: 1.4rem; font-weight: bold; color: #2DD4BF;}
        .receipt-total span:last-child { font-family: monospace; font-size: 1.6rem; }

        .trust-badges { display: flex; gap: 15px; margin-top: 30px; background: rgba(45,212,191,0.05); padding: 20px; border-radius: 16px; border: 1px solid rgba(45,212,191,0.2);}
        .badge { display: flex; align-items: center; gap: 12px; font-size: 0.9rem; color: #aaa; line-height: 1.4;}
        .badge-icon { font-size: 1.8rem; }

        /* Right Column: Payment Form */
        .payment-card { background: #0a0a0a; border: 1px solid rgba(255,255,255,0.08); border-radius: 24px; padding: 40px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); position: relative; overflow: hidden;}
        
        /* M-Pesa Green Glow Effect */
        .payment-card::before { content: ''; position: absolute; top: 0; right: 0; width: 150px; height: 150px; background: rgba(37,211,102,0.15); filter: blur(60px); pointer-events: none;}

        .mpesa-header { display: flex; flex-direction: column; gap: 15px; margin-bottom: 35px;}
        .mpesa-badge-container { display: flex; align-items: center; gap: 10px;}
        .mpesa-logo { background: #25D366; color: #fff; font-weight: 900; font-style: italic; padding: 8px 20px; border-radius: 12px; font-size: 1.5rem; letter-spacing: -1px; display: inline-block;}
        .secure-lock { color: #25D366; font-size: 1.2rem;}
        
        .mpesa-desc { color: #888; font-size: 0.95rem; line-height: 1.5;}

        .input-group { margin-bottom: 30px;}
        .input-label { display: block; font-size: 0.95rem; font-weight: bold; color: #ccc; margin-bottom: 12px;}
        .input-field { width: 100%; background: rgba(0,0,0,0.6); border: 2px solid rgba(255,255,255,0.1); padding: 20px; border-radius: 16px; color: #fff; font-size: 1.4rem; outline: none; transition: 0.3s; box-sizing: border-box; font-family: monospace; letter-spacing: 2px;}
        .input-field:focus { border-color: #25D366; background: rgba(37,211,102,0.05); }
        .input-field::placeholder { color: #444; }

        .btn-pay { width: 100%; padding: 22px; background: #2DD4BF; color: #000; border: none; border-radius: 16px; font-size: 1.3rem; font-weight: bold; cursor: pointer; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 10px;}
        .btn-pay:hover:not(:disabled) { background: #fff; transform: translateY(-3px); box-shadow: 0 15px 30px rgba(45,212,191,0.3);}
        .btn-pay:disabled { background: #222; color: #666; cursor: not-allowed; transform: none; box-shadow: none;}

        .alert-error { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: bold; text-align: center;}

        @media (max-width: 768px) {
            .checkout-container { grid-template-columns: 1fr; }
            .payment-card { order: -1; } 
            .payment-card { padding: 30px 20px;}
            .summary-card { padding: 30px 20px;}
        }
    </style>
</head>
<body>

<nav class="nav-bar">
    <a href="index.php" class="brand">MILELE</a>
    <a href="item.php?id=<?php echo $item_id; ?>" class="btn-glass">← Cancel</a>
</nav>

<div class="checkout-container">
    
    <div class="summary-card">
        <div class="summary-title">Order Summary</div>
        
        <div class="item-preview">
            <img src="<?php echo htmlspecialchars($thumbnail); ?>" class="item-img" alt="Item">
            <div class="item-details">
                <h2 class="item-name"><?php echo htmlspecialchars($item['title']); ?></h2>
                <div class="seller-info">Sold by <span class="seller-name"><?php echo htmlspecialchars($item['seller_name']); ?></span></div>
                <div class="seller-info" style="font-size: 0.85rem; margin-top: 5px;">🎓 <?php echo htmlspecialchars($item['university_name']); ?></div>
            </div>
        </div>

        <div class="receipt-box">
            <div class="receipt-row">
                <span>Item Price</span>
                <span>KES <?php echo number_format($item_price, 2); ?></span>
            </div>
            <div class="receipt-row">
                <span>MILELE Escrow Fee (3%)</span>
                <span>KES <?php echo number_format($escrow_fee, 2); ?></span>
            </div>
            
            <div class="receipt-divider"></div>
            
            <div class="receipt-total">
                <span>Total to Pay</span>
                <span>KES <?php echo number_format($total_price, 2); ?></span>
            </div>
        </div>

        <div class="trust-badges">
            <div class="badge">
                <span class="badge-icon">🔒</span>
                <span>Your funds are locked securely in MILELE Escrow. The seller is only paid when you receive the item and provide them with your unique PIN.</span>
            </div>
        </div>
    </div>

    <div class="payment-card">
        <?php if($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if(!$error || strpos($error, 'valid') !== false): ?>
            <div class="mpesa-header">
                <div class="mpesa-badge-container">
                    <div class="mpesa-logo">M-PESA</div>
                    <span class="secure-lock">🔒</span>
                </div>
                <div class="mpesa-desc">Enter your Safaricom registered phone number. An STK push prompt will appear on your phone to securely authorize the payment.</div>
            </div>

            <form method="POST" id="checkoutForm" onsubmit="processPayment(event)">
                <input type="hidden" name="action" value="pay">
                
                <div class="input-group">
                    <label class="input-label">M-Pesa Phone Number</label>
                    <input type="tel" name="phone_number" class="input-field" placeholder="07XX XXX XXX" required autocomplete="off" pattern="[0-9]{9,12}" title="Enter a valid Safaricom number">
                </div>

                <button type="submit" class="btn-pay" id="payBtn">
                    <span id="btnText">Pay KES <?php echo number_format($total_price, 2); ?></span>
                </button>
            </form>
        <?php endif; ?>
    </div>

</div>

<script>
    function processPayment(e) {
        const btn = document.getElementById('payBtn');
        const text = document.getElementById('btnText');
        
        btn.disabled = true;
        text.innerHTML = '⏳ Waiting for M-Pesa Pin...';
    }
</script>

</body>
</html>