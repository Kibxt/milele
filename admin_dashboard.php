<?php
// MILELE - Executive Admin Control Center

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';
$user_id = $_SESSION['user_id'];

try {
    $stmt_check = $pdo->prepare("SELECT is_admin FROM users WHERE user_id = :id");
    $stmt_check->execute([':id' => $user_id]);
    $admin_check = $stmt_check->fetch();

    if (!$admin_check || $admin_check['is_admin'] != 1) {
        header("Location: index.php");
        exit();
    }

    $stats = [];
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['listings'] = $pdo->query("SELECT COUNT(*) FROM listings WHERE listing_status = 'active'")->fetchColumn();
    $stats['volume'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM escrow_transactions WHERE transaction_status = 'released'")->fetchColumn();
    $stats['vault'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM escrow_transactions WHERE transaction_status = 'funded' OR transaction_status = 'disputed'")->fetchColumn();

    $stmt_ledger = $pdo->query("
        SELECT t.*, l.title, b.full_name as buyer_name, s.full_name as seller_name 
        FROM escrow_transactions t
        JOIN listings l ON t.listing_id = l.listing_id
        JOIN users b ON t.buyer_id = b.user_id
        JOIN users s ON t.seller_id = s.user_id
        ORDER BY t.created_at DESC LIMIT 50
    ");
    $ledger = $stmt_ledger->fetchAll();

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System error loading Admin Control Center.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>God-Mode | MILELE Admin</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; background-image: radial-gradient(circle at 50% 0%, rgba(45, 212, 191, 0.05), transparent 60%); }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 20px;}
        .nav-bar h1 { color: #fff; margin: 0; font-size: 2.2rem; letter-spacing: -1px; display: flex; align-items: center; gap: 10px;}
        .crown-icon { color: #2DD4BF; font-size: 1.8rem; }
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; transition: 0.3s; font-size: 0.9rem; }
        .btn-glass:hover { background: rgba(255,255,255,0.1); }

        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .metric-card { background: rgba(255,255,255,0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.05); padding: 30px 20px; border-radius: 24px; text-align: center; transition: 0.3s; }
        .metric-card:hover { border-color: rgba(45,212,191,0.3); transform: translateY(-3px); }
        .metric-title { color: #888; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; }
        .metric-value { font-size: 2.5rem; font-weight: bold; color: #fff; margin: 0; }
        .highlight-value { color: #2DD4BF; text-shadow: 0 0 20px rgba(45,212,191,0.3); }

        .section-title { font-size: 1.2rem; color: #888; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; }

        .ledger-section { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; padding: 30px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { color: #666; padding-bottom: 15px; font-size: 0.85rem; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.1); }
        td { padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.05); color: #ccc; font-size: 0.95rem; }
        
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase;}
        .status-pending { background: rgba(255, 255, 255, 0.1); color: #ccc; }
        .status-funded { background: rgba(251, 191, 36, 0.1); color: #FBBF24; border: 1px solid rgba(251, 191, 36, 0.2); }
        .status-released { background: rgba(45, 212, 191, 0.1); color: #2DD4BF; }
        .status-failed { background: rgba(248, 113, 113, 0.1); color: #F87171; }
        .status-disputed { background: rgba(239, 68, 68, 0.2); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.4); animation: pulse 2s infinite; }

        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }

        .action-group { display: flex; gap: 8px; }
        .btn-admin-action { padding: 6px 12px; border-radius: 8px; border: none; font-size: 0.8rem; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-refund { background: rgba(248, 113, 113, 0.1); color: #F87171; border: 1px solid rgba(248, 113, 113, 0.2); }
        .btn-refund:hover { background: #F87171; color: #000; }
        .btn-force { background: rgba(45, 212, 191, 0.1); color: #2DD4BF; border: 1px solid rgba(45, 212, 191, 0.2); }
        .btn-force:hover { background: #2DD4BF; color: #000; }
    </style>
</head>
<body>

<div class="container">
    <div class="nav-bar">
        <h1><span class="crown-icon">👑</span> Executive Terminal</h1>
        <a href="profile.php" class="btn-glass">← Back to Profile</a>
    </div>

    <div class="metrics-grid">
        <div class="metric-card"><div class="metric-title">Platform Users</div><div class="metric-value"><?php echo number_format($stats['users']); ?></div></div>
        <div class="metric-card"><div class="metric-title">Active Listings</div><div class="metric-value"><?php echo number_format($stats['listings']); ?></div></div>
        <div class="metric-card"><div class="metric-title">Escrow Vault (Live)</div><div class="metric-value highlight-value"><span style="font-size:1.2rem; color:#888;">KES</span> <?php echo number_format($stats['vault']); ?></div></div>
        <div class="metric-card"><div class="metric-title">Cleared Volume</div><div class="metric-value"><span style="font-size:1.2rem; color:#888;">KES</span> <?php echo number_format($stats['volume']); ?></div></div>
    </div>

    <div class="section-title">Global Transaction Ledger</div>
    
    <div class="ledger-section">
        <?php if (empty($ledger)): ?>
            <p style="color: #666; text-align: center;">No transactions have occurred on the platform yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>TX ID</th>
                        <th>Item</th>
                        <th>Buyer</th>
                        <th>Seller</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Admin Override</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ledger as $tx): ?>
                        <tr>
                            <td style="color: #666; font-family: monospace;">#<?php echo $tx['transaction_id']; ?></td>
                            <td style="color: #fff; font-weight: bold;"><?php echo htmlspecialchars($tx['title']); ?></td>
                            <td><?php echo htmlspecialchars(explode(' ', $tx['buyer_name'])[0]); ?></td>
                            <td><?php echo htmlspecialchars(explode(' ', $tx['seller_name'])[0]); ?></td>
                            <td>KES <?php echo number_format($tx['total_amount'], 2); ?></td>
                            <td>
                                <?php 
                                    $statusClass = 'status-pending';
                                    if ($tx['transaction_status'] == 'funded') $statusClass = 'status-funded';
                                    if ($tx['transaction_status'] == 'released') $statusClass = 'status-released';
                                    if ($tx['transaction_status'] == 'failed') $statusClass = 'status-failed';
                                    if ($tx['transaction_status'] == 'disputed') $statusClass = 'status-disputed';
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($tx['transaction_status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($tx['transaction_status'] === 'funded' || $tx['transaction_status'] === 'disputed'): ?>
                                    <div class="action-group">
                                        <form action="admin_resolve.php" method="POST" onsubmit="return confirm('WARNING: This will reverse the money to the BUYER via Safaricom B2C.');">
                                            <input type="hidden" name="transaction_id" value="<?php echo $tx['transaction_id']; ?>">
                                            <input type="hidden" name="action" value="refund">
                                            <button type="submit" class="btn-admin-action btn-refund">Refund Buyer</button>
                                        </form>

                                        <form action="admin_resolve.php" method="POST" onsubmit="return confirm('WARNING: This will release the money to the SELLER via Safaricom B2C.');">
                                            <input type="hidden" name="transaction_id" value="<?php echo $tx['transaction_id']; ?>">
                                            <input type="hidden" name="action" value="force_pay">
                                            <button type="submit" class="btn-admin-action btn-force">Force Pay</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #444; font-size: 0.8rem;">Locked</span>
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