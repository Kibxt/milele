<?php
// MILELE - Private User Dashboard (Strict Email Admin Link + Premium UI)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';
$my_id = $_SESSION['user_id'];
$error = '';
$success = '';

$allowed_admins = [
    'kibeta425@gmail.com', 
    'alvin.kibet@stratmore.edu', 
    'alvin.kibet@strathmore.edu', 
    'yegonkibe4@gmail.com'
];

// ==========================================
// 🔐 ESCROW RELEASE LOGIC
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'release_escrow') {
    $listing_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
    $entered_pin = trim(filter_input(INPUT_POST, 'entered_pin', FILTER_SANITIZE_SPECIAL_CHARS));

    try {
        $stmt = $pdo->prepare("SELECT escrow_pin FROM listings WHERE listing_id = ? AND seller_id = ? AND listing_status = 'escrow'");
        $stmt->execute([$listing_id, $my_id]);
        $real_pin = $stmt->fetchColumn();

        if ($real_pin && $real_pin === $entered_pin) {
            $pdo->prepare("UPDATE listings SET listing_status = 'sold' WHERE listing_id = ?")->execute([$listing_id]);
            $pdo->prepare("UPDATE users SET completed_escrows = completed_escrows + 1 WHERE user_id = ?")->execute([$my_id]);
            $success = "✅ Escrow PIN verified! Funds released and transaction complete.";
        } else {
            $error = "❌ Invalid Escrow PIN for this transaction.";
        }
    } catch(PDOException $e) { $error = "Release Error: " . $e->getMessage(); }
}

// ==========================================
// 📊 FETCH DASHBOARD DATA
// ==========================================
try {
    // 1. Profile Data (Fetch email to verify admin button)
    $stmt = $pdo->prepare("SELECT full_name, email, university_name, profile_picture, completed_escrows, account_state FROM users WHERE user_id = ?");
    $stmt->execute([$my_id]);
    $user = $stmt->fetch();
    
    // Strict verification for the Admin button UI
    $is_admin_user = $user ? in_array(strtolower(trim($user['email'])), $allowed_admins) : false;

} catch (PDOException $e) { $error .= "User Data Error: " . $e->getMessage(); }

// 2. Follower Data
$followers = 0; $following = 0;
try {
    $f_count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ?");
    $f_count->execute([$my_id]);
    $followers = $f_count->fetchColumn();

    $fw_count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
    $fw_count->execute([$my_id]);
    $following = $fw_count->fetchColumn();
} catch (PDOException $e) { }

// 3. Active Inventory
$active_listings = [];
try {
    $stmt_active = $pdo->prepare("SELECT * FROM listings WHERE seller_id = ? AND listing_status = 'active' ORDER BY created_at DESC");
    $stmt_active->execute([$my_id]);
    $active_listings = $stmt_active->fetchAll();
} catch (PDOException $e) { }

// 4. Pending Sales
$pending_sales = [];
try {
    $stmt_sales = $pdo->prepare("SELECT l.*, u.full_name as buyer_name FROM listings l LEFT JOIN users u ON l.buyer_id = u.user_id WHERE l.seller_id = ? AND l.listing_status = 'escrow' ORDER BY l.created_at DESC");
    $stmt_sales->execute([$my_id]);
    $pending_sales = $stmt_sales->fetchAll();
} catch (PDOException $e) { }

// 5. My Purchases
$my_purchases = [];
try {
    $stmt_purchases = $pdo->prepare("SELECT l.*, u.full_name as seller_name FROM listings l LEFT JOIN users u ON l.seller_id = u.user_id WHERE l.buyer_id = ? ORDER BY l.created_at DESC");
    $stmt_purchases->execute([$my_id]);
    $my_purchases = $stmt_purchases->fetchAll();
} catch (PDOException $e) { }

// Helper for Avatar Initials
function get_initials($name) {
    $words = explode(' ', $name);
    if (count($words) >= 2) return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    return strtoupper(substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | MILELE</title>
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
        body { background: var(--chalk); color: var(--indigo); font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }

        /* Navigation */
        nav { background: rgba(247,245,255,0.94); backdrop-filter: blur(14px); border-bottom: 1px solid var(--card-border); position: sticky; top: 0; z-index: 100; padding: 0 5%; display: flex; align-items: center; justify-content: space-between; height: 70px; }
        .nav-logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 24px; color: var(--indigo); text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .logo-dot { width: 10px; height: 10px; background: var(--amber); border-radius: 50%; display: inline-block; }
        .nav-actions { display: flex; gap: 12px; align-items: center; }
        
        .btn-ghost { background: none; border: 1.5px solid var(--indigo); color: var(--indigo); padding: 9px 20px; border-radius: 50px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; text-decoration: none; }
        .btn-ghost:hover { background: var(--indigo); color: var(--white); }
        .btn-danger { background: none; border: 1.5px solid var(--coral); color: var(--coral); padding: 9px 20px; border-radius: 50px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; text-decoration: none; }
        .btn-danger:hover { background: var(--coral); color: var(--white); }
        .btn-admin { background: var(--indigo-mid); color: var(--white); border: none; padding: 9px 20px; border-radius: 50px; font-weight: 700; font-size: 13px; text-decoration: none; transition: 0.2s; box-shadow: 0 4px 12px rgba(45,27,105,0.2);}
        .btn-admin:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(45,27,105,0.3);}

        /* Alerts */
        .alert-container { max-width: 1000px; margin: 30px auto 0; padding: 0 5%; width: 100%;}
        .alert-error { background: rgba(255,107,107,0.1); color: var(--coral); border: 1px solid rgba(255,107,107,0.2); padding: 16px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; text-align: center; font-weight: 600; }
        .alert-success { background: rgba(0,212,170,0.1); color: #059669; border: 1px solid rgba(0,212,170,0.2); padding: 16px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; text-align: center; font-weight: 600; }

        /* Profile Hero Card */
        .profile-hero { max-width: 1000px; margin: 30px auto 40px; padding: 40px 5%; width: 100%; background: var(--white); border: 1px solid var(--card-border); border-radius: 24px; text-align: center; box-shadow: 0 12px 40px rgba(26,16,64,0.04);}
        .avatar-wrapper { position: relative; width: 100px; height: 100px; margin: 0 auto 20px; }
        .big-avatar { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; background: var(--indigo); display: flex; align-items: center; justify-content: center; font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; color: var(--white); box-shadow: 0 8px 24px rgba(26,16,64,0.1);}
        
        .profile-name { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; margin: 0 0 8px 0; color: var(--indigo); display: flex; align-items: center; justify-content: center; gap: 8px;}
        .verified-tick { background: var(--mint); color: var(--indigo); font-size: 10px; font-weight: 800; padding: 4px 10px; border-radius: 50px; letter-spacing: 0.05em; text-transform: uppercase; font-family: 'Inter', sans-serif;}
        .profile-uni { color: var(--slate); font-size: 15px; margin-bottom: 30px; font-weight: 500;}
        
        .trust-stats { display: flex; justify-content: center; gap: 16px; flex-wrap: wrap;}
        .stat-box { background: var(--chalk); border: 1px solid var(--card-border); padding: 16px 24px; border-radius: 16px; min-width: 110px;}
        .stat-num { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; color: var(--indigo); margin-bottom: 4px;}
        .stat-label { font-size: 11px; color: var(--slate); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;}

        /* Dashboard Container */
        .dashboard-container { max-width: 1200px; margin: 0 auto; padding: 0 5% 80px; width: 100%; flex-grow: 1; }
        .section-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--card-border); padding-bottom: 16px; margin-bottom: 30px; margin-top: 50px;}
        .section-title { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; color: var(--indigo); margin: 0;}
        
        /* Direct link specifically to post_item.php */
        .btn-new-item { padding: 10px 24px; background: var(--amber); color: var(--indigo); text-decoration: none; border-radius: 50px; font-weight: 800; font-size: 13px; transition: 0.2s; box-shadow: 0 4px 15px rgba(245,166,35,0.3);}
        .btn-new-item:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(245,166,35,0.45); }

        /* Grids & Cards */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; }
        .card { background: var(--white); border: 1px solid var(--card-border); border-radius: 20px; overflow: hidden; display: flex; flex-direction: column; position: relative; box-shadow: 0 10px 30px rgba(26,16,64,0.03); transition: transform 0.3s;}
        .card:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(26,16,64,0.08);}
        
        .status-badge { position: absolute; top: 16px; left: 16px; font-size: 10px; font-weight: 800; text-transform: uppercase; z-index: 10; padding: 6px 12px; border-radius: 50px; letter-spacing: 0.05em; box-shadow: 0 4px 12px rgba(26,16,64,0.1);}
        .status-active { background: var(--mint); color: var(--indigo); }
        .status-escrow { background: var(--amber); color: var(--indigo); }
        .status-sold { background: var(--slate); color: var(--white); }

        .card-img { width: 100%; height: 220px; object-fit: cover; border-bottom: 1px solid var(--card-border); background: var(--chalk);}
        .card-body { padding: 24px; display: flex; flex-direction: column; flex-grow: 1; }
        .card-title { font-size: 16px; font-weight: 700; color: var(--indigo); margin: 0 0 12px 0; line-height: 1.4;}
        .card-price { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; color: var(--indigo); margin-bottom: 16px;}
        
        .entity-label { font-size: 13px; color: var(--slate); font-weight: 500;}
        .entity-name { color: var(--indigo); font-weight: 700; }

        /* Action Boxes (Escrow & Vault) */
        .escrow-action-box { background: var(--chalk); border: 1.5px dashed var(--amber); border-radius: 16px; padding: 20px; margin-top: 20px;}
        .escrow-action-title { font-size: 12px; color: var(--indigo); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;}
        .escrow-input-group { display: flex; gap: 10px;}
        .escrow-input { flex-grow: 1; height: 44px; background: var(--white); border: 2px solid var(--card-border); color: var(--indigo); padding: 0 15px; border-radius: 12px; font-family: 'Inter', monospace; font-size: 16px; font-weight: 700; text-align: center; outline: none; transition: 0.2s; letter-spacing: 2px;}
        .escrow-input:focus { border-color: var(--amber); box-shadow: 0 0 0 4px rgba(245,166,35,0.12);}
        .btn-release { background: var(--amber); color: var(--indigo); border: none; padding: 0 20px; border-radius: 12px; font-weight: 800; cursor: pointer; transition: 0.2s;}
        .btn-release:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(245,166,35,0.3);}
        
        .escrow-vault { background: var(--white); border: 1.5px solid var(--card-border); border-radius: 16px; padding: 20px; margin-top: 20px;}
        .vault-header { font-size: 11px; color: var(--slate); font-weight: 800; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.05em;}
        .pin-display-group { display: flex; align-items: center; gap: 10px; background: var(--chalk); padding: 10px 16px; border-radius: 12px; border: 1px solid var(--card-border);}
        .pin-text { font-family: 'Syne', monospace; font-size: 20px; font-weight: 800; color: var(--indigo); flex-grow: 1; text-align: center; letter-spacing: 8px;}
        .pin-hidden { filter: blur(6px); user-select: none; opacity: 0.7;}
        .btn-icon { background: var(--white); border: 1px solid var(--card-border); color: var(--indigo); border-radius: 8px; width: 36px; height: 36px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; font-size: 16px;}
        .btn-icon:hover { background: var(--amber); border-color: var(--amber);}

        /* Empty State */
        .empty-state { text-align: center; padding: 60px 20px; background: var(--white); border-radius: 24px; border: 2px dashed var(--card-border); grid-column: 1 / -1; }
        .empty-state-icon { font-size: 40px; margin-bottom: 16px; opacity: 0.8;}
        .empty-state h3 { color: var(--indigo); margin-bottom: 8px; font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800;}
        .empty-state p { color: var(--slate); font-size: 14px; margin-bottom: 0;}

        @media (max-width: 600px) {
            .nav-actions { gap: 8px; }
            .btn-ghost, .btn-danger, .btn-admin { padding: 8px 12px; font-size: 12px; }
            .profile-hero { padding: 30px 20px; border-radius: 0; border-left: none; border-right: none;}
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav>
    <a href="index.php" class="nav-logo"><span class="logo-dot"></span>MILELE</a>
    <div class="nav-actions">
        <?php if($is_admin_user): ?>
            <a href="admin.php" class="btn-admin">Admin Panel</a>
        <?php endif; ?>
        
        <a href="index.php" class="btn-ghost">Market Feed</a>
        <a href="profile.php?action=logout" class="btn-danger">Logout</a>
    </div>
</nav>

<div class="alert-container">
    <?php if($error) echo "<div class='alert-error'>$error</div>"; ?>
    <?php if($success) echo "<div class='alert-success'>$success</div>"; ?>
</div>

<header class="profile-hero">
    <div class="avatar-wrapper">
        <?php if(!empty($user['profile_picture'])): ?>
            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="big-avatar" alt="Profile">
        <?php else: ?>
            <div class="big-avatar"><?php echo get_initials($user['full_name']); ?></div>
        <?php endif; ?>
    </div>

    <h1 class="profile-name">
        <?php echo htmlspecialchars($user['full_name']); ?>
        <?php if($user['account_state'] === 'campus_verified' || $user['is_verified']): ?>
            <span class="verified-tick" title="Verified Campus User">Verified</span>
        <?php endif; ?>
    </h1>
    <div class="profile-uni"><?php echo htmlspecialchars($user['university_name']); ?></div>

    <div class="trust-stats">
        <div class="stat-box">
            <div class="stat-num"><?php echo $followers; ?></div>
            <div class="stat-label">Followers</div>
        </div>
        <div class="stat-box">
            <div class="stat-num"><?php echo $following; ?></div>
            <div class="stat-label">Following</div>
        </div>
        <div class="stat-box" style="background: rgba(0,212,170,0.05); border-color: rgba(0,212,170,0.2);">
            <div class="stat-num" style="color: var(--mint);"><?php echo (int)$user['completed_escrows']; ?></div>
            <div class="stat-label">Deals Done</div>
        </div>
    </div>
</header>

<main class="dashboard-container">
    
    <div class="section-header">
        <h2 class="section-title">My Purchases</h2>
    </div>
    
    <?php if (empty($my_purchases)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🛍️</div>
            <h3>No purchases yet</h3>
            <p>Items you buy through the marketplace will appear here.</p>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($my_purchases as $item): 
                $img = json_decode($item['image_path'], true)[0] ?? $item['image_path'];
                $is_sold = ($item['listing_status'] === 'sold');
            ?>
                <div class="card" style="<?php echo $is_sold ? 'opacity: 0.7;' : ''; ?>">
                    <div class="status-badge <?php echo $is_sold ? 'status-sold' : 'status-active'; ?>">
                        <?php echo $is_sold ? 'Complete' : 'Pending Delivery'; ?>
                    </div>
                    <img src="<?php echo htmlspecialchars($img); ?>" class="card-img" alt="Item">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <div class="entity-label">Seller: <span class="entity-name"><?php echo htmlspecialchars($item['seller_name'] ?? 'System User'); ?></span></div>
                        
                        <?php if(!$is_sold && $item['escrow_pin']): ?>
                            <div class="escrow-vault">
                                <div class="vault-header">Your Delivery PIN</div>
                                <div class="pin-display-group">
                                    <div class="pin-text pin-hidden" id="pin_<?php echo $item['listing_id']; ?>"><?php echo htmlspecialchars($item['escrow_pin']); ?></div>
                                    <button class="btn-icon" onclick="togglePin('pin_<?php echo $item['listing_id']; ?>')" title="Reveal PIN">👁️</button>
                                </div>
                                <div style="font-size: 11px; color: var(--slate); margin-top: 12px; line-height: 1.4;">Give this PIN to the seller only after you have inspected and received the item.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section-header">
        <h2 class="section-title">Pending Escrow Sales</h2>
    </div>

    <?php if (empty($pending_sales)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔒</div>
            <h3>No locked transactions</h3>
            <p>When a buyer purchases your item, the funds are held safely here until delivery.</p>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($pending_sales as $item): 
                $img = json_decode($item['image_path'], true)[0] ?? $item['image_path'];
            ?>
                <div class="card">
                    <div class="status-badge status-escrow">Locked in Escrow</div>
                    <img src="<?php echo htmlspecialchars($img); ?>" class="card-img" alt="Item">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <div class="card-price">KES <?php echo number_format($item['price']); ?></div>
                        <div class="entity-label">Buyer: <span class="entity-name"><?php echo htmlspecialchars($item['buyer_name']); ?></span></div>
                        
                        <form method="POST" class="escrow-action-box">
                            <input type="hidden" name="action" value="release_escrow">
                            <input type="hidden" name="listing_id" value="<?php echo $item['listing_id']; ?>">
                            <div class="escrow-action-title">Verify Delivery</div>
                            <div style="font-size: 13px; color: var(--slate); margin-bottom: 12px;">Enter the 4-digit PIN from the buyer to release your funds.</div>
                            <div class="escrow-input-group">
                                <input type="text" name="entered_pin" class="escrow-input" maxlength="4" placeholder="XXXX" required autocomplete="off">
                                <button type="submit" class="btn-release">Release</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section-header">
        <h2 class="section-title">Active Inventory</h2>
        <a href="post_item.php" class="btn-new-item">+ List New Item</a>
    </div>

    <?php if (empty($active_listings)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📦</div>
            <h3>Your shop is empty</h3>
            <p>You have no active items on the market right now.</p>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($active_listings as $item): 
                $img = json_decode($item['image_path'], true)[0] ?? $item['image_path'];
            ?>
                <div class="card">
                    <div class="status-badge status-active">Live on Market</div>
                    <img src="<?php echo htmlspecialchars($img); ?>" class="card-img" alt="Item">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <div class="card-price">KES <?php echo number_format($item['price']); ?></div>
                        <div class="entity-label" style="margin-top: auto; padding-top: 16px; border-top: 1px solid var(--card-border);">
                            Posted on <?php echo date('M d, Y', strtotime($item['created_at'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
    function togglePin(id) {
        const pinElement = document.getElementById(id);
        if(pinElement.classList.contains('pin-hidden')) {
            pinElement.classList.remove('pin-hidden');
            setTimeout(() => { pinElement.classList.add('pin-hidden'); }, 5000); 
        } else {
            pinElement.classList.add('pin-hidden');
        }
    }
</script>
</body>
</html>