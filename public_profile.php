<?php
// MILELE - Public Trust Profile

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

$profile_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$profile_id) {
    header("Location: index.php");
    exit();
}

try {
    // 1. Fetch User Reputation Data
    $stmt_user = $pdo->prepare("SELECT full_name, university_name, account_state, completed_escrows, created_at FROM users WHERE user_id = :id");
    $stmt_user->execute([':id' => $profile_id]);
    $user = $stmt_user->fetch();

    if (!$user) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>User not found.</div>");
    }

    // 2. Fetch User's Active Listings
    $stmt_listings = $pdo->prepare("SELECT * FROM listings WHERE seller_id = :id AND listing_status = 'active' ORDER BY created_at DESC");
    $stmt_listings->execute([':id' => $profile_id]);
    $items = $stmt_listings->fetchAll();

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System Error.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['full_name']); ?> | MILELE Profile</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; min-height: 100vh; display: flex; flex-direction: column;}
        
        .nav-bar { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(5,5,5,0.8); backdrop-filter: blur(20px); position: sticky; top: 0; z-index: 100;}
        .brand { font-size: 1.8rem; font-weight: 800; color: #2DD4BF; text-decoration: none; letter-spacing: -1px;}
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; transition: 0.3s; font-size: 0.95rem; font-weight: bold;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }

        /* Trust Hero Section */
        .profile-hero { padding: 60px 20px; text-align: center; background: radial-gradient(circle at 50% -20%, rgba(45,212,191,0.1), transparent 50%); border-bottom: 1px solid rgba(255,255,255,0.05); }
        .big-avatar { width: 100px; height: 100px; background: #111; border: 2px solid #2DD4BF; color: #2DD4BF; font-size: 3rem; font-weight: bold; border-radius: 50%; display: flex; justify-content: center; align-items: center; margin: 0 auto 20px; box-shadow: 0 10px 30px rgba(45,212,191,0.2);}
        .profile-name { font-size: 2.5rem; margin: 0 0 10px 0; display: flex; align-items: center; justify-content: center; gap: 10px;}
        .verified-tick { color: #3B82F6; font-size: 1.5rem; }
        .profile-uni { color: #888; font-size: 1.1rem; margin-bottom: 30px; }
        
        .trust-stats { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;}
        .stat-box { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 15px 25px; border-radius: 16px; min-width: 120px;}
        .stat-num { font-size: 1.8rem; font-weight: bold; color: #fff; margin-bottom: 5px;}
        .stat-num.accent { color: #2DD4BF; }
        .stat-label { font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 1px;}

        /* Listings Grid */
        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px 80px; flex-grow: 1; width: 100%; box-sizing: border-box;}
        .section-title { font-size: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; margin-bottom: 30px;}
        
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }
        
        .card { background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0) 100%); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; overflow: hidden; transition: transform 0.3s; display: flex; flex-direction: column; text-decoration: none; position: relative;}
        .card:hover { transform: translateY(-5px); border-color: rgba(45,212,191,0.3); box-shadow: 0 20px 40px rgba(0,0,0,0.4);}
        
        .floating-badges { position: absolute; top: 15px; left: 15px; right: 15px; display: flex; justify-content: space-between; z-index: 10; pointer-events: none;}
        .glass-badge { background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.1); padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; color: #fff; text-transform: uppercase; letter-spacing: 1px;}
        
        .card-img { width: 100%; aspect-ratio: 1/1; object-fit: contain; background: radial-gradient(circle, #1a1a1a 0%, #050505 100%); border-bottom: 1px solid rgba(255,255,255,0.05);}
        
        .card-body { padding: 25px 20px 20px; display: flex; flex-direction: column; flex-grow: 1; }
        .card-title { font-size: 1.15rem; font-weight: bold; color: #fff; margin: 0 0 10px 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4;}
        .card-price { font-size: 1.4rem; color: #2DD4BF; font-weight: bold; margin-bottom: 15px;}
        
        .card-footer { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px; text-align: right;}
        .btn-view { padding: 8px 16px; background: rgba(255,255,255,0.05); color: #fff; border-radius: 10px; font-size: 0.85rem; font-weight: bold; transition: 0.2s;}
        .card:hover .btn-view { background: #2DD4BF; color: #000; }

        .empty-state { text-align: center; padding: 50px 20px; color: #666; }
    </style>
</head>
<body>

<nav class="nav-bar">
    <a href="index.php" class="brand">MILELE</a>
    <a href="javascript:history.back()" class="btn-glass">← Back</a>
</nav>

<header class="profile-hero">
    <div class="big-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
    <h1 class="profile-name">
        <?php echo htmlspecialchars($user['full_name']); ?>
        <?php if($user['account_state'] === 'campus_verified'): ?>
            <span class="verified-tick" title="Campus Verified User">✔️</span>
        <?php endif; ?>
    </h1>
    <div class="profile-uni">🎓 <?php echo htmlspecialchars($user['university_name']); ?></div>
    
    <div class="trust-stats">
        <div class="stat-box">
            <div class="stat-num accent"><?php echo (int)$user['completed_escrows']; ?></div>
            <div class="stat-label">Deals Done</div>
        </div>
        <div class="stat-box">
            <div class="stat-num"><?php echo count($items); ?></div>
            <div class="stat-label">Active Listings</div>
        </div>
        <div class="stat-box">
            <div class="stat-num" style="font-size: 1.2rem; margin-top: 10px;"><?php echo date('M Y', strtotime($user['created_at'])); ?></div>
            <div class="stat-label">Joined</div>
        </div>
    </div>
    
    <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $profile_id): ?>
        <a href="inbox.php?user=<?php echo $profile_id; ?>" class="btn-glass" style="display: inline-block; margin-top: 30px; background: #2DD4BF; color: #000; padding: 12px 30px;">💬 Message Seller</a>
    <?php endif; ?>
</header>

<main class="container">
    <h2 class="section-title">Current Listings</h2>
    
    <?php if (empty($items)): ?>
        <div class="empty-state">
            <div style="font-size: 3rem; opacity: 0.5; margin-bottom: 10px;">📦</div>
            <p>This user has no active items for sale right now.</p>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($items as $item): 
                $decoded_images = json_decode($item['image_path'], true);
                $thumbnail = (json_last_error() === JSON_ERROR_NONE && is_array($decoded_images) && count($decoded_images) > 0) ? $decoded_images[0] : (!empty($item['image_path']) ? $item['image_path'] : 'https://via.placeholder.com/400x400/111111/333333?text=MILELE');
            ?>
                <a href="item.php?id=<?php echo $item['listing_id']; ?>" class="card">
                    <div class="floating-badges">
                        <span class="glass-badge" style="color:#2DD4BF; border-color: rgba(45,212,191,0.3);"><?php echo htmlspecialchars($item['category']); ?></span>
                    </div>
                    <img src="<?php echo htmlspecialchars($thumbnail); ?>" loading="lazy" class="card-img" alt="Item Image">
                    
                    <div class="card-body">
                        <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <div class="card-price">KES <?php echo number_format($item['price'], 2); ?></div>
                        <div class="card-footer"><div class="btn-view">View Item</div></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>

</body>
</html>