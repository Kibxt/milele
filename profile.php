<?php
// MILELE - Premium Profile Dashboard

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ⚡ THE MASTER CONNECTION
require 'db.php';

$user_id = $_SESSION['user_id'];

try {
    // Fetch User Details
    $stmt = $pdo->prepare("SELECT full_name, email, university_name, completed_escrows, created_at FROM users WHERE user_id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();

    // Fetch User's Active Listings
    $stmt_listings = $pdo->prepare("SELECT * FROM listings WHERE seller_id = :id AND listing_status != 'deleted' ORDER BY created_at DESC");
    $stmt_listings->execute([':id' => $user_id]);
    $my_listings = $stmt_listings->fetchAll();

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System error loading profile.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | MILELE</title>
    <style>
        /* Ultra-Premium Glass Aesthetic */
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        
        /* Top Navigation Bar */
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .nav-bar h1 { color: #fff; margin: 0; font-size: 2rem; }
        .nav-buttons { display: flex; gap: 15px; }
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; transition: 0.3s; font-size: 0.9rem; }
        .btn-glass:hover { background: rgba(255,255,255,0.1); }
        .btn-accent { background: #2DD4BF; color: #000; border: none; font-weight: bold; }
        .btn-accent:hover { background: #fff; }
        .btn-danger { color: #F87171; border-color: rgba(248,113,113,0.3); }
        .btn-danger:hover { background: rgba(248,113,113,0.1); }

        /* Profile Info Card */
        .profile-card { background: rgba(255,255,255,0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.08); padding: 30px; border-radius: 24px; margin-bottom: 40px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;}
        .profile-info h2 { margin: 0 0 5px 0; color: #2DD4BF; }
        .profile-info p { margin: 0; color: #888; font-size: 0.95rem; }
        .stats-box { text-align: center; background: rgba(0,0,0,0.5); padding: 15px 30px; border-radius: 16px; border: 1px solid rgba(45,212,191,0.2); }
        .stats-box h3 { margin: 0; font-size: 2rem; color: #fff; }
        .stats-box span { font-size: 0.8rem; color: #2DD4BF; text-transform: uppercase; letter-spacing: 1px; }

        /* Listings Grid */
        .section-title { font-size: 1.2rem; color: #888; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .item-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 16px; transition: 0.3s; }
        .item-card:hover { border-color: rgba(45,212,191,0.3); transform: translateY(-3px); }
        .item-title { font-weight: bold; margin-bottom: 10px; font-size: 1.1rem; }
        .item-price { color: #2DD4BF; margin-bottom: 15px; }
        .item-status { display: inline-block; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; background: rgba(255,255,255,0.1); }
        .status-active { background: rgba(45,212,191,0.1); color: #2DD4BF; }
        .status-hidden { background: rgba(248,113,113,0.1); color: #F87171; }
    </style>
</head>
<body>

<div class="container">
    <div class="nav-bar">
        <h1>My Profile</h1>
        <div class="nav-buttons">
            <a href="index.php" class="btn-glass btn-accent">← Return to Market</a>
            <a href="logout.php" class="btn-glass btn-danger">Logout</a>
        </div>
    </div>

    <div class="profile-card">
        <div class="profile-info">
            <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
            <p><?php echo htmlspecialchars($user['email']); ?></p>
            <p style="margin-top: 10px;">🎓 <?php echo htmlspecialchars($user['university_name'] ?: 'University Not Specified'); ?></p>
        </div>
        <div class="stats-box">
            <h3><?php echo (int)$user['completed_escrows']; ?></h3>
            <span>Successful Deals</span>
        </div>
    </div>

    <div class="section-title">My Studio Listings</div>
    
    <?php if (empty($my_listings)): ?>
        <p style="color: #666; text-align: center; padding: 40px; background: rgba(255,255,255,0.02); border-radius: 16px;">You haven't posted any items yet.</p>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($my_listings as $item): ?>
                <div class="item-card">
                    <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div class="item-price">KES <?php echo number_format($item['price'], 2); ?></div>
                    <div class="item-status <?php echo $item['listing_status'] === 'active' ? 'status-active' : 'status-hidden'; ?>">
                        <?php echo ucfirst($item['listing_status']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>