<?php
// MILELE - Real-Time Daraja API M-Pesa Checkout (Premium UI)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=checkout");
    exit();
}

require 'db.php';
$my_id = $_SESSION['user_id'];
$item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$error = '';
$stk_pushed = false;

if (!$item_id) {
    die("<div style='background:#F7F5FF; color:#FF6B6B; padding:50px; text-align:center; font-family: sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold;'>Invalid Item ID.</div>");
}

// ==========================================
// 📡 REAL-TIME AJAX LISTENER (Silently checks DB)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'check_status') {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("SELECT listing_status FROM listings WHERE listing_id = ?");
        $stmt->execute([$item_id]);
        $status = $stmt->fetchColumn();
        echo json_encode(['status' => $status]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error']);
    }
    exit();
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

    if (!$item) die("<div style='background:#F7F5FF; color:#FF6B6B; padding:50px; text-align:center; font-family: sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold;'>Item not found.</div>");
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
    
    if (preg_match('/^(?:254|\+254|0)?(7[0-9]{8}|1[0-9]{8})$/', $phone, $matches)) {
        $formatted_phone = '254' . $matches[1];
        
        // --- YOUR EXPLICIT DARAJA CREDENTIALS ---
        $consumerKey = 'LA33OtNfdXyPyrTozI5KGULDecju2sAyNYMGdp85mTuRX9UA'; 
        $consumerSecret = 'MjthfBtuHJS2ezFAdMuGW87qaJd5MLn2fDRLiSnc2EVY4czOuJA1aZD3oyKmiGno'; 
        $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'; 
        $BusinessShortCode = '174379'; 
        // ----------------------------------------

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
            
            $stk_url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
            $Timestamp = date('YmdHis');
            $Password = base64_encode($BusinessShortCode.$passkey.$Timestamp);
            $callback_url = 'https://milele-campus-live-56fbf7c046b3.herokuapp.com/mpesa_callback.php?item=' . $item_id . '&buyer=' . $my_id;

            $curl_post_data = array(
                'BusinessShortCode' => $BusinessShortCode,
                'Password' => $Password,
                'Timestamp' => $Timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => 1, 
                'PartyA' => $formatted_phone,
                'PartyB' => $BusinessShortCode,
                'PhoneNumber' => $formatted_phone,
                'CallBackURL' => $callback_url,
                'AccountReference' => 'MILELE ESCROW',
                'TransactionDesc' => 'Escrow Item: ' . $item_id
            );

            $ch_stk = curl_init($stk_url);
            curl_setopt($ch_stk, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$access_token));
            curl_setopt($ch_stk, CURLOPT_POST, 1);
            curl_setopt($ch_stk, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
            curl_setopt($ch_stk, CURLOPT_RETURNTRANSFER, 1);
            $stk_response = json_decode(curl_exec($ch_stk));
            curl_close($ch_stk);

            if (isset($stk_response->ResponseCode) && $stk_response->ResponseCode == "0") {
                $stk_pushed = true; // Tell the frontend to switch to "Waiting" mode
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
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --indigo: #1A1040;
            --indigo-mid: #2D1B69;
            --amber: #F5A623;
            --coral: #FF6B6B;
            --mint: #00D4AA;
            --chalk: #F7F5FF;
            --slate: #8B7FA8;
            --white: #ffffff;
            --card-border: rgba(26,16,64,0.10);
            --mpesa-green: #25D366;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: var(--chalk); color: var(--indigo); font-family: 'Inter', sans-serif; display: flex; flex-direction: column; min-height: 100vh;}
        
        /* Navigation */
        nav { display: flex; justify-content: space-between; align-items: center; padding: 0 5%; border-bottom: 1px solid var(--card-border); background: rgba(247,245,255,0.94); backdrop-filter: blur(14px); position: sticky; top: 0; z-index: 100; height: 70px;}
        .brand { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; color: var(--indigo); text-decoration: none; display: flex; align-items: center; gap: 8px;}
        .logo-dot { width: 10px; height: 10px; background: var(--amber); border-radius: 50%; display: inline-block; }
        .btn-glass { padding: 9px 20px; background: transparent; color: var(--indigo); text-decoration: none; border: 1.5px solid var(--indigo); border-radius: 50px; font-weight: 700; font-size: 13px; transition: 0.2s;}
        .btn-glass:hover { background: var(--indigo); color: var(--white); }
        
        /* Main Layout */
        .checkout-container { max-width: 1100px; margin: 60px auto; padding: 0 20px; display: grid; grid-template-columns: 1.2fr 1fr; gap: 40px; flex-grow: 1; align-items: start;}
        
        /* Order Summary Card */
        .summary-card { background: var(--white); border: 1px solid var(--card-border); border-radius: 24px; padding: 40px; box-shadow: 0 20px 60px rgba(26,16,64,0.04);}
        .summary-title { font-size: 12px; font-weight: 800; color: var(--slate); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 30px;}
        
        .item-preview { display: flex; gap: 24px; margin-bottom: 40px; align-items: center;}
        .item-img { width: 110px; height: 110px; border-radius: 16px; object-fit: cover; background: var(--chalk); border: 1px solid var(--card-border);}
        .item-name { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; margin: 0 0 8px 0; color: var(--indigo); line-height: 1.2;}
        .seller-info { color: var(--slate); font-size: 14px; font-weight: 500;}
        .seller-name { color: var(--indigo); font-weight: 700;}

        /* Receipt Box */
        .receipt-box { background: var(--chalk); border-radius: 16px; padding: 24px; border: 1.5px dashed var(--card-border);}
        .receipt-row { display: flex; justify-content: space-between; padding: 12px 0; color: var(--slate); font-size: 15px; font-weight: 500;}
        .receipt-row span:last-child { font-family: 'Syne', sans-serif; font-weight: 700; color: var(--indigo);}
        .receipt-divider { border-bottom: 1.5px dashed var(--card-border); margin: 12px 0;}
        .receipt-total { display: flex; justify-content: space-between; padding-top: 16px; font-size: 18px; font-weight: 800; color: var(--indigo);}
        .receipt-total span:last-child { font-family: 'Syne', sans-serif; font-size: 24px; color: var(--indigo); }

        /* Payment Card */
        .payment-card { background: var(--white); border: 1px solid var(--card-border); border-radius: 24px; padding: 40px; box-shadow: 0 20px 60px rgba(26,16,64,0.08); position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: center; min-height: 400px;}
        .payment-card::before { content: ''; position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(37,211,102,0.08); filter: blur(60px); pointer-events: none; border-radius: 50%;}

        .mpesa-header { display: flex; flex-direction: column; gap: 12px; margin-bottom: 32px; position: relative; z-index: 2;}
        .mpesa-logo { background: var(--mpesa-green); color: var(--white); font-weight: 900; font-style: italic; padding: 8px 16px; border-radius: 12px; font-size: 20px; letter-spacing: -0.5px; display: inline-block; align-self: flex-start;}
        .mpesa-desc { font-size: 14px; color: var(--slate); line-height: 1.6; font-weight: 500;}
        
        .input-group { margin-bottom: 24px; position: relative; z-index: 2;}
        .input-label { display: block; font-size: 12px; font-weight: 800; color: var(--indigo); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.05em;}
        .input-field { width: 100%; background: var(--chalk); border: 2px solid var(--card-border); padding: 18px 24px; border-radius: 16px; color: var(--indigo); font-size: 20px; outline: none; transition: 0.3s; box-sizing: border-box; font-family: 'Syne', monospace; font-weight: 700; letter-spacing: 2px;}
        .input-field:focus { border-color: var(--mpesa-green); box-shadow: 0 0 0 4px rgba(37,211,102,0.1); background: var(--white);}

        .btn-pay { width: 100%; padding: 20px; background: var(--indigo); color: var(--white); border: none; border-radius: 16px; font-size: 16px; font-weight: 800; cursor: pointer; transition: 0.3s; font-family: 'Inter', sans-serif; position: relative; z-index: 2;}
        .btn-pay:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(26,16,64,0.2);}

        .alert-error { background: rgba(255,107,107,0.1); color: var(--coral); border: 1px solid rgba(255,107,107,0.2); padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600; text-align: center; font-size: 14px;}

        /* Real-Time Status Screens */
        .status-screen { text-align: center; display: none; padding: 20px 0; position: relative; z-index: 2;}
        .spinner { border: 4px solid var(--card-border); border-top: 4px solid var(--mpesa-green); border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; margin: 0 auto 24px;}
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .check-icon { font-size: 64px; color: var(--mpesa-green); margin-bottom: 16px; animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); display: flex; align-items: center; justify-content: center; background: rgba(37,211,102,0.1); width: 100px; height: 100px; border-radius: 50%; margin: 0 auto 24px;}
        @keyframes popIn { 0% { transform: scale(0); } 100% { transform: scale(1); } }

        .status-screen h2 { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; color: var(--indigo); margin-bottom: 12px;}
        .status-screen p { color: var(--slate); font-size: 15px; line-height: 1.6; font-weight: 500; margin-bottom: 0;}

        footer { background: var(--indigo); color: rgba(255,255,255,0.5); padding: 40px 5%; text-align: center; font-size: 14px; margin-top: auto;}

        @media (max-width: 900px) {
            .checkout-container { grid-template-columns: 1fr; gap: 30px;}
            .payment-card { order: -1; min-height: auto;}
        }
        @media (max-width: 600px) {
            .summary-card, .payment-card { padding: 30px 20px;}
            .item-preview { flex-direction: column; text-align: center;}
            .item-img { width: 100%; height: 200px;}
        }
    </style>
</head>
<body>

<nav class="nav-bar">
    <a href="index.php" class="nav-logo"><span class="logo-dot"></span>MILELE</a>
    <a href="index.php" class="btn-glass">← Cancel Order</a>
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

        <div id="formState" style="<?php echo $stk_pushed ? 'display:none;' : 'display:block;'; ?>">
            <?php if(!$error || strpos($error, 'format') !== false): ?>
                <div class="mpesa-header">
                    <div><span class="mpesa-logo">M-PESA</span></div>
                    <div class="mpesa-desc">Enter your phone number. A prompt will appear on your phone to securely authorize the payment to MILELE Escrow.</div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="pay">
                    <div class="input-group">
                        <label class="input-label">M-Pesa Phone Number</label>
                        <input type="tel" name="phone_number" class="input-field" placeholder="07XX XXX XXX" required autocomplete="off">
                    </div>
                    <button type="submit" class="btn-pay" onclick="this.innerHTML='Initiating...'; this.style.opacity='0.8';">Pay KES <?php echo number_format($total_price, 2); ?></button>
                </form>
            <?php endif; ?>
        </div>

        <div id="waitingState" class="status-screen" style="<?php echo $stk_pushed ? 'display:block;' : ''; ?>">
            <div class="spinner"></div>
            <h2 style="color: var(--mpesa-green);">Check Your Phone</h2>
            <p>Please enter your M-Pesa PIN on your mobile device to authorize the transaction.</p>
            <p style="color: var(--indigo); font-size: 13px; font-weight: 700; margin-top: 24px; text-transform: uppercase; letter-spacing: 0.05em;">Waiting for confirmation...</p>
        </div>

        <div id="successState" class="status-screen">
            <div class="check-icon">✓</div>
            <h2>Payment Successful!</h2>
            <p>Funds are securely locked in Escrow.</p>
            <p style="color: var(--amber); font-weight: 800; margin-top: 24px; text-transform: uppercase; letter-spacing: 0.05em; font-size: 13px;">Redirecting to your vault...</p>
        </div>
    </div>

</div>

<footer>
    © 2026 MILELE. Secure Payments via Safaricom Daraja API.
</footer>

<?php if($stk_pushed): ?>
<script>
    // The Real-Time Polling Engine (100% Intact from user prompt)
    const checkInterval = setInterval(() => {
        fetch('checkout.php?action=check_status&id=<?php echo $item_id; ?>')
        .then(response => response.json())
        .then(data => {
            // Once mpesa_callback.php updates the database to 'escrow', this triggers
            if(data.status === 'escrow') {
                clearInterval(checkInterval);
                
                // Swap the UI instantly
                document.getElementById('waitingState').style.display = 'none';
                document.getElementById('successState').style.display = 'block';
                
                // Redirect to dashboard vault after 2.5 seconds to let them read the success message
                setTimeout(() => { 
                    window.location.href = 'profile.php'; 
                }, 2500);
            }
        })
        .catch(error => console.error("Polling error:", error));
    }, 3000); // Checks the database every 3 seconds
</script>
<?php endif; ?>

</body>
</html>