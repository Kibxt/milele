<?php
// MILELE - Private User Dashboard (Escrow & Social Engine)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ==========================================
// 🛑 SECURE LOGOUT ROUTER (THE FIX)
// ==========================================
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
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS follows (
        follower_id INT NOT NULL,
        followed_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (follower_id, followed_id)
    )");
} catch (PDOException $e) {}

// ==========================================
// 📸 AI PROFILE PICTURE UPLOAD
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    $tmp_name = $_FILES['profile_pic']['tmp_name'];
    $file_type = $_FILES['profile_pic']['type'];
    $file_name = $_FILES['profile_pic']['name'];

    $sightengine_user = '1287637059';     
    $sightengine_secret = 'vVLakzVx9WAHwqvg9o8p9ucggiu5byzJ'; 
    
    $ch_ai = curl_init('https://api.sightengine.com/1.0/check.json');
    curl_setopt($ch_ai, CURLOPT_POST, true);
    curl_setopt($ch_ai, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_ai, CURLOPT_SSL_VERIFYPEER, false);
    $cfile = new CURLFile($tmp_name, $file_type, $file_name);
    curl_setopt($ch_ai, CURLOPT_POSTFIELDS, ['models' => 'nudity-2.0,wad,offensive,gore', 'api_user' => $sightengine_user, 'api_secret' => $sightengine_secret, 'media' => $cfile]);
    
    $ai_result = json_decode(curl_exec($ch_ai), true);
    curl_close($ch_ai);

    $is_safe = true;
    if (isset($ai_result['status']) && $ai_result['status'] === 'success') {
        $weapon_score = $ai_result['weapon'] ?? ($ai_result['wad']['weapon'] ?? 0);
        $safe_score = $ai_result['nudity']['safe'] ?? ($ai_result['nudity']['none'] ?? 1);
        if ($weapon_score > 0.4 || $safe_score < 0.5) {
            $error = "Profile picture rejected by AI Security. Please use an appropriate image.";
            $is_safe = false;
        }
    } else {
        $error = "AI Scan failed. Please try again.";
        $is_safe = false;
    }

    if ($is_safe) {
        $imgbb_api_key = '1006ee1ae706c851943f2918cb115ed8'; 
        $image_base64 = base64_encode(file_get_contents($tmp_name));
        
        $ch_cloud = curl_init();
        curl_setopt($ch_cloud, CURLOPT_URL, 'https://api.imgbb.com/1/upload?key=' . $imgbb_api_key);
        curl_setopt($ch_cloud, CURLOPT_POST, 1);
        curl_setopt($ch_cloud, CURLOPT_POSTFIELDS, ['image' => $image_base64]);
        curl_setopt($ch_cloud, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_cloud, CURLOPT_SSL_VERIFYPEER, false);
        
        $cloud_result = json_decode(curl_exec($ch_cloud), true);
        curl_close($ch_cloud);
        
        if (isset($cloud_result['data']['url'])) {
            $new_pic_url = $cloud_result['data']['url'];
            $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?")->execute([$new_pic_url, $my_id]);
            $success = "Profile picture updated successfully!";
        } else {
            $error = "Cloud upload failed.";
        }
    }
}

// ==========================================
// 📊 FETCH CORE DATA (Stats, Escrows, Inventory)
// ==========================================
try {
    // 1. User Stats
    $stmt = $pdo->prepare("SELECT full_name, university_name, profile_picture, completed_escrows, account_state FROM users WHERE user_id = ?");
    $stmt->execute([$my_id]);
    $user = $stmt->fetch();

    // 2. Follower Engine
    $followers_count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ?");
    $followers_count->execute([$my_id]);
    $f_count = $followers_count->fetchColumn();

    $following_count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
    $following_count->execute([$my_id]);
    $fw_count = $following_count->fetchColumn();

    // 3. Inventory Engine (Active Items)
    $stmt_active = $pdo->prepare("SELECT * FROM listings WHERE seller_id = ? AND listing_status = 'active' ORDER BY created_at DESC");
    $stmt_active->execute([$my_id]);
    $active_listings = $stmt_active->fetchAll();

    // 4. Escrow Engine (Sold or Locked Items)
    $stmt_escrow = $pdo->prepare("SELECT * FROM listings WHERE seller_id = ? AND listing_status != 'active' ORDER BY created_at DESC");
    $stmt_escrow->execute([$my_id]);
    $escrow_listings = $stmt_escrow->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}
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
        .btn-accent { background: #F87171; color: #fff; border: none; border: 1px solid rgba(248,113,113,0.3);}
        .btn-accent:hover { background: #EF4444; }

        /* Trust Hero Section */
        .profile-hero { padding: 60px 20px; text-align: center; background: radial-gradient(circle at 50% -20%, rgba(45,212,191,0.1), transparent 50%); border-bottom: 1px solid rgba(255,255,255,0.05); }
        .avatar-wrapper { position: relative; width: 120px; height: 120px; margin: 0 auto 20px; }
        .big-avatar { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 3px solid #2DD4BF; background: #111; display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: bold; color: #2DD4BF; box-shadow: 0 10px 30px rgba(45,212,191,0.2);}
        .edit-btn { position: absolute; bottom: 0; right: 0; background: #2DD4BF; color: #000; border: none; border-radius: 50%; width: 35px; height: 35px; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.5); transition: 0.2s;}
        .edit-btn:hover { transform: scale(1.1); background: #fff;}
        
        .profile-name { font-size: 2.5rem; margin: 0 0 10px 0; display: flex; align-items: center; justify-content: center; gap: 10px;}
        .verified-tick { color: #3B82F6; font-size: 1.5rem; }
        .profile-uni { color: #888; font-size: 1.1rem; margin-bottom: 30px; }
        
        .trust-stats { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; margin-top: 20px;}
        .stat-box { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 15px 25px; border-radius: 16px; min-width: 100px;}
        .stat-num { font-size: 1.6rem; font-weight: bold; color: #fff; margin-bottom: 5px;}
        .stat-num.accent { color: #2DD4BF; }
        .stat-label { font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 1px;}

        /* Alerts */
        .alert { max-width: 600px; margin: 0 auto 20px; padding: 15px; border-radius: 12px; font-weight: bold;}
        .alert-error { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); }
        .alert-success { background: rgba(45,212,191,0.1); color: #2DD4BF; border: 1px solid rgba(45,212,191,0.3); }

        /* Dashboard Layout */
        .dashboard-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px 80px; width: 100%; box-sizing: border-box; flex-grow: 1;}
        .section-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; margin-bottom: 30px;}
        .section-title { font-size: 1.5rem; margin: 0; color: #fff;}
        .btn-new-item { padding: 10px 20px; background: #2DD4BF; color: #000; text-decoration: none; border-radius: 12px; font-weight: bold; transition: 0.2s;}
        .btn-new-item:hover { background: #fff; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; margin-bottom: 50px;}
        .card { background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0) 100%); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; overflow: hidden; transition: transform 0.3s; display: flex; flex-direction: column; position: relative;}
        .card:hover { transform: translateY(-5px); border-color: rgba(45,212,191,0.3); box-shadow: 0 20px 40px rgba(0,0,0,0.4);}
        
        .status-badge { position: absolute; top: 15px; left: 15px; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; z-index: 10;}
        .status-active { color: #2DD4BF; border: 1px solid rgba(45,212,191,0.3); }
        .status-escrow { color: #F59E0B; border: 1px solid rgba(245,158,11,0.3); }
        .status-sold { color: #F87171; border: 1px solid rgba(248,113,113,0.3); }

        .card-img { width: 100%; aspect-ratio: 1/1; object-fit: contain; background: radial-gradient(circle, #1a1a1a 0%, #050505 100%); border-bottom: 1px solid rgba(255,255,255,0.05);}
        .card-body { padding: 25px 20px 20px; display: flex; flex-direction: column; flex-grow: 1; }
        .card-title { font-size: 1.15rem; font-weight: bold; color: #fff; margin: 0 0 10px 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;}
        .card-price { font-size: 1.4rem; color: #2DD4BF; font-weight: bold; margin-bottom: 15px;}
        
        .card-footer { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px; display: flex; gap: 10px;}
        .btn-action { flex: 1; padding: 10px; background: rgba(255,255,255,0.05); color: #fff; border-radius: 10px; font-size: 0.85rem; font-weight: bold; text-align: center; text-decoration: none; transition: 0.2s;}
        .btn-action:hover { background: #fff; color: #000; }
        .btn-view { flex: 1; padding: 10px; background: rgba(45,212,191,0.1); color: #2DD4BF; border-radius: 10px; font-size: 0.85rem; font-weight: bold; text-align: center; text-decoration: none; transition: 0.2s; border: 1px solid rgba(45,212,191,0.3);}
        .btn-view:hover { background: #2DD4BF; color: #000; }

        .empty-state { text-align: center; padding: 50px 20px; color: #666; background: rgba(255,255,255,0.02); border-radius: 24px; border: 1px dashed rgba(255,255,255,0.1); margin-bottom: 50px;}
    </style>
</head>
<body>

<nav class="nav-bar">
    <a href="index.php" class="brand">MILELE</a>
    <div class="nav-actions">
        <a href="index.php" class="btn-glass">← Market Feed</a>
        <a href="profile.php?action=logout" class="btn-glass btn-accent">Logout</a>
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
        <button class="edit-btn" onclick="document.getElementById('picInput').click()" title="Change Profile Picture">📷</button>
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
            <div class="stat-num"><?php echo $f_count; ?></div>
            <div class="stat-label">Followers</div>
        </div>
        <div class="stat-box">
            <div class="stat-num"><?php echo $fw_count; ?></div>
            <div class="stat-label">Following</div>
        </div>
        <div class="stat-box" style="border-color: rgba(45,212,191,0.3); background: rgba(45,212,191,0.05);">
            <div class="stat-num accent"><?php echo (int)$user['completed_escrows']; ?></div>
            <div class="stat-label" style="color: #2DD4BF;">Deals Done</div>
        </div>
    </div>

    <form id="picForm" method="POST" enctype="multipart/form-data" style="display:none;">
        <input type="file" name="profile_pic" id="picInput" accept="image/*" onchange="document.getElementById('picForm').submit();">
    </form>
</header>

<main class="dashboard-container">
    
    <div class="section-header">
        <h2 class="section-title">Active Inventory</h2>
        <a href="post_item.php" class="btn-new-item">+ List New Item</a>
    </div>
    
    <?php if (empty($active_listings)): ?>
        <div class="empty-state">
            <div style="font-size: 3rem; opacity: 0.5; margin-bottom: 10px;">📦</div>
            <h2 style="color: #fff; margin-bottom: 5px;">Your shop is empty</h2>
            <p>You haven't listed any items for sale yet. Turn your unused campus gear into cash.</p>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($active_listings as $item): 
                $decoded_images = json_decode($item['image_path'], true);
                $thumbnail = (json_last_error() === JSON_ERROR_NONE && is_array($decoded_images) && count($decoded_images) > 0) ? $decoded_images[0] : (!empty($item['image_path']) ? $item['image_path'] : 'https://via.placeholder.com/400x400/111111/333333?text=MILELE');
            ?>
                <div class="card">
                    <div class="status-badge status-active">● Active</div>
                    <img src="<?php echo htmlspecialchars($thumbnail); ?>" loading="lazy" class="card-img" alt="Item Image">
                    
                    <div class="card-body">
                        <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <div class="card-price">KES <?php echo number_format($item['price'], 2); ?></div>
                        
                        <div class="card-footer">
                            <a href="item.php?id=<?php echo $item['listing_id']; ?>" class="btn-view">View Public</a>
                            <a href="#" class="btn-action" onclick="alert('Edit functionality coming soon!'); return false;">Edit Item</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section-header">
        <h2 class="section-title">Escrow & Past Deals</h2>
    </div>

    <?php if (empty($escrow_listings)): ?>
        <div class="empty-state" style="padding: 30px;">
            <p style="margin: 0;">No past deals or locked escrows yet.</p>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($escrow_listings as $item): 
                $decoded_images = json_decode($item['image_path'], true);
                $thumbnail = (json_last_error() === JSON_ERROR_NONE && is_array($decoded_images) && count($decoded_images) > 0) ? $decoded_images[0] : (!empty($item['image_path']) ? $item['image_path'] : 'https://via.placeholder.com/400x400/111111/333333?text=MILELE');
                
                // Determine styling based on status
                $status_class = ($item['listing_status'] === 'sold') ? 'status-sold' : 'status-escrow';
                $status_text = ($item['listing_status'] === 'sold') ? 'Sold' : 'In Escrow';
            ?>
                <div class="card" style="opacity: 0.7;">
                    <div class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></div>
                    <img src="<?php echo htmlspecialchars($thumbnail); ?>" loading="lazy" class="card-img" alt="Item Image" style="filter: grayscale(100%);">
                    
                    <div class="card-body">
                        <h3 class="card-title" style="color: #aaa;"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <div class="card-price" style="color: #888;">KES <?php echo number_format($item['price'], 2); ?></div>
                        
                        <div class="card-footer">
                            <a href="item.php?id=<?php echo $item['listing_id']; ?>" class="btn-action" style="width: 100%;">View Record</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

</body>
</html>