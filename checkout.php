<?php
// MILELE - Premium M-Pesa Checkout Engine

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=checkout");
    exit();
}

require 'db.php';
$my_id = $_SESSION['user_id'];
$item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$error = '';
$escrow_fee = 50; // MILELE Flat Escrow Fee (KES)

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

$total_price = $item['price'] + $escrow_fee;
$images = json_decode($item['image_path'], true);
$thumbnail = (is_array($images) && count($images) > 0) ? $images[0] : $item['image_path'];

// ==========================================
// 💸 PROCESS PAYMENT (M-PESA SIMULATION)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay') {
    $phone = trim(filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_SPECIAL_CHARS));
    
    // Basic Phone Validation (Kenya)
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
        
        /* Left Column: Item Summary */
        .summary-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; padding: 30px;}
        .summary-title { font-size: 1.2rem; font-weight: bold; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px;}
        
        .item-preview { display: flex; gap: 20px; margin-bottom: 30px;}
        .item-img { width: 120px; height: 120px; border-radius: 16px; object-fit: contain; background: #111; border: 1px solid rgba(255,255,255,0.05);}
        .item-details { flex-grow: 1; display: flex; flex-direction: column; justify-content: center;}
        .item-name { font-size: 1.4rem; font-weight: bold; margin: 0 0 5px 0;}
        .seller-info { color: #888; font-size: 0.9rem;}
        .seller-name { color: #2DD4BF; font-weight: bold;}

        .receipt-row { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px dashed rgba(255,255,255,0.1); font-size: 1.1rem;}
        .receipt-total { display: flex; justify-content: space-between; padding: 20px 0 0; font-size: 1.5rem; font-weight: bold; color: #2DD4BF;}

        .trust-badges { display: flex; gap: 15px; margin-top: 30px; background: rgba(45,212,191,0.05); padding: 20px; border-radius: 16px; border: 1px solid rgba(45,212,191,0.2);}
        .badge { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; color: #aaa;}
        .badge-icon { font-size: 1.5rem; }

        /* Right Column: Payment Form */
        .payment-card { background: radial-gradient(circle at top right, rgba(45,212,191,0.05), transparent 70%), #0a0a0a; border: 1px solid rgba(255,255,255,0.08); border-radius: 24px; padding: 40px;}
        
        .mpesa-header { display: flex; align-items: center; gap: 15px; margin-bottom: 30px;}
        .mpesa-logo { background: #25D366; color: #fff; font-weight: 900; font-style: italic; padding: 5px 15px; border-radius: 8px; font-size: 1.5rem; letter-spacing: -1px;}
        .mpesa-desc { color: #888; font-size: 0.9rem; line-height: 1.4;}

        .input-group { margin-bottom: 25px;}
        .input-label { display: block; font-size: 0.9rem; font-weight: bold; color: #fff; margin-bottom: 10px;}
        .input-field { width: 100%; background: rgba(0,0,0,0.5); border: 2px solid rgba(255,255,255,0.1); padding: 18px 20px; border-radius: 16px; color: #fff; font-size: 1.2rem; outline: none; transition: 0.3s; box-sizing: border-box; font-family: monospace;}
        .input-field:focus { border-color: #25D366; background: rgba(37,211,102,0.05); }

        .btn-pay { width: 100%; padding: 20px; background: #2DD4BF; color: #000; border: none; border-radius: 16px; font-size: 1.2rem; font-weight: bold; cursor: pointer; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 10px;}
        .btn-pay:hover:not(:disabled) { background: #fff; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(45,212,191,0.3);}
        .btn-pay:disabled { background: #333; color: #888; cursor: not-allowed; transform: none; box-shadow: none;}

        .alert-error { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: bold; text-align: center;}

        @media (max-width: 768px) {
            .checkout-container { grid-template-columns: 1fr; }
            .payment-card { order: -1; } /* Puts payment form on top on mobile */
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
                <div class="seller-info" style="font-size: 0.8rem; margin-top: 5px;">🎓 <?php echo htmlspecialchars($item['university_name']); ?></div>
            </div>
        </div>

        <div class="receipt-row">
            <span>Item Price</span>
            <span>KES <?php echo number_format($item['price'], 2); ?></span>
        </div>
        <div class="receipt-row">
            <span>MILELE Escrow Fee</span>
            <span>KES <?php echo number_format($escrow_fee, 2); ?></span>
        </div>
        
        <div class="receipt-total">
            <span>Total to Pay</span>
            <span>KES <?php echo number_format($total_price, 2); ?></span>
        </div>

        <div class="trust-badges">
            <div class="badge">
                <span class="badge-icon">🔒</span>
                <span>Funds are held securely in Escrow until you receive the item.</span>
            </div>
        </div>
    </div>

    <div class="payment-card">
        <?php if($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if(!$error || strpos($error, 'valid') !== false): // Only show form if item is valid ?>
            <div class="mpesa-header">
                <div class="mpesa-logo">M-PESA</div>
                <div class="mpesa-desc">Enter your M-Pesa registered phone number. A prompt will appear on your phone to authorize the payment.</div>
            </div>

            <form method="POST" id="checkoutForm" onsubmit="processPayment(event)">
                <input type="hidden" name="action" value="pay">
                
                <div class="input-group">
                    <label class="input-label">M-Pesa Phone Number</label>
                    <input type="text" name="phone_number" class="input-field" placeholder="07XX XXX XXX" required autocomplete="off">
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
        // Visual feedback to simulate the M-Pesa prompt delay
        const btn = document.getElementById('payBtn');
        const text = document.getElementById('btnText');
        
        btn.disabled = true;
        text.innerHTML = '⏳ Waiting for M-Pesa Pin...';
        
        // We let the form submit naturally, but this gives the user immediate visual feedback
    }
</script>

</body>
</html>