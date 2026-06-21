<?php
// MILELE - Seller Payout & Earnings Dashboard

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];

try {
    // 1. Fetch Active Escrows (Waiting for PIN)
    $stmt_pending = $pdo->prepare("
        SELECT t.*, l.title, b.full_name as buyer_name 
        FROM escrow_transactions t
        JOIN listings l ON t.listing_id = l.listing_id
        JOIN users b ON t.buyer_id = b.user_id
        WHERE t.seller_id = :id AND t.transaction_status = 'funded'
        ORDER BY t.created_at DESC
    ");
    $stmt_pending->execute([':id' => $seller_id]);
    $pending_deals = $stmt_pending->fetchAll();

    // 2. Fetch Completed Deals (Money sent to M-Pesa)
    $stmt_cleared = $pdo->prepare("
        SELECT t.*, l.title, b.full_name as buyer_name 
        FROM escrow_transactions t
        JOIN listings l ON t.listing_id = l.listing_id
        JOIN users b ON t.buyer_id = b.user_id
        WHERE t.seller_id = :id AND t.transaction_status = 'released'
        ORDER BY t.created_at DESC
    ");
    $stmt_cleared->execute([':id' => $seller_id]);
    $cleared_deals = $stmt_cleared->fetchAll();

    // 3. Calculate Total Earnings
    $total_earned = 0;
    foreach ($cleared_deals as $deal) {
        // Safe fallback in case the seller_payout column is newly added
        $payout = isset($deal['seller_payout']) && $deal['seller_payout'] > 0 ? $deal['seller_payout'] : $deal['total_amount'];
        $total_earned += $payout;
    }

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System error loading payout data.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sales | MILELE</title>
    <style>
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .nav-bar h1 { color: #fff; margin: 0; font-size: 2rem; }
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; transition: 0.3s; font-size: 0.9rem; }
        .btn-glass:hover { background: rgba(255,255,255,0.1); }

        .earnings-card { background: rgba(45,212,191,0.05); border: 1px solid rgba(45,212,191,0.2); padding: 40px; border-radius: 24px; text-align: center; margin-bottom: 50px; }
        .earnings-title { color: #2DD4BF; font-size: 1rem; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; font-weight: bold;}
        .earnings-amount { font-size: 4rem; font-weight: bold; margin: 0; color: #fff; line-height: 1;}
        
        .section-title { font-size: 1.2rem; color: #888; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
        
        /* Premium Table */
        .table-section { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; padding: 30px; overflow-x: auto; margin-bottom: 40px;}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { color: #666; padding-bottom: 15px; font-size: 0.85rem; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.1); }
        td { padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.05); color: #ccc; }
        
        .status-locked { color: #FBBF24; font-weight: bold; background: rgba(251, 191, 36, 0.1); padding: 4px 10px; border-radius: 8px; font-size: 0.8rem;}
        .status-cleared { color: #2DD4BF; font-weight: bold; background: rgba(45, 212, 191, 0.1); padding: 4px 10px; border-radius: 8px; font-size: 0.8rem;}
        
        .pin-form { display: flex; gap: 10px; }
        .pin-input { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 8px 12px; border-radius: 8px; width: 80px; text-align: center; font-family: monospace; font-size: 1rem; outline: none;}
        .pin-input:focus { border-color: #2DD4BF; }
        .btn-claim { background: #2DD4BF; color: #000; border: none; padding: 8px 16px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.2s;}
        .btn-claim:hover { background: #fff; }

        .fee-badge { color: #888; font-size: 0.8rem; }
    </style>
</head>
<body>

<div class="container">
    <div class="nav-bar">
        <h1>My Sales</h1>
        <a href="profile.php" class="btn-glass">← Back to Profile</a>
    </div>

    <?php if (isset($_SESSION['payout_error'])): ?>
        <div style="background: rgba(248,113,113,0.1); color: #F87171; padding: 15px; border-radius: 12px; margin-bottom: 30px; text-align: center; border: 1px solid rgba(248,113,113,0.3);">
            <?php echo htmlspecialchars($_SESSION['payout_error']); unset($_SESSION['payout_error']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['payout_success'])): ?>
        <div style="background: rgba(45,212,191,0.1); color: #2DD4BF; padding: 15px; border-radius: 12px; margin-bottom: 30px; text-align: center; border: 1px solid rgba(45,212,191,0.3);">
            <?php echo htmlspecialchars($_SESSION['payout_success']); unset($_SESSION['payout_success']); ?>
        </div>
    <?php endif; ?>

    <div class="earnings-card">
        <div class="earnings-title">Total Lifetime Earnings</div>
        <div class="earnings-amount"><span style="font-size: 2rem; color: #2DD4BF;">KES</span> <?php echo number_format($total_earned, 2); ?></div>
    </div>

    <div class="section-title">🔒 Awaiting Handover PIN</div>
    <div class="table-section">
        <?php if (empty($pending_deals)): ?>
            <p style="color: #666; text-align: center; margin: 0;">No funds are currently locked in escrow.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Buyer</th>
                        <th>Selling Price</th>
                        <th>Status</th>
                        <th>Claim Funds</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_deals as $deal): ?>
                        <tr>
                            <td style="color: #fff; font-weight: bold;"><?php echo htmlspecialchars($deal['title']); ?></td>
                            <td><?php echo htmlspecialchars(explode(' ', $deal['buyer_name'])[0]); ?></td>
                            <td>KES <?php echo number_format($deal['total_amount'], 2); ?></td>
                            <td><span class="status-locked">Vault Locked</span></td>
                            <td>
                                <form action="process_payout.php" method="POST" class="pin-form">
                                    <input type="hidden" name="transaction_id" value="<?php echo $deal['transaction_id']; ?>">
                                    <input type="text" name="escrow_pin" class="pin-input" placeholder="PIN" maxlength="4" required autocomplete="off">
                                    <button type="submit" class="btn-claim">Release M-Pesa</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="section-title" style="margin-top: 60px;">✅ Cleared Transactions</div>
    <div class="table-section">
        <?php if (empty($cleared_deals)): ?>
            <p style="color: #666; text-align: center; margin: 0;">You haven't completed any sales yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Buyer</th>
                        <th>Final Payout</th>
                        <th>Platform Fee</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cleared_deals as $deal): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($deal['created_at'])); ?></td>
                            <td style="color: #fff; font-weight: bold;"><?php echo htmlspecialchars($deal['title']); ?></td>
                            <td><?php echo htmlspecialchars(explode(' ', $deal['buyer_name'])[0]); ?></td>
                            
                            <?php 
                                $payout = isset($deal['seller_payout']) && $deal['seller_payout'] > 0 ? $deal['seller_payout'] : $deal['total_amount'];
                                $fee = isset($deal['platform_fee']) ? $deal['platform_fee'] : 0;
                            ?>
                            
                            <td style="color: #2DD4BF; font-weight: bold;">KES <?php echo number_format($payout, 2); ?></td>
                            <td><span class="fee-badge">KES <?php echo number_format($fee, 2); ?></span></td>
                            <td><span class="status-cleared">M-Pesa Sent</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>