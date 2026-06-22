<?php
// MILELE - Ultimate Admin Control Center (Strict Email Security)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';
$my_id = $_SESSION['user_id'];
$error = '';
$success = '';

// ==========================================
// 🛡️ STRICT EMAIL-BASED SECURITY CHECK
// ==========================================
$allowed_admins = [
    'kibeta425@gmail.com', 
    'alvin.kibet@stratmore.edu', 
    'alvin.kibet@strathmore.edu', // Added correct spelling fallback
    'yegonkibe4@gmail.com'
];

try {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ?");
    $stmt->execute([$my_id]);
    $me = $stmt->fetch();
    
    $user_email = $me ? strtolower(trim($me['email'])) : '';

    if (!in_array($user_email, $allowed_admins)) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:-apple-system, sans-serif; font-size:1.5rem; border-top: 5px solid #F87171;'>
            <h2>🚨 Access Denied</h2>
            <p style='font-size: 1rem; color: #888;'>The email account <strong>" . htmlspecialchars($user_email) . "</strong> does not have Administrator privileges.</p>
        </div>");
    }

    // Auto-sync the database is_admin flag for these specific emails to keep the DB clean
    $pdo->prepare("UPDATE users SET is_admin = 1 WHERE email = ?")->execute([$user_email]);

} catch (PDOException $e) {
    die("Security Check Error.");
}

// ==========================================
// ⚡ ADMIN ACTIONS (MODERATION & ESCROW)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        // 1. User Moderation
        if ($action === 'toggle_user') {
            $target_id = filter_input(INPUT_POST, 'target_user', FILTER_VALIDATE_INT);
            $new_state = filter_input(INPUT_POST, 'new_state', FILTER_SANITIZE_SPECIAL_CHARS);
            if ($target_id && $target_id != $my_id) {
                $pdo->prepare("UPDATE users SET account_state = ? WHERE user_id = ?")->execute([$new_state, $target_id]);
                $success = "User account state updated to: " . htmlspecialchars($new_state);
            } else {
                $error = "You cannot alter your own admin account.";
            }
        }
        
        // 2. Listing Moderation (Ban/Unban/Delete)
        if (in_array($action, ['ban_listing', 'unban_listing', 'delete_listing'])) {
            $target_listing = filter_input(INPUT_POST, 'target_listing', FILTER_VALIDATE_INT);
            if ($target_listing) {
                if ($action === 'ban_listing') {
                    $pdo->prepare("UPDATE listings SET listing_status = 'banned' WHERE listing_id = ?")->execute([$target_listing]);
                    $success = "Listing has been BANNED and removed from the public feed.";
                } elseif ($action === 'unban_listing') {
                    $pdo->prepare("UPDATE listings SET listing_status = 'active' WHERE listing_id = ?")->execute([$target_listing]);
                    $success = "Listing has been restored to ACTIVE status.";
                } elseif ($action === 'delete_listing') {
                    $pdo->prepare("DELETE FROM listings WHERE listing_id = ?")->execute([$target_listing]);
                    $success = "Listing permanently deleted from database.";
                }
            }
        }

        // 3. Escrow Enforcement (Force Pay / Refund)
        if (in_array($action, ['force_pay', 'refund_escrow'])) {
            $target_escrow = filter_input(INPUT_POST, 'target_escrow', FILTER_VALIDATE_INT);
            
            if ($target_escrow) {
                if ($action === 'force_pay') {
                    $stmt_seller = $pdo->prepare("SELECT seller_id FROM listings WHERE listing_id = ? AND listing_status = 'escrow'");
                    $stmt_seller->execute([$target_escrow]);
                    $seller_id = $stmt_seller->fetchColumn();

                    if ($seller_id) {
                        $pdo->prepare("UPDATE listings SET listing_status = 'sold' WHERE listing_id = ?")->execute([$target_escrow]);
                        $pdo->prepare("UPDATE users SET completed_escrows = completed_escrows + 1 WHERE user_id = ?")->execute([$seller_id]);
                        $success = "⚖️ FORCE PAY EXECUTED: Funds released to seller and transaction marked as sold.";
                    } else {
                        $error = "Escrow transaction not found or already resolved.";
                    }
                } elseif ($action === 'refund_escrow') {
                    $pdo->prepare("UPDATE listings SET listing_status = 'active', buyer_id = NULL, escrow_pin = NULL WHERE listing_id = ? AND listing_status = 'escrow'")->execute([$target_escrow]);
                    $success = "↩️ REFUND EXECUTED: Transaction cancelled. Item returned to market.";
                }
            }
        }

    } catch (PDOException $e) {
        $error = "Action failed: " . htmlspecialchars($e->getMessage());
    }
}

// ==========================================
// 📊 FETCH ADVANCED PLATFORM METRICS
// ==========================================
try {
    $user_stats = $pdo->query("SELECT COUNT(user_id) as total_users, SUM(CASE WHEN account_state = 'frozen' THEN 1 ELSE 0 END) as frozen_users FROM users")->fetch();

    $list_stats = $pdo->query("
        SELECT 
            COUNT(listing_id) as total_listings,
            SUM(CASE WHEN listing_status = 'active' THEN 1 ELSE 0 END) as active_listings,
            SUM(CASE WHEN listing_status = 'escrow' THEN 1 ELSE 0 END) as active_escrows,
            SUM(CASE WHEN listing_status = 'banned' THEN 1 ELSE 0 END) as banned_listings,
            SUM(CASE WHEN listing_status = 'sold' THEN price ELSE 0 END) as total_volume,
            SUM(CASE WHEN listing_status = 'sold' THEN price * 0.03 ELSE 0 END) as total_revenue
        FROM listings
    ")->fetch();

    $total_volume = $list_stats['total_volume'] ?? 0;
    $total_revenue = $list_stats['total_revenue'] ?? 0;

    $all_users = $pdo->query("SELECT user_id, full_name, email, university_name, account_state, completed_escrows, created_at FROM users ORDER BY created_at DESC")->fetchAll();
    
    $all_listings = $pdo->query("
        SELECT l.listing_id, l.title, l.price, l.listing_status, l.created_at, u.full_name as seller_name 
        FROM listings l JOIN users u ON l.seller_id = u.user_id 
        ORDER BY l.created_at DESC
    ")->fetchAll();

    $escrow_tracker = $pdo->query("
        SELECT l.listing_id, l.title, l.price, l.escrow_pin, l.created_at, s.full_name as seller_name, b.full_name as buyer_name 
        FROM listings l 
        JOIN users s ON l.seller_id = s.user_id 
        LEFT JOIN users b ON l.buyer_id = b.user_id 
        WHERE l.listing_status = 'escrow' 
        ORDER BY l.created_at ASC
    ")->fetchAll();

} catch (PDOException $e) {
    die("Dashboard Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control Center | MILELE</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; min-height: 100vh;}
        .nav-bar { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(5,5,5,0.9); position: sticky; top: 0; z-index: 100; backdrop-filter: blur(10px);}
        .brand { font-size: 1.8rem; font-weight: 900; color: #8B5CF6; text-decoration: none; letter-spacing: -1px;}
        .admin-badge { background: rgba(139, 92, 246, 0.2); color: #A78BFA; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; border: 1px solid rgba(139, 92, 246, 0.4); margin-left: 10px; vertical-align: middle;}
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; font-weight: bold; transition: 0.3s;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }

        .container { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 30px; font-weight: bold;}
        .alert-error { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); }
        .alert-success { background: rgba(52,211,153,0.1); color: #34D399; border: 1px solid rgba(52,211,153,0.3); }

        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 50px;}
        .metric-card { background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0) 100%); border: 1px solid rgba(255,255,255,0.08); padding: 25px; border-radius: 20px; position: relative; overflow: hidden;}
        .metric-title { font-size: 0.8rem; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;}
        .metric-value { font-size: 2rem; font-weight: bold; color: #fff;}
        .metric-value.revenue { color: #34D399; }
        .metric-value.volume { color: #8B5CF6; }
        .metric-value.escrow { color: #F59E0B; }

        .table-section { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; padding: 30px; margin-bottom: 40px;}
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;}
        .table-title { font-size: 1.5rem; font-weight: bold; margin: 0; color: #fff;}
        
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 15px 10px; color: #888; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.1);}
        td { padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.03); color: #ccc;}
        tr:hover td { background: rgba(255,255,255,0.02); }

        .status { padding: 5px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; display: inline-block;}
        .s-active { background: rgba(45,212,191,0.1); color: #2DD4BF; border: 1px solid rgba(45,212,191,0.3); }
        .s-escrow { background: rgba(245,158,11,0.1); color: #F59E0B; border: 1px solid rgba(245,158,11,0.3); }
        .s-sold { background: rgba(107,114,128,0.1); color: #9CA3AF; border: 1px solid rgba(107,114,128,0.3); }
        .s-frozen, .s-banned { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); }

        .action-form { display: inline; margin: 0;}
        .btn-action { padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: bold; cursor: pointer; border: none; transition: 0.2s;}
        
        .btn-freeze, .btn-ban { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3);}
        .btn-freeze:hover, .btn-ban:hover { background: #F87171; color: #000;}
        
        .btn-unfreeze, .btn-unban { background: rgba(107,114,128,0.2); color: #D1D5DB; border: 1px solid rgba(107,114,128,0.4);}
        .btn-unfreeze:hover, .btn-unban:hover { background: #D1D5DB; color: #000;}
        
        .btn-delete { background: transparent; color: #888; border: 1px solid #444;}
        .btn-delete:hover { background: #EF4444; color: #fff; border-color: #EF4444;}

        .btn-force-pay { background: rgba(52,211,153,0.1); color: #34D399; border: 1px solid rgba(52,211,153,0.3);}
        .btn-force-pay:hover { background: #34D399; color: #000;}
        
        .btn-refund { background: rgba(245,158,11,0.1); color: #F59E0B; border: 1px solid rgba(245,158,11,0.3);}
        .btn-refund:hover { background: #F59E0B; color: #000;}
    </style>
</head>
<body>

<nav class="nav-bar">
    <div>
        <a href="index.php" class="brand">MILELE</a>
        <span class="admin-badge">ADMIN</span>
    </div>
    <div class="nav-actions">
        <a href="profile.php" class="btn-glass">← Back to Profile</a>
    </div>
</nav>

<div class="container">
    
    <?php if($error) echo "<div class='alert alert-error'>$error</div>"; ?>
    <?php if($success) echo "<div class='alert alert-success'>$success</div>"; ?>

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-title">Gross Trade Volume</div>
            <div class="metric-value volume">KES <?php echo number_format($total_volume, 2); ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Platform Revenue (3%)</div>
            <div class="metric-value revenue">KES <?php echo number_format($total_revenue, 2); ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Active Escrows</div>
            <div class="metric-value escrow"><?php echo $list_stats['active_escrows']; ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Total Users / Frozen</div>
            <div class="metric-value">
                <?php echo $user_stats['total_users']; ?> 
                <span class="metric-title" style="color:#F87171; font-size:1rem;">/ <?php echo $user_stats['frozen_users']; ?></span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Live / Banned Items</div>
            <div class="metric-value">
                <?php echo $list_stats['active_listings']; ?> 
                <span class="metric-title" style="color:#F87171; font-size:1rem;">/ <?php echo $list_stats['banned_listings']; ?></span>
            </div>
        </div>
    </div>

    <div class="table-section" style="border-color: rgba(245,158,11,0.3); background: radial-gradient(circle at top left, rgba(245,158,11,0.03), transparent 50%);">
        <div class="table-header">
            <h2 class="table-title" style="color: #F59E0B;">⚖️ Escrow Enforcement</h2>
        </div>
        <?php if(empty($escrow_tracker)): ?>
            <div style="color: #666; text-align: center; padding: 20px;">No deals currently locked in escrow.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Item ID</th>
                        <th>Title / Value</th>
                        <th>Seller</th>
                        <th>Buyer</th>
                        <th>Escrow PIN</th>
                        <th>Enforcement Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($escrow_tracker as $esc): ?>
                    <tr>
                        <td>#<?php echo $esc['listing_id']; ?></td>
                        <td>
                            <div style="color:#fff; font-weight:bold;"><?php echo htmlspecialchars($esc['title']); ?></div>
                            <div style="color:#2DD4BF; font-size:0.85rem;">KES <?php echo number_format($esc['price'], 2); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($esc['seller_name']); ?></td>
                        <td><?php echo htmlspecialchars($esc['buyer_name']); ?></td>
                        <td style="font-family: monospace; color:#F59E0B; letter-spacing: 2px; font-weight:bold;"><?php echo htmlspecialchars($esc['escrow_pin']); ?></td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="action" value="force_pay">
                                    <input type="hidden" name="target_escrow" value="<?php echo $esc['listing_id']; ?>">
                                    <button type="submit" class="btn-action btn-force-pay" onclick="return confirm('FORCE PAY: Manually release funds to seller?');">Force Pay</button>
                                </form>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="action" value="refund_escrow">
                                    <input type="hidden" name="target_escrow" value="<?php echo $esc['listing_id']; ?>">
                                    <button type="submit" class="btn-action btn-refund" onclick="return confirm('REFUND: Cancel transaction and return item to market?');">Refund</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="table-section">
        <div class="table-header">
            <h2 class="table-title">Marketplace Moderation</h2>
        </div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Price</th>
                        <th>Seller</th>
                        <th>Status</th>
                        <th>Moderation Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_listings as $lst): ?>
                    <tr>
                        <td>#<?php echo $lst['listing_id']; ?></td>
                        <td style="color:#fff; font-weight:bold;">
                            <a href="item.php?id=<?php echo $lst['listing_id']; ?>" style="color:inherit; text-decoration:none;">
                                <?php echo htmlspecialchars($lst['title']); ?> ↗
                            </a>
                        </td>
                        <td>KES <?php echo number_format($lst['price'], 2); ?></td>
                        <td><?php echo htmlspecialchars($lst['seller_name']); ?></td>
                        <td>
                            <?php 
                                if($lst['listing_status'] === 'active') echo '<span class="status s-active">Active</span>';
                                elseif($lst['listing_status'] === 'escrow') echo '<span class="status s-escrow">In Escrow</span>';
                                elseif($lst['listing_status'] === 'sold') echo '<span class="status s-sold">Sold</span>';
                                elseif($lst['listing_status'] === 'banned') echo '<span class="status s-banned">Banned</span>';
                            ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <?php if($lst['listing_status'] === 'banned'): ?>
                                    <form method="POST" class="action-form">
                                        <input type="hidden" name="action" value="unban_listing">
                                        <input type="hidden" name="target_listing" value="<?php echo $lst['listing_id']; ?>">
                                        <button type="submit" class="btn-action btn-unban">Unban</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" class="action-form">
                                        <input type="hidden" name="action" value="ban_listing">
                                        <input type="hidden" name="target_listing" value="<?php echo $lst['listing_id']; ?>">
                                        <button type="submit" class="btn-action btn-ban" onclick="return confirm('Ban this item? It will be hidden from the public feed.');">Ban</button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="action" value="delete_listing">
                                    <input type="hidden" name="target_listing" value="<?php echo $lst['listing_id']; ?>">
                                    <button type="submit" class="btn-action btn-delete" onclick="return confirm('Permanently wipe this listing?');">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-section">
        <div class="table-header">
            <h2 class="table-title">User Management</h2>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Full Name</th>
                    <th>University</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($all_users as $u): ?>
                <tr>
                    <td style="color:#aaa; font-size: 0.9rem;"><?php echo htmlspecialchars($u['email']); ?></td>
                    <td style="color:#fff; font-weight:bold;"><?php echo htmlspecialchars($u['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($u['university_name']); ?></td>
                    <td>
                        <?php 
                            if($u['account_state'] === 'frozen') echo '<span class="status s-frozen">Frozen</span>';
                            elseif($u['account_state'] === 'admin') echo '<span class="status" style="background:rgba(139,92,246,0.2); color:#A78BFA; border:1px solid rgba(139,92,246,0.4);">Admin</span>';
                            else echo '<span class="status s-active">Active</span>';
                        ?>
                    </td>
                    <td>
                        <?php if($u['user_id'] != $my_id): ?>
                            <form method="POST" class="action-form">
                                <input type="hidden" name="action" value="toggle_user">
                                <input type="hidden" name="target_user" value="<?php echo $u['user_id']; ?>">
                                <?php if($u['account_state'] === 'frozen'): ?>
                                    <input type="hidden" name="new_state" value="active">
                                    <button type="submit" class="btn-action btn-unfreeze">Unfreeze</button>
                                <?php else: ?>
                                    <input type="hidden" name="new_state" value="frozen">
                                    <button type="submit" class="btn-action btn-freeze" onclick="return confirm('Freeze this user?');">Freeze</button>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <span style="color:#666; font-size:0.8rem;">(You)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>