<?php
// MILELE - Real Daraja API M-Pesa Checkout (Fully Authenticated)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=checkout");
    exit();
}

require 'db.php';
$my_id = $_SESSION['user_id'];
$item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$error = '';
$success_message = '';

if (!$item_id) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>Invalid Item ID.</div>");
}

// ==========================================
// 🛒 FETCH ITEM DATA
// ==========================================
try {
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name as seller_name, u.university_name 
        FROM listings l JOIN users u ON l.seller_id = u.user_id 
        WHERE l.listing_id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();

    if (!$item) die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>Item not found.</div>");
    if ($item['listing_status'] !== 'active') $error = "This item is no longer available.";
    if ($item['seller_id'] == $my_id) $error = "You cannot purchase your own item.";

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}

// 3% Dynamic Math
$item_price = (float)$item['price'];
$escrow_fee = $item_price * 0.03; 
$total_price = $item_price + $escrow_fee;

$images = json_decode($item['image_path'], true);
$thumbnail = (is_array($images) && count($images) > 0) ? $images[0] : $item['image_path'];

// ==========================================
// 🚀 TRIGGER SAFARICOM STK PUSH
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay') {
    $phone = trim(filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_SPECIAL_CHARS));
    
    // Format phone from 07XX to 2547XX
    if (preg_match('/^(?:254|\+254|0)?(7[0-9]{8}|1[0-9]{8})$/', $phone, $matches)) {
        $formatted_phone = '254' . $matches[1];
        
        // --- YOUR EXPLICIT DARAJA CREDENTIALS ---
        $consumerKey = 'LA33OtNfdXyPyrTozI5KGULDecju2sAyNYMGdp85mTuRX9UA'; 
        $consumerSecret = 'MjthfBtuHJS2ezFAdMuGW87qaJd5MLn2fDRLiSnc2EVY4czOuJA1aZD3oyKmiGno'; 
        $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'; // Standard Safaricom Sandbox Passkey
        $BusinessShortCode = '174379'; 
        // ----------------------------------------

        // 1. Get Access Token
        $headers = ['Content-Type:application/json; charset=utf8'];
        $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_USERPWD, $consumerKey.':'.$consumerSecret);
        $result = json_decode(curl_exec($ch));
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status == 200 && isset($result->access_token)) {
            $access_token = $result->access_token;
            
            // 2. Prepare STK Push Payload
            $stk_url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
            $Timestamp = date('YmdHis');
            $Password = base64_encode($BusinessShortCode.$passkey.$Timestamp);
            
            // Webhook linking back to your Heroku app
            $callback_url = 'https://milele-campus-live-56fbf7c046b3.herokuapp.com/mpesa_callback.php?item=' . $item_id . '&buyer=' . $my_id;

            $curl_post_data = array(
                'BusinessShortCode' => $BusinessShortCode,
                'Password' => $Password,
                'Timestamp' => $Timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => 1, // Kept at 1 for Sandbox limits
                'PartyA' => $formatted_phone,
                'PartyB' => $BusinessShortCode,
                'PhoneNumber' => $formatted_phone,
                'CallBackURL' => $callback_url,
                'AccountReference' => 'MILELE ESCROW',
                'TransactionDesc' => 'Escrow Item: ' . $item_id
            );

            // 3. Fire STK Push
            $ch_stk = curl_init($stk_url);
            curl_setopt($ch_stk, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$access_token));
            curl_setopt($ch_stk, CURLOPT_POST, 1);
            curl_setopt($ch_stk, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
            curl_setopt($ch_stk, CURLOPT_RETURNTRANSFER, 1);
            $stk_response = json_decode(curl_exec($ch_stk));
            curl_close($ch_stk);

            if (isset($stk_response->ResponseCode) && $stk_response->ResponseCode == "0") {
                $success_message = "STK Push sent! Please check your phone and enter your M-Pesa PIN. Once paid, check your dashboard.";
            } else {
                $error = "M-Pesa STK Push failed. Please check your phone number and try again.";
            }
        } else {
            $error = "Failed to connect to Safaricom Daraja API. Check credentials.";
        }
    } else {
        $error = "Invalid Safaricom phone number format.";
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
        
        .checkout-container { max-width: 1000px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: 1.2fr 1fr; gap: 40px;}
        
        .summary-card { background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0) 100%); border: 1px solid rgba(255,255,255,0.08); border-radius: 24px; padding: 40px;}
        .summary-title { font-size: 1.1rem; font-weight: bold; color: #aaa; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 30px;}
        .item-preview { display: flex; gap: 20px; margin-bottom: 40px; align-items: center;}
        .item-img { width: 100px; height: 100px; border-radius: 16px; object-fit: contain; background: #111; border: 1px solid rgba(255,255,255,0.05);}
        .item-name { font-size: 1.3rem; font-weight: bold; margin: 0 0 8px 0; color: #fff;}
        .seller-info { color: #888; font-size: 0.95rem;}
        .seller-name { color: #2DD4BF; font-weight: bold;}

        .receipt-box { background: rgba(0,0,0,0.4); border-radius: 16px; padding: 20px; border: 1px solid rgba(255,255,255,0.05);}
        .receipt-row { display: flex; justify-content: space-between; padding: 12px 0; color: #bbb; font-size: 1.1rem;}
        .receipt-row span:last-child { font-family: monospace; font-size: 1.2rem; color: #fff;}
        .receipt-divider { border-bottom: 1px dashed rgba(255,255,255,0.2); margin: 10px 0;}
        .receipt-total { display: flex; justify-content: space-between; padding-top: 15px; font-size: 1.4rem; font-weight: bold; color: #2DD4BF;}
        .receipt-total span:last-child { font-family: monospace; font-size: 1.6rem; }

        .payment-card { background: #0a0a0a; border: 1px solid rgba(255,255,255,0.08); border-radius: 24px; padding: 40px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); position: relative; overflow: hidden;}
        .payment-card::before { content: ''; position: absolute; top: 0; right: 0; width: 150px; height: 150px; background: rgba(37,211,102,0.15); filter: blur(60px); pointer-events: none;}

        .mpesa-header { display: flex; flex-direction: column; gap: 15px; margin-bottom: 35px;}
        .mpesa-logo { background: #25D366; color: #fff; font-weight: 900; font-style: italic; padding: 8px 20px; border-radius: 12px; font-size: 1.5rem; letter-spacing: -1px; display: inline-block;}
        
        .input-group { margin-bottom: 30px;}
        .input-label { display: block; font-size: 0.95rem; font-weight: bold; color: #ccc; margin-bottom: 12px;}
        .input-field { width: 100%; background: rgba(0,0,0,0.6); border: 2px solid rgba(255,255,255,0.1); padding: 20px; border-radius: 16px; color: #fff; font-size: 1.4rem; outline: none; transition: 0.3s; box-sizing: border-box; font-family: monospace; letter-spacing: 2px;}
        .input-field:focus { border-color: #25D366; }

        .btn-pay { width: 100%; padding: 22px; background: #2DD4BF; color: #000; border: none; border-radius: 16px; font-size: 1.3rem; font-weight: bold; cursor: pointer; transition: 0.3s;}
        .btn-pay:hover:not(:disabled) { background: #fff; transform: translateY(-3px);}
        .btn-dashboard { width: 100%; padding: 22px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 16px; font-size: 1.1rem; font-weight: bold; cursor: pointer; text-decoration: none; display: block; text-align: center; margin-top: 15px;}

        .alert-error { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: bold; text-align: center;}
        .alert-success { background: rgba(37,211,102,0.1); color: #25D366; border: 1px solid rgba(37,211,102,0.3); padding: 25px; border-radius: 12px; margin-bottom: 25px; font-weight: bold; text-align: center; line-height: 1.5;}

        @media (max-width: 768px) {
            .checkout-container { grid-template-columns: 1fr; }
            .payment-card { order: -1; padding: 30px 20px;}
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
            </div>
        </div>

        <div class="receipt-box">
            <div class="receipt-row"><span>Item Price</span><span>KES <?php echo number_format($item_price, 2); ?></span></div>
            <div class="receipt-row"><span>Escrow Fee (3%)</span><span>KES <?php echo number_format($escrow_fee, 2); ?></span></div>
            <div class="receipt-divider"></div>
            <div class="receipt-total"><span>Total to Pay</span><span>KES <?php echo number_format($total_price, 2); ?></span></div>
        </div>
    </div>

    <div class="payment-card">
        <?php if($error): ?><div class="alert-error"><?php echo $error; ?></div><?php endif; ?>

        <?php if($success_message): ?>
            <div class="alert-success">
                <div style="font-size: 3rem; margin-bottom: 10px;">📱</div>
                <?php echo $success_message; ?>
            </div>
            <a href="profile.php" class="btn-dashboard">Go to Dashboard</a>
        
        <?php elseif(!$error || strpos($error, 'valid') !== false): ?>
            <div class="mpesa-header">
                <div><span class="mpesa-logo">M-PESA</span></div>
                <div class="mpesa-desc">Enter your phone number. An STK push prompt will appear on your phone to securely authorize the payment.</div>
            </div>

            <form method="POST" id="checkoutForm" onsubmit="document.getElementById('payBtn').innerHTML='Sending Request...'; document.getElementById('payBtn').style.opacity='0.5';">
                <input type="hidden" name="action" value="pay">
                <div class="input-group">
                    <label class="input-label">M-Pesa Phone Number</label>
                    <input type="tel" name="phone_number" class="input-field" placeholder="07XX XXX XXX" required autocomplete="off">
                </div>
                <button type="submit" class="btn-pay" id="payBtn">Pay KES <?php echo number_format($total_price, 2); ?></button>
            </form>
        <?php endif; ?>
    </div>

</div>
</body>
</html>