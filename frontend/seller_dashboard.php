<?php
// frontend/seller_dashboard.php

// Connect to the database
$host = 'localhost';
$db   = 'milele_escrow';
$user = 'root';
$pass = '';

$order = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    
    // THE BYPASS: Sorting by escrow_id instead of id to prevent MySQL Error 1075
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
    <title>Seller Escrow - Milele Premium</title>
    <style>
        :root {
            --bg-dark: #000000;
            --glass-bg: rgba(28, 28, 30, 0.65);
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-primary: #f5f5f7;
            --text-secondary: #86868b;
            --accent-blue: #2997ff;
            --accent-glow: rgba(41, 151, 255, 0.4);
            --success-green: #30d158;
            --success-glow: rgba(48, 209, 88, 0.2);
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
            border-radius: 16px; padding: 20px; margin-bottom: 32px; border: 1px solid var(--glass-border);
        }

        .metric { text-align: left; }
        .metric span { display: block; color: var(--text-secondary); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .metric strong { font-size: 18px; font-weight: 500; }

        .verify-box label { display: block; margin-bottom: 16px; color: var(--text-primary); font-weight: 500; font-size: 14px; }
        input[type="text"] { 
            width: 100%; background: rgba(0,0,0,0.4); color: #fff; padding: 18px; 
            border: 1px solid var(--glass-border); border-radius: 16px; font-size: 32px; 
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; 
            letter-spacing: 14px; text-align: center; box-sizing: border-box; outline: none; 
            margin-bottom: 24px; transition: all 0.3s ease; 
        }
        input[type="text"]:focus { border-color: var(--accent-blue); box-shadow: 0 0 0 4px var(--accent-glow); }

        .btn-verify { 
            width: 100%; background: var(--text-primary); color: var(--bg-dark); border: none; 
            padding: 18px; border-radius: 14px; font-size: 16px; font-weight: 600; cursor: pointer; 
            transition: transform 0.2s, background 0.2s; 
        }
        .btn-verify:hover { background: #ffffff; transform: scale(1.02); }
        .btn-verify:active { transform: scale(0.98); }
        
        #response-msg { margin-top: 20px; font-size: 14px; display: none; padding: 14px; border-radius: 12px; font-weight: 500; backdrop-filter: blur(10px); }
        .empty-state { color: var(--text-secondary); padding: 40px 0; font-size: 15px; }
    </style>
</head>
<body>

<div class="dashboard-glass">
    <h2>Vault Authorization</h2>
    <p class="subtitle">Secure Escrow Terminal</p>

    <?php if ($order): ?>
        
        <div class="status-badge">Funds Locked & Secured</div>
        
        <div class="order-metrics">
            <div class="metric">
                <span>Transaction ID</span>
                <strong><?php echo htmlspecialchars($order['escrow_id']); ?></strong>
            </div>
            <div class="metric" style="text-align: right;">
                <span>Payout Amount</span>
                <strong>KES <?php echo number_format($order['amount']); ?></strong>
            </div>
        </div>

        <div class="verify-box">
            <label>Enter 6-Digit Buyer Verification Code</label>
            <input type="text" id="meetup_code" maxlength="6" placeholder="••••••" autocomplete="off">
            <button class="btn-verify" onclick="verifyHandshake('<?php echo htmlspecialchars($order['escrow_id']); ?>')">Release Funds to M-Pesa</button>
        </div>

        <div id="response-msg"></div>

    <?php else: ?>
        <div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.5; margin-bottom: 16px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <p>No active escrows awaiting verification.</p>
        </div>
    <?php endif; ?>

</div>

<script>
function verifyHandshake(escrowId) {
    const pin = document.getElementById('meetup_code').value.trim();
    const msgBox = document.getElementById('response-msg');
    const btn = document.querySelector('.btn-verify');

    if (pin.length !== 6) {
        msgBox.style.display = 'block';
        msgBox.style.background = 'rgba(255, 59, 48, 0.1)';
        msgBox.style.color = '#ff3b30';
        msgBox.style.border = '1px solid rgba(255, 59, 48, 0.2)';
        msgBox.innerText = 'Authentication Failed: Invalid 6-digit code format.';
        return;
    }

    // UI Loading State
    btn.innerText = 'Authenticating...';
    btn.style.opacity = '0.7';
    btn.style.pointerEvents = 'none';
    
    msgBox.style.display = 'block';
    msgBox.style.background = 'rgba(41, 151, 255, 0.1)';
    msgBox.style.color = 'var(--accent-blue)';
    msgBox.style.border = '1px solid rgba(41, 151, 255, 0.2)';
    msgBox.innerText = 'Connecting to Safaricom Gateway...';

    // Fire the async request to the verify_handshake backend
    fetch('../backend/api/verify_handshake.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `escrow_id=${encodeURIComponent(escrowId)}&pin=${encodeURIComponent(pin)}`
    })
    .then(response => response.json())
    .then(data => {
        btn.innerText = 'Release Funds to M-Pesa';
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';

        if (data.status === 'success') {
            msgBox.style.background = 'rgba(48, 209, 88, 0.1)';
            msgBox.style.color = 'var(--success-green)';
            msgBox.style.border = '1px solid rgba(48, 209, 88, 0.2)';
            msgBox.innerHTML = '<strong>Handshake Verified!</strong> Payout dispatched to your M-Pesa.';
            
            // Reload after success to show the empty vault state
            setTimeout(() => window.location.reload(), 2500);
        } else {
            msgBox.style.background = 'rgba(255, 59, 48, 0.1)';
            msgBox.style.color = '#ff3b30';
            msgBox.style.border = '1px solid rgba(255, 59, 48, 0.2)';
            msgBox.innerText = 'Error: ' + data.message;
        }
    })
    .catch(error => {
        btn.innerText = 'Release Funds to M-Pesa';
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';

        msgBox.style.background = 'rgba(255, 59, 48, 0.1)';
        msgBox.style.color = '#ff3b30';
        msgBox.style.border = '1px solid rgba(255, 59, 48, 0.2)';
        msgBox.innerText = 'Network disruption. Secure connection lost.';
    });
}
</script>

</body>
</html>