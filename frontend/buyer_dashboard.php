<?php
// frontend/buyer_dashboard.php

$host = 'localhost';
$db   = 'milele_escrow';
$user = 'root';
$pass = '';

$order = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    // Preserving the MySQL Bypass logic
    $stmt = $pdo->query("SELECT * FROM escrow_transactions WHERE status = 'funded' ORDER BY escrow_id DESC LIMIT 1");
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silently handle in production
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Dashboard - Milele Premium</title>
    <style>
        :root {
            --bg-dark: #000000;
            --glass-bg: rgba(28, 28, 30, 0.65);
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-primary: #f5f5f7;
            --text-secondary: #86868b;
            --accent-blue: #2997ff;
            --success-green: #30d158;
            --success-glow: rgba(48, 209, 88, 0.2);
            --warning-red: #ff3b30;
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
            max-width: 460px; 
            text-align: center;
        }

        h2 { margin: 0 0 8px 0; font-size: 28px; font-weight: 600; letter-spacing: -0.5px; }
        p.subtitle { color: var(--text-secondary); font-size: 15px; margin-bottom: 32px; }

        .status-badge { 
            display: inline-flex; align-items: center; background: rgba(48, 209, 88, 0.1); 
            color: var(--success-green); padding: 8px 16px; border-radius: 30px; font-size: 13px; 
            font-weight: 600; margin-bottom: 32px; text-transform: uppercase; letter-spacing: 1px;
            box-shadow: 0 0 20px var(--success-glow); border: 1px solid rgba(48, 209, 88, 0.2);
        }

        .status-badge::before {
            content: ''; display: inline-block; width: 8px; height: 8px; background: var(--success-green);
            border-radius: 50%; margin-right: 8px; box-shadow: 0 0 8px var(--success-green);
        }
        
        .order-metrics { 
            display: flex; justify-content: space-between; background: rgba(0,0,0,0.3);
            border-radius: 16px; padding: 20px; margin-bottom: 24px; border: 1px solid var(--glass-border);
        }

        .metric { text-align: left; }
        .metric span { display: block; color: var(--text-secondary); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .metric strong { font-size: 16px; font-weight: 500; }

        .pin-box { background: rgba(0,0,0,0.4); border: 1px dashed var(--glass-border); padding: 30px 20px; border-radius: 16px; margin-top: 10px; }
        .pin-box h3 { margin: 0 0 10px 0; color: var(--text-primary); font-size: 16px; font-weight: 600; }
        .pin-box p { color: var(--text-secondary); font-size: 14px; line-height: 1.5; margin: 0 0 20px 0; }
        
        .pin-number { 
            font-size: 42px; font-weight: 700; letter-spacing: 16px; color: var(--accent-blue); 
            margin: 20px 0; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; 
            text-shadow: 0 0 20px rgba(41, 151, 255, 0.3);
            margin-right: -16px; /* Offset the extra letter spacing on the last digit to keep it centered */
        }
        
        .warning-text { color: var(--warning-red); font-size: 13px; font-weight: 500; display: block; margin-top: 20px; background: rgba(255, 59, 48, 0.1); padding: 10px; border-radius: 8px; border: 1px solid rgba(255, 59, 48, 0.2);}
        
        .empty-state { color: var(--text-secondary); padding: 40px 0; font-size: 15px; }
    </style>
</head>
<body>

<div class="dashboard-glass">
    <h2>Buyer Terminal</h2>
    <p class="subtitle">Active Escrow Contracts</p>

    <?php if ($order): ?>
        
        <div class="status-badge">Protected & Funded</div>
        
        <div class="order-metrics">
            <div class="metric">
                <span>Transaction ID</span>
                <strong><?php echo htmlspecialchars($order['escrow_id']); ?></strong>
            </div>
            <div class="metric" style="text-align: right;">
                <span>Amount Locked</span>
                <strong>KES <?php echo number_format($order['amount']); ?></strong>
            </div>
        </div>

        <div class="pin-box">
            <h3>Secure Meetup Code</h3>
            <p>Inspect your item. If satisfied, provide this code to the seller to finalize the payout.</p>
            
            <div class="pin-number">
                <?php echo htmlspecialchars($order['secret_pin']); ?>
            </div>
            
            <span class="warning-text">Do NOT share this code before receiving the item.</span>
        </div>

    <?php else: ?>
        <div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.5; margin-bottom: 16px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
            </svg>
            <p>You have no actively funded orders right now.</p>
        </div>
    <?php endif; ?>

</div>

</body>
</html>