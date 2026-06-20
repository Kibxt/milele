<?php
// MILELE - Seller Payout & Escrow Dashboard

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ⚡ THE MASTER CONNECTION
require 'db.php';

$seller_id = $_SESSION['user_id'];

try {
    // Fetch all escrow deals for this seller
    $stmt = $pdo->prepare("
        SELECT t.*, l.title, u.full_name as buyer_name 
        FROM escrow_transactions t
        JOIN listings l ON t.listing_id = l.listing_id
        JOIN users u ON t.buyer_id = u.user_id
        WHERE t.seller_id = :seller
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([':seller' => $seller_id]);
    $deals = $stmt->fetchAll();

    // Calculate Total Pending vs Total Cleared
    $pending_balance = 0;
    $cleared_balance = 0;
    foreach ($deals as $d) {
        if ($d['transaction_status'] === 'funded') $pending_balance += $d['net_payout'];
        if ($d['transaction_status'] === 'released') $cleared_balance += $d['net_payout'];
    }

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:sans-serif;'>System error loading payouts.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payouts | MILELE</title>
    <style>
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .header h1 { margin: 0; font-size: 2rem; color: #fff; }
        .btn-back { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); transition: 0.2s; }
        .btn-back:hover { background: rgba(255,255,255,0.1); }

        /* Balance Cards */
        .balance-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 40px; }
        .card { background: rgba(255,255,255,0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.08); padding: 30px; border-radius: 24px; }
        .card-title { color: #888; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .card-amount { font-size: 2.5rem; font-weight: bold; }
        .text-pending { color: #FBBF24; } /* Amber */
        .text-cleared { color: #2DD4BF; } /* Teal */

        /* Deals Table */
        .deals-section { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; padding: 30px; overflow-x: auto; }
        .deals-section h2 { margin-top: 0; margin-bottom: 20px; font-size: 1.2rem; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { color: #666; padding-bottom: 15px; font-size: 0.85rem; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.1); }
        td { padding: 20px 0; border-bottom: 1px solid rgba(255,255,255,0.05); color: #ccc; }
        
        .status-badge { display: inline-block; padding: 5px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: bold; }
        .badge-funded { background: rgba(251, 191, 36, 0.1); color: #FBBF24; }
        .badge-released { background: rgba(45, 212, 191, 0.1); color: #2DD4BF; }

        .btn-claim { background: #2DD4BF; color: #000; border: none; padding: 8px 16px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.2s; text-decoration: none; font-size: 0.85rem; }
        .btn-claim:hover { background: #fff; }
        .text-muted { color: #666; font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Escrow & Payouts</h1>
        <a href="profile.php" class="btn-back">← Back to Profile</a>
    </div>

    <div class="balance-grid">
        <div class="card">
            <div class="card-title">Pending Escrow (Locked)</div>
            <div class="card-amount text-pending">KES <?php echo number_format($pending_balance, 2); ?></div>
        </div>
        <div class="card">
            <div class="card-title">Cleared for Withdrawal</div>
            <div class="card-amount text-cleared">KES <?php echo number_format($cleared_balance, 2); ?></div>
        </div>
    </div>

    <div class="deals-section">
        <h2>Recent Transactions</h2>
        <?php if (empty($deals)): ?>
            <p style="color: #666; text-align: center; padding: 40px 0;">No transactions yet. Post an item to get started.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Buyer</th>
                        <th>Net Payout</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deals as $deal): ?>
                        <tr>
                            <td style="color: #fff; font-weight: bold;"><?php echo htmlspecialchars($deal['title']); ?></td>
                            <td><?php echo htmlspecialchars(explode(' ', $deal['buyer_name'])[0]); ?></td>
                            <td>KES <?php echo number_format($deal['net_payout'], 2); ?></td>
                            <td>
                                <?php if ($deal['transaction_status'] === 'funded'): ?>
                                    <span class="status-badge badge-funded">Locked</span>
                                <?php else: ?>
                                    <span class="status-badge badge-released">Cleared</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($deal['transaction_status'] === 'funded'): ?>
                                    <a href="#" class="btn-claim">Claim Funds</a>
                                <?php else: ?>
                                    <span class="text-muted">Added to Balance</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>