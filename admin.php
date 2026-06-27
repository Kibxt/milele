<?php
// MILELE - Ultimate Admin Control Center (Strict Email Security + Premium UI)

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
        die("<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Access Denied</title><link href='https://fonts.googleapis.com/css2?family=Syne:wght@800&family=Inter:wght@500&display=swap' rel='stylesheet'></head><body style='background:#F7F5FF; display:flex; justify-content:center; align-items:center; height:100vh; margin:0;'><div style='background:#ffffff; border:1px solid rgba(26,16,64,0.1); padding:50px; text-align:center; border-radius:24px; box-shadow:0 20px 40px rgba(26,16,64,0.05); max-width:400px;'><h2 style='font-family:Syne, sans-serif; font-size:28px; color:#FF6B6B; margin:0 0 10px 0;'>🚨 Access Denied</h2><p style='font-family:Inter, sans-serif; font-size:15px; color:#8B7FA8; margin-bottom:20px; line-height:1.6;'>The email account <strong>" . htmlspecialchars($user_email) . "</strong> does not have Administrator privileges.</p><a href='profile.php' style='display:inline-block; background:#1A1040; color:#fff; padding:12px 24px; border-radius:50px; text-decoration:none; font-family:Inter, sans-serif; font-weight:700;'>Return to Profile</a></div></body></html>");
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
    <title>Control Center | MILELE</title>
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
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { background: var(--chalk); color: var(--indigo); font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column;}
        
        /* Navigation */
        nav { background: rgba(247,245,255,0.94); backdrop-filter: blur(14px); border-bottom: 1px solid var(--card-border); position: sticky; top: 0; z-index: 100; padding: 0 5%; display: flex; align-items: center; justify-content: space-between; height: 70px; }
        .nav-logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 24px; color: var(--indigo); text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .logo-dot { width: 10px; height: 10px; background: var(--amber); border-radius: 50%; display: inline-block; }
        .admin-badge { background: var(--indigo); color: var(--amber); padding: 4px 12px; border-radius: 50px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-left: 10px; }
        
        .nav-actions { display: flex; gap: 12px; align-items: center; }
        .btn-ghost { background: none; border: 1.5px solid var(--indigo); color: var(--indigo); padding: 9px 20px; border-radius: 50px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; text-decoration: none; }
        .btn-ghost:hover { background: var(--indigo); color: var(--white); }

        .container { max-width: 1400px; margin: 40px auto; padding: 0 20px; flex-grow: 1; width: 100%;}
        
        /* Alerts */
        .alert-error { background: rgba(255,107,107,0.1); color: var(--coral); border: 1px solid rgba(255,107,107,0.2); padding: 16px; border-radius: 12px; margin-bottom: 30px; font-size: 14px; text-align: center; font-weight: 600; }
        .alert-success { background: rgba(0,212,170,0.1); color: #059669; border: 1px solid rgba(0,212,170,0.2); padding: 16px; border-radius: 12px; margin-bottom: 30px; font-size: 14px; text-align: center; font-weight: 600; }

        /* Metrics Grid */
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px;}
        .metric-card { background: var(--white); border: 1px solid var(--card-border); padding: 24px; border-radius: 20px; box-shadow: 0 10px 30px rgba(26,16,64,0.03); transition: transform 0.2s;}
        .metric-card:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(26,16,64,0.08); }
        .metric-title { font-size: 11px; color: var(--slate); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;}
        .metric-value { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; color: var(--indigo);}
        .metric-value.revenue { color: #059669; }
        .metric-value.volume { color: var(--amber); }

        /* Table Sections */
        .table-section { background: var(--white); border: 1px solid var(--card-border); border-radius: 24px; padding: 32px; margin-bottom: 40px; box-shadow: 0 12px 40px rgba(26,16,64,0.04); overflow-x: auto;}
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 2px solid var(--card-border); padding-bottom: 16px;}
        .table-title { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; margin: 0; color: var(--indigo);}
        
        table { width: 100%; border-collapse: collapse; text-align: left; min-width: 800px; }
        th { padding: 16px 12px; color: var(--slate); font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--card-border);}
        td { padding: 16px 12px; border-bottom: 1px solid var(--card-border); color: var(--indigo); font-size: 14px;}
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--chalk); }

        /* Status Badges */
        .status { padding: 6px 12px; border-radius: 50px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block;}
        .s-active { background: var(--mint); color: var(--indigo); }
        .s-escrow { background: var(--amber); color: var(--indigo); }
        .s-sold { background: var(--slate); color: var(--white); }
        .s-frozen, .s-banned { background: rgba(255,107,107,0.1); color: var(--coral); }
        .s-admin { background: var(--indigo); color: var(--amber); }

        /* Form Actions & Buttons */
        .action-form { display: inline-flex; margin: 0;}
        .btn-action { padding: 8px 16px; border-radius: 50px; font-size: 12px; font-weight: 700; cursor: pointer; border: none; transition: 0.2s; font-family: 'Inter', sans-serif;}
        
        /* Dangerous / Banning actions */
        .btn-freeze, .btn-ban { background: rgba(255,107,107,0.1); color: var(--coral); }
        .btn-freeze:hover, .btn-ban:hover { background: var(--coral); color: var(--white); }
        .btn-delete { background: transparent; color: var(--slate); border: 1.5px solid var(--slate); }
        .btn-delete:hover { border-color: var(--coral); color: var(--coral); }
        
        /* Restoring actions */
        .btn-unfreeze, .btn-unban { background: var(--chalk); color: var(--indigo); border: 1.5px solid var(--card-border); }
        .btn-unfreeze:hover, .btn-unban:hover { background: var(--indigo); color: var(--white); border-color: var(--indigo);}
        
        /* Escrow Enforcement actions */
        .btn-force-pay { background: var(--mint); color: var(--indigo); box-shadow: 0 4px 10px rgba(0,212,170,0.2);}
        .btn-force-pay:hover { transform: translateY(-1px); box-shadow: 0 6px 15px rgba(0,212,170,0.4); }
        .btn-refund { background: var(--amber); color: var(--indigo); box-shadow: 0 4px 10px rgba(245,166,35,0.2);}
        .btn-refund:hover { transform: translateY(-1px); box-shadow: 0 6px 15px rgba(245,166,35,0.4); }

        /* Highlighted Escrow Table */
        .escrow-highlight { border: 2px solid var(--amber); position: relative; }
        .escrow-highlight::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(245,166,35,0.02); pointer-events: none; }
        .pin-code { font-family: 'Syne', monospace; font-size: 16px; font-weight: 800; color: var(--amber); letter-spacing: 2px;}

        footer { background: var(--indigo); color: rgba(255,255,255,0.5); padding: 40px 5%; text-align: center; font-size: 14px; margin-top: 40px;}
    </style>
</head>
<body>

<nav>
    <div style="display:flex; align-items:center;">
        <a href="index.php" class="nav-logo"><span class="logo-dot"></span>MILELE</a>
        <span class="admin-badge">Control Center</span>
    </div>
    <div class="nav-actions">
        <a href="profile.php" class="btn-ghost">← Back to Profile</a>
    </div>
</nav>

<div class="container">
    
    <?php if($error) echo "<div class='alert-error'>$error</div>"; ?>
    <?php if($success) echo "<div class='alert-success'>$success</div>"; ?>

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
            <div class="metric-value"><?php echo $list_stats['active_escrows']; ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Total Users / Frozen</div>
            <div class="metric-value">
                <?php echo $user_stats['total_users']; ?> 
                <span style="color:var(--coral); font-size:16px;">/ <?php echo $user_stats['frozen_users']; ?></span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Live / Banned Items</div>
            <div class="metric-value">
                <?php echo $list_stats['active_listings']; ?> 
                <span style="color:var(--coral); font-size:16px;">/ <?php echo $list_stats['banned_listings']; ?></span>
            </div>
        </div>
    </div>

    <div class="table-section escrow-highlight">
        <div class="table-header" style="border-bottom-color: rgba(245,166,35,0.2);">
            <h2 class="table-title" style="color: var(--amber);">⚖️ Escrow Enforcement</h2>
        </div>
        <?php if(empty($escrow_tracker)): ?>
            <div style="color: var(--slate); text-align: center; padding: 20px; font-weight: 500;">No deals currently locked in escrow.</div>
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
                        <td style="font-weight: 600; color: var(--slate);">#<?php echo $esc['listing_id']; ?></td>
                        <td>
                            <div style="font-weight:700; margin-bottom:4px;"><?php echo htmlspecialchars($esc['title']); ?></div>
                            <div style="font-weight:600; font-size:13px; color:var(--slate);">KES <?php echo number_format($esc['price'], 2); ?></div>
                        </td>
                        <td style="font-weight: 500;"><?php echo htmlspecialchars($esc['seller_name']); ?></td>
                        <td style="font-weight: 500;"><?php echo htmlspecialchars($esc['buyer_name']); ?></td>
                        <td class="pin-code"><?php echo htmlspecialchars($esc['escrow_pin']); ?></td>
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
                    <td style="font-weight: 600; color: var(--slate);">#<?php echo $lst['listing_id']; ?></td>
                    <td style="font-weight:700;">
                        <a href="checkout.php?id=<?php echo $lst['listing_id']; ?>" style="color:inherit; text-decoration:none;">
                            <?php echo htmlspecialchars($lst['title']); ?> ↗
                        </a>
                    </td>
                    <td style="font-weight: 500;">KES <?php echo number_format($lst['price'], 2); ?></td>
                    <td style="font-weight: 500;"><?php echo htmlspecialchars($lst['seller_name']); ?></td>
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
                                <button type="submit" class="btn-action btn-delete" onclick="return confirm('Permanently wipe this listing from the database?');">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
                    <td style="color:var(--slate); font-weight: 500;"><?php echo htmlspecialchars($u['email']); ?></td>
                    <td style="font-weight:700;"><?php echo htmlspecialchars($u['full_name']); ?></td>
                    <td style="font-weight: 500;"><?php echo htmlspecialchars($u['university_name']); ?></td>
                    <td>
                        <?php 
                            if($u['account_state'] === 'frozen') echo '<span class="status s-frozen">Frozen</span>';
                            elseif($u['account_state'] === 'admin' || in_array(strtolower(trim($u['email'])), $allowed_admins)) echo '<span class="status s-admin">Admin</span>';
                            else echo '<span class="status s-active">Active</span>';
                        ?>
                    </td>
                    <td>
                        <?php if($u['user_id'] != $my_id && !in_array(strtolower(trim($u['email'])), $allowed_admins)): ?>
                            <form method="POST" class="action-form">
                                <input type="hidden" name="action" value="toggle_user">
                                <input type="hidden" name="target_user" value="<?php echo $u['user_id']; ?>">
                                <?php if($u['account_state'] === 'frozen'): ?>
                                    <input type="hidden" name="new_state" value="active">
                                    <button type="submit" class="btn-action btn-unfreeze">Unfreeze</button>
                                <?php else: ?>
                                    <input type="hidden" name="new_state" value="frozen">
                                    <button type="submit" class="btn-action btn-freeze" onclick="return confirm('Freeze this user? They will not be able to trade.');">Freeze</button>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <span style="color:var(--slate); font-size:12px; font-weight: 600;">(Protected)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<footer>
    © 2026 MILELE Platform Control.
</footer>

</body>
</html>