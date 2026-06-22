<?php
// MILELE - Bulletproof Dashboard & Escrow Simulator

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 🛑 SECURE LOGOUT ROUTER
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

// ==========================================
// 🛠️ SILENT DATABASE UPGRADES
// ==========================================
try { $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE listings ADD COLUMN buyer_id INT DEFAULT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE listings ADD COLUMN escrow_pin VARCHAR(10) DEFAULT NULL"); } catch (PDOException $e) {}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS follows (
        follower_id INT NOT NULL, followed_id INT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (follower_id, followed_id)
    )");
} catch (PDOException $e) {}

// ==========================================
// 🧪 DEVELOPER ESCROW SIMULATOR 
// ==========================================
if (isset($_GET['simulate']) && $_GET['simulate'] === 'true') {
    try {
        $fake_pin = rand(1000, 9999);
        // Create a fake active item owned by user 1 (or any user), bought by YOU
        $stmt = $pdo->prepare("INSERT INTO listings (seller_id, buyer_id, title, description, price, category, listing_status, escrow_pin, image_path) VALUES (1, ?, 'Simulated MacBook Pro', 'Testing the escrow engine', 75000, 'Electronics', 'escrow', ?, '[]')");
        $stmt->execute([$my_id, $fake_pin]);
        $success = "🧪 SIMULATION SUCCESS: A fake purchase has been added to your vault!";
    } catch (PDOException $e) {
        $error = "Simulation failed: " . $e->getMessage();
    }
}

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
// 📊 QUARANTINED DATA FETCHING (BULLETPROOF)
// ==========================================
// 1. Profile Data
try {
    $stmt = $pdo->prepare("SELECT full_name, university_name, profile_picture, completed_escrows, account_state FROM users WHERE user_id = ?");
    $stmt->execute([$my_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) { $error .= "<br>User Data Error: " . $e->getMessage(); }

// 2. Follower Data
$followers = 0; $following = 0;
try {
    $f_count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ?");
    $f_count->execute([$my_id]);
    $followers = $f_count->fetchColumn();

    $fw_count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
    $fw_count->execute([$my_id]);
    $following = $fw_count->fetchColumn();
} catch (PDOException $e) { $error .= "<br>Follower Data Error: " . $e->getMessage(); }

// 3. Active Inventory
$active_listings = [];
try {
    $stmt_active = $pdo->prepare("SELECT * FROM listings WHERE seller_id = ? AND listing_status = 'active' ORDER BY created_at DESC");
    $stmt_active->execute([$my_id]);
    $active_listings = $stmt_active->fetchAll();
} catch (PDOException $e) { $error .= "<br>Active Inventory Error: " . $e->getMessage(); }

// 4. Pending Sales (Seller view)
$pending_sales = [];
try {
    $stmt_sales = $pdo->prepare("SELECT l.*, u.full_name as buyer_name FROM listings l LEFT JOIN users u ON l.buyer_id = u.user_id WHERE l.seller_id = ? AND l.listing_status = 'escrow' ORDER BY l.created_at DESC");
    $stmt_sales->execute([$my_id]);
    $pending_sales = $stmt_sales->fetchAll();
} catch (PDOException $e) { $error .= "<br>Pending Sales Error: " . $e->getMessage(); }

// 5. My Purchases (Buyer view)
$my_purchases = [];
try {
    $stmt_purchases = $pdo->prepare("SELECT l.*, u.full_name as seller_name FROM listings l LEFT JOIN users u ON l.seller_id = u.user_id WHERE l.buyer_id = ? ORDER BY l.created_at DESC");
    $stmt_purchases->execute([$my_id]);
    $my_purchases = $stmt_purchases->fetchAll();
} catch (PDOException $e) { $error .= "<br>Purchases Error: " . $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | MILELE</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; min-height: 100vh; display: flex; flex-direction: column;}
        .nav-bar { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(5,5,5,0.8); backdrop-filter: blur(20px); position: sticky; top: 0; z-index: 100;}
        .brand { font-size: 1.8rem; font-weight: 800; color: #2DD4BF; text-decoration: none; letter-spacing: -1px;}
        .nav-actions { display: flex; gap: 15px;}
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; font-weight: bold; transition: 0.3s;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }
        .btn-red { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3);}
        .btn-red:hover { background: #EF4444; color: #000; }

        .profile-hero { padding: 60px 20px 40px; text-align: center; background: radial-gradient(circle at 50% -20%, rgba(45,212,191,0.1), transparent 50%); }
        .avatar-wrapper { position: relative; width: 120px; height: 120px; margin: 0 auto 20px; }
        .big-avatar { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 3px solid #2DD4BF; background: #111; display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: bold; color: #2DD4BF;}
        
        .profile-name { font-size: 2.5rem; margin: 0 0 10px 0; display: flex; align-items: center; justify-content: center; gap: 10px;}
        .verified-tick { color: #3B82F6; font-size: 1.5rem; }
        .profile-uni { color: #888; font-size: 1.1rem; margin-bottom: 30px; }
        
        .trust-stats { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; margin-bottom: 30px;}
        .stat-box { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 15px 25px; border-radius: 16px; min-width: 100px;}
        .stat-num { font-size: 1.6rem; font-weight: bold; color: #fff; margin-bottom: 5px;}
        .stat-label { font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 1px;}

        .alert { max-width: 800px; margin: 0 auto 20px; padding: 15px; border-radius: 12px; font-weight: bold; text-align: center;}
        .alert-error { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); }
        .alert-success { background: rgba(45,212,191,0.1); color: #2DD4BF; border: 1px solid rgba(45,212,191,0.3); }

        .dashboard-container { max-width: 1200px; margin: 0 auto; padding: 0 20px 80px; width: 100%; box-sizing: border-box;}
        .section-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; margin-bottom: 30px; margin-top: 50px;}
        .section-title { font-size: 1.5rem; margin: 0; color: #fff; display: flex; align-items: center; gap: 10px;}
        .btn-new-item { padding: 10px 20px; background: #2DD4BF; color: #000; text-decoration: none; border-radius: 12px; font-weight: bold; transition: 0.2s;}
        .btn-new-item:hover { background: #fff; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 30px; }
        .card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; overflow: hidden; display: flex; flex-direction: column; position: relative;}
        
        .status-badge { position: absolute; top: 15px; left: 15px; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; z-index: 10;}
        .status-active { color: #2DD4BF; border: 1px solid rgba(45,212,191,0.3); }
        .status-escrow { color: #F59E0B; border: 1px solid rgba(245,158,11,0.3); }
        .status-sold { color: #6B7280; border: 1px solid rgba(107,114,128,0.3); }

        .card-img { width: 100%; height: 200px; object-fit: contain; background: #0a0a0a; border-bottom: 1px solid rgba(255,255,255,0.05);}
        .card-body { padding: 20px; display: flex; flex-direction: column; flex-grow: 1; }
        .card-title { font-size: 1.1rem; font-weight: bold; color: #fff; margin: 0 0 10px 0;}
        .card-price { font-size: 1.3rem; color: #2DD4BF; font-weight: bold; margin-bottom: 15px;}
        
        .entity-label { font-size: 0.85rem; color: #888; margin-bottom: 10px;}
        .entity-name { color: #fff; font-weight: bold; }

        .escrow-action-box { background: rgba(245,158,11,0.05); border: 1px solid rgba(245,158,11,0.3); border-radius: 12px; padding: 15px; margin-top: auto;}
        .escrow-input-group { display: flex; gap: 10px; margin-top: 10px;}
        .escrow-input { flex-grow: 1; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 10px; border-radius: 8px; font-family: monospace; font-size: 1.1rem; text-align: center; outline: none;}
        .escrow-input:focus { border-color: #F59E0B; }
        .btn-release { background: #F59E0B; color: #000; border: none; padding: 10px 15px; border-radius: 8px; font-weight: bold; cursor: pointer;}
        
        .escrow-vault { background: rgba(45,212,191,0.05); border: 1px solid rgba(45,212,191,0.3); border-radius: 12px; padding: 15px; margin-top: auto;}
        .vault-header { font-size: 0.85rem; color: #2DD4BF; font-weight: bold; margin-bottom: 10px; text-transform: uppercase;}
        .pin-display-group { display: flex; align-items: center; gap: 10px; background: rgba(0,0,0,0.5); padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);}
        .pin-text { font-family: monospace; font-size: 1.5rem; font-weight: bold; color: #fff; flex-grow: 1; text-align: center; letter-spacing: 5px;}
        .pin-hidden { filter: blur(6px); user-select: none;}
        .btn-icon { background: rgba(255,255,255,0.1); border: none; color: #fff; border-radius: 8px; width: 35px; height: 35px; cursor: pointer; transition: 0.2s;}
        .btn-icon:hover { background: #2DD4BF; color: #000;}

        .empty-state { text-align: center; padding: 40px; color: #666; background: rgba(255,255,255,0.02); border-radius: 20px; border: 1px dashed rgba(255,255,255,0.1);}
        .sim-btn { background: #8B5CF6; color: #fff; border: none; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; cursor: pointer; text-decoration: none; margin-left: 15px;}
        .sim-btn:hover { background: #A78BFA; }
    </style>
</head>
<body>

<nav class="nav-bar">
    <a href="index.php" class="brand">MILELE</a>
    <div class="nav-actions">
        <a href="index.php" class="btn-glass">← Market Feed</a>
        <a href="profile.php?action=logout" class="btn-glass btn-red">Logout</a>
    </div>
</nav>

<header class="profile-hero">
    <?php if($error) echo "<div class='alert alert-error'>$error</div>"; ?>
    <?php if($success) echo "<div class='alert alert-success'>$success</div>"; ?>

    <div class="avatar-wrapper">
        <?php if($user['profile_picture']): ?>
            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="big-avatar" alt="Profile">
        <?php else: ?>
            <div class="big-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
        <?php endif; ?>
    </div>

    <h1 class="profile-name">
        <?php echo htmlspecialchars($user['full_name']); ?>
        <?php if($user['account_state'] === 'campus_verified'): ?>
            <span class="verified-tick" title="Campus Verified User">✔️</span>
        <?php endif; ?>
    </h1>
    <div class="profile-uni">🎓 <?php echo htmlspecialchars($user['university_name']); ?></div>

    <div class="trust-stats">
        <div class="stat-box">
            <div class="stat-num"><?php echo $followers; ?></div>
            <div class="stat-label">Followers</div>
        </div>
        <div class="stat-box">
            <div class="stat-num"><?php echo $following; ?></div>
            <div class="stat-label">Following</div>
        </div>
        <div class="stat-box" style="border-color: rgba(45,212,191,0.3); background: rgba(45,212,191,0.05);">
            <div class="stat-num" style="color: #2DD4BF;"><?php echo (int)$user['completed_escrows']; ?></div>
            <div class="stat-label" style="color: #2DD4BF;">Deals Done</div>
        </div>
    </div>
</header>

<main class="dashboard-container">
    
    <div class="section-header">
        <h2 class="section-title">
            🛍️ My Purchases 
            <a href="profile.php?simulate=true" class="sim-btn" title="Creates a fake purchase so you can test the PIN UI">🧪 Simulate Purchase</a>
        </h2>
    </div>
    
    <?php if (empty($my_purchases)): ?>
        <div class="empty-state">
            <div style="font-size: 2rem; margin-bottom: 10px;">🛒</div>
            You haven't bought anything yet.<br>
            <span style="font-size: 0.8rem; color: #444;">(Note: Because we haven't built checkout.php yet, you can use the Simulate button above to see this UI).</span>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($my_purchases as $item): 
                $img = json_decode($item['image_path'], true)[0] ?? $item['image_path'];
                $is_sold = ($item['listing_status'] === 'sold');
            ?>
                <div class="card" style="<?php echo $is_sold ? 'opacity: 0.6;' : ''; ?>">
                    <div class="status-badge <?php echo $is_sold ? 'status-sold' : 'status-active'; ?>">
                        <?php echo $is_sold ? 'Complete' : 'Pending Delivery'; ?>
                    </div>
                    <img src="<?php echo htmlspecialchars($img); ?>" class="card-img" alt="Item">
                    
                    <div class="card-body">
                        <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <div class="entity-label">Seller: <span class="entity-name"><?php echo htmlspecialchars($item['seller_name'] ?? 'System User'); ?></span></div>
                        
                        <?php if(!$is_sold && $item['escrow_pin']): ?>
                            <div class="escrow-vault">
                                <div class="vault-header">Your Transaction PIN</div>
                                <div class="pin-display-group">
                                    <div class="pin-text pin-hidden" id="pin_<?php echo $item['listing_id']; ?>"><?php echo htmlspecialchars($item['escrow_pin']); ?></div>
                                    <button class="btn-icon" onclick="togglePin('pin_<?php echo $item['listing_id']; ?>')" title="Reveal PIN">👁️</button>
                                </div>
                                <div style="font-size: 0.75rem; color: #888; margin-top: 10px;">Give this PIN to the seller only after inspecting the item.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section-header">
        <h2 class="section-title">🔐 Pending Escrow Sales</h2>
    </div>

    <?php if (empty($pending_sales)): ?>
        <div class="empty-state">No items are currently locked in Escrow.</div>
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
                        <div class="card-price">KES <?php echo number_format($item['price'], 2); ?></div>
                        <div class="entity-label">Buyer: <span class="entity-name"><?php echo htmlspecialchars($item['buyer_name']); ?></span></div>
                        
                        <form method="POST" class="escrow-action-box">
                            <input type="hidden" name="action" value="release_escrow">
                            <input type="hidden" name="listing_id" value="<?php echo $item['listing_id']; ?>">
                            <div style="font-size: 0.85rem; color: #F59E0B; font-weight: bold;">Enter Buyer's PIN to Release Funds:</div>
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
        <h2 class="section-title">📦 Active Inventory</h2>
        <a href="post_item.php" class="btn-new-item">+ List New Item</a>
    </div>

    <?php if (empty($active_listings)): ?>
        <div class="empty-state">You have no active items on the market.</div>
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
                        <div class="card-price">KES <?php echo number_format($item['price'], 2); ?></div>
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