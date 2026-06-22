<?php
// MILELE - Premium Admin Control Center

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
// 🛠️ AUTO-ELEVATE & SECURITY CHECK
// ==========================================
// Silently ensure the first user (you) is an admin for testing
try { $pdo->exec("UPDATE users SET is_admin = 1 WHERE user_id = 1"); } catch(PDOException $e) {}

// Verify current user is actually an admin
try {
    $stmt = $pdo->prepare("SELECT is_admin, account_state FROM users WHERE user_id = ?");
    $stmt->execute([$my_id]);
    $me = $stmt->fetch();
    
    if (!$me || (empty($me['is_admin']) && $me['account_state'] !== 'admin')) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:sans-serif; font-size:1.5rem;'>🚨 Access Denied. You do not have Administrator privileges.</div>");
    }
} catch (PDOException $e) {
    die("Security Check Error.");
}

// ==========================================
// ⚡ ADMIN ACTIONS (FREEZE / DELETE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Freeze or Unfreeze a User
    if ($_POST['action'] === 'toggle_user') {
        $target_id = filter_input(INPUT_POST, 'target_user', FILTER_VALIDATE_INT);
        $new_state = filter_input(INPUT_POST, 'new_state', FILTER_SANITIZE_SPECIAL_CHARS);
        
        if ($target_id && $target_id != $my_id) { // Prevent freezing yourself
            try {
                $pdo->prepare("UPDATE users SET account_state = ? WHERE user_id = ?")->execute([$new_state, $target_id]);
                $success = "User account state updated to: " . htmlspecialchars($new_state);
            } catch (PDOException $e) { $error = "Action failed."; }
        } else {
            $error = "You cannot alter your own admin account.";
        }
    }
    
    // Delete a Listing
    if ($_POST['action'] === 'delete_listing') {
        $target_listing = filter_input(INPUT_POST, 'target_listing', FILTER_VALIDATE_INT);
        if ($target_listing) {
            try {
                $pdo->prepare("DELETE FROM listings WHERE listing_id = ?")->execute([$target_listing]);
                $success = "Listing permanently deleted.";
            } catch (PDOException $e) { $error = "Failed to delete listing."; }
        }
    }
}

// ==========================================
// 📊 FETCH PLATFORM METRICS
// ==========================================
try {
    // Top Level Stats
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_listings = $pdo->query("SELECT COUNT(*) FROM listings")->fetchColumn();
    $active_escrows = $pdo->query("SELECT COUNT(*) FROM listings WHERE listing_status = 'escrow'")->fetchColumn();
    
    // Revenue Calculation (3% of all 'sold' items)
    $revenue_data = $pdo->query("SELECT SUM(price * 0.03) FROM listings WHERE listing_status = 'sold'")->fetchColumn();
    $total_revenue = $revenue_data ? $revenue_data : 0;

    // Data Tables
    $all_users = $pdo->query("SELECT user_id, full_name, university_name, account_state, completed_escrows, created_at FROM users ORDER BY created_at DESC")->fetchAll();
    
    $all_listings = $pdo->query("
        SELECT l.listing_id, l.title, l.price, l.listing_status, l.created_at, u.full_name as seller_name 
        FROM listings l JOIN users u ON l.seller_id = u.user_id 
        ORDER BY l.created_at DESC
    ")->fetchAll();

    $escrow_tracker = $pdo->query("
        SELECT l.listing_id, l.title, l.price, l.escrow_pin, s.full_name as seller_name, b.full_name as buyer_name 
        FROM listings l 
        JOIN users s ON l.seller_id = s.user_id 
        LEFT JOIN users b ON l.buyer_id = b.user_id 
        WHERE l.listing_status = 'escrow' 
        ORDER BY l.created_at DESC
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
        
        /* Navigation */
        .nav-bar { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(5,5,5,0.9); position: sticky; top: 0; z-index: 100; backdrop-filter: blur(10px);}
        .brand { font-size: 1.8rem; font-weight: 900; color: #8B5CF6; text-decoration: none; letter-spacing: -1px;}
        .admin-badge { background: rgba(139, 92, 246, 0.2); color: #A78BFA; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; border: 1px solid rgba(139, 92, 246, 0.4); margin-left: 10px; vertical-align: middle;}
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; font-weight: bold; transition: 0.3s;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }

        .container { max-width: 1400px; margin: 40px auto; padding: 0 20px; }

        /* Alerts */
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 30px; font-weight: bold;}
        .alert-error { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); }
        .alert-success { background: rgba(52,211,153,0.1); color: #34D399; border: 1px solid rgba(52,211,153,0.3); }

        /* Top KPI Metrics */
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 50px;}
        .metric-card { background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0) 100%); border: 1px solid rgba(255,255,255,0.08); padding: 30px; border-radius: 20px; position: relative; overflow: hidden;}
        .metric-card::before { content:''; position: absolute; top: -50px; right: -50px; width: 100px; height: 100px; background: rgba(139, 92, 246, 0.2); filter: blur(40px); border-radius: 50%;}
        .metric-title { font-size: 0.9rem; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;}
        .metric-value { font-size: 2.5rem; font-weight: bold; color: #fff;}
        .metric-value.revenue { color: #34D399; }
        .metric-value.escrow { color: #F59E0B; }

        /* Data Tables */
        .table-section { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; padding: 30px; margin-bottom: 40px;}
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;}
        .table-title { font-size: 1.5rem; font-weight: bold; margin: 0; color: #fff;}
        
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 15px 10px; color: #888; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.1);}
        td { padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.03); color: #ccc;}
        tr:hover td { background: rgba(255,255,255,0.02); }

        /* Status Badges */
        .status { padding: 5px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; display: inline-block;}
        .s-active { background: rgba(45,212,191,0.1); color: #2DD4BF; border: 1px solid rgba(45,212,191,0.3); }
        .s-escrow { background: rgba(245,158,11,0.1); color: #F59E0B; border: 1px solid rgba(245,158,11,0.3); }
        .s-sold { background: rgba(107,114,128,0.1); color: #9CA3AF; border: 1px solid rgba(107,114,128,0.3); }
        .s-frozen { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); }

        /* Action Buttons */
        .action-form { display: inline; margin: 0;}
        .btn-action { padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: bold; cursor: pointer; border: none; transition: 0.2s;}
        .btn-freeze { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3);}
        .btn-freeze:hover { background: #F87171; color: #000;}
        .btn-unfreeze { background: rgba(52,211,153,0.1); color: #34D399; border: 1px solid rgba(52,211,153,0.3);}
        .btn-unfreeze:hover { background: #34D399; color: #000;}
        .btn-delete { background: transparent; color: #888; border: 1px solid #444;}
        .btn-delete:hover { background: #EF4444; color: #fff; border-color: #EF4444;}

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
            <div class="metric-title">Total Platform Revenue (3% Cut)</div>
            <div class="metric-value revenue">KES <?php echo number_format($total_revenue, 2); ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Active Escrows (Pending Deals)</div>
            <div class="metric-value escrow"><?php echo $active_escrows; ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Total Users</div>
            <div class="metric-value"><?php echo $total_users; ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Total Listings</div>
            <div class="metric-value"><?php echo $total_listings; ?></div>
        </div>
    </div>

    <div class="table-section">
        <div class="table-header">
            <h2 class="table-title">Live Escrow Tracker</h2>
        </div>
        <?php if(empty($escrow_tracker)): ?>
            <div style="color: #666; text-align: center; padding: 20px;">No deals currently locked in escrow.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Item ID</th>
                        <th>Item Title</th>
                        <th>Value</th>
                        <th>Seller</th>
                        <th>Buyer</th>
                        <th>Escrow PIN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($escrow_tracker as $esc): ?>
                    <tr>
                        <td>#<?php echo $esc['listing_id']; ?></td>
                        <td style="color:#fff; font-weight:bold;"><?php echo htmlspecialchars($esc['title']); ?></td>
                        <td style="color:#2DD4BF;">KES <?php echo number_format($esc['price'], 2); ?></td>
                        <td><?php echo htmlspecialchars($esc['seller_name']); ?></td>
                        <td><?php echo htmlspecialchars($esc['buyer_name']); ?></td>
                        <td style="font-family: monospace; color:#F59E0B; letter-spacing: 2px; font-weight:bold;"><?php echo htmlspecialchars($esc['escrow_pin']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="table-section">
        <div class="table-header">
            <h2 class="table-title">User Management</h2>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>University</th>
                    <th>Deals Done</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($all_users as $u): ?>
                <tr>
                    <td>#<?php echo $u['user_id']; ?></td>
                    <td style="color:#fff; font-weight:bold;"><?php echo htmlspecialchars($u['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($u['university_name']); ?></td>
                    <td><?php echo (int)$u['completed_escrows']; ?></td>
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
                                    <button type="submit" class="btn-action btn-freeze" onclick="return confirm('Freeze this user? They will not be able to log in or sell.');">Freeze</button>
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

    <div class="table-section">
        <div class="table-header">
            <h2 class="table-title">Marketplace Content (All Listings)</h2>
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
                        <th>Date Posted</th>
                        <th>Action</th>
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
                            ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($lst['created_at'])); ?></td>
                        <td>
                            <form method="POST" class="action-form">
                                <input type="hidden" name="action" value="delete_listing">
                                <input type="hidden" name="target_listing" value="<?php echo $lst['listing_id']; ?>">
                                <button type="submit" class="btn-action btn-delete" onclick="return confirm('Permanently delete this listing? This cannot be undone.');">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>