<?php
// frontend/checkout.php
$item_title = "Live Test Item";
$amount = 2; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout - Milele Premium</title>
    <style>
        :root {
            --bg-dark: #000000;
            --glass-bg: rgba(28, 28, 30, 0.65);
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-primary: #f5f5f7;
            --text-secondary: #86868b;
            --accent-blue: #2997ff;
            --accent-glow: rgba(41, 151, 255, 0.4);
        }

        body { 
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; 
            background-color: var(--bg-dark); 
            background-image: radial-gradient(circle at 50% -20%, #1a1a24 0%, var(--bg-dark) 70%);
            color: var(--text-primary);
            display: flex; 
            justify-content: center; 
            align-items: center;
            min-height: 100vh; 
            margin: 0; 
            padding: 20px;
        }

        .dashboard-glass { 
            background: var(--glass-bg); 
            backdrop-filter: saturate(180%) blur(30px); 
            -webkit-backdrop-filter: saturate(180%) blur(30px);
            padding: 48px 40px; 
            border-radius: 28px; 
            border: 1px solid var(--glass-border); 
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255,255,255,0.1); 
            width: 100%; 
            max-width: 440px; 
            text-align: center;
        }

        h2 { margin: 0 0 8px 0; font-size: 28px; font-weight: 600; letter-spacing: -0.5px; }
        p.subtitle { color: var(--text-secondary); font-size: 15px; margin-bottom: 32px; }

        .order-metrics { 
            display: flex; justify-content: space-between; background: rgba(0,0,0,0.3);
            border-radius: 16px; padding: 20px; margin-bottom: 32px; border: 1px solid var(--glass-border);
        }

        .metric { text-align: left; }
        .metric span { display: block; color: var(--text-secondary); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .metric strong { font-size: 16px; font-weight: 500; }

        .verify-box form { display: flex; flex-direction: column; gap: 20px; }
        .verify-box label { display: block; text-align: left; color: var(--text-primary); font-weight: 500; font-size: 14px; margin-bottom: -10px; }
        
        input[type="text"] { 
            width: 100%; background: rgba(0,0,0,0.4); color: #fff; padding: 18px; 
            border: 1px solid var(--glass-border); border-radius: 16px; font-size: 20px; 
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; 
            text-align: center; box-sizing: border-box; outline: none; 
            transition: all 0.3s ease; 
        }
        input[type="text"]:focus { border-color: var(--accent-blue); box-shadow: 0 0 0 4px var(--accent-glow); }

        .btn-verify { 
            width: 100%; background: var(--text-primary); color: var(--bg-dark); border: none; 
            padding: 18px; border-radius: 14px; font-size: 16px; font-weight: 600; cursor: pointer; 
            transition: transform 0.2s, background 0.2s; 
        }
        .btn-verify:hover { background: #ffffff; transform: scale(1.02); }
        .btn-verify:active { transform: scale(0.98); }
    </style>
</head>
<body>

<div class="dashboard-glass">
    <h2>Secure Checkout</h2>
    <p class="subtitle">Escrow Protection Active</p>

    <div class="order-metrics">
        <div class="metric">
            <span>Item</span>
            <strong><?php echo htmlspecialchars($item_title); ?></strong>
        </div>
        <div class="metric" style="text-align: right;">
            <span>Total Due</span>
            <strong>KES <?php echo number_format($amount); ?></strong>
        </div>
    </div>

    <div class="verify-box">
        <form action="../backend/api/process_stk.php" method="POST">
            <label for="phone">M-Pesa Phone Number</label>
            <input type="text" id="phone" name="phone" placeholder="2547..." required autocomplete="off">
            <button type="submit" class="btn-verify">Verify & Pay Now</button>
        </form>
    </div>
</div>

</body>
</html>