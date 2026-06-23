<?php
// MILELE - Secure User Dashboard (Dark Mode)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

// 1. BOUNCER: Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Catch success messages from create_listing.php
if (isset($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// 2. ACTION LISTENER: Handle Listing Deletion securely
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $listing_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
    
    if ($listing_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM listings WHERE listing_id = ? AND seller_id = ?");
            $stmt->execute([$listing_id, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = "Listing permanently deleted.";
            } else {
                $error = "Could not delete listing. It may have already been removed.";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// 3. DATA FETCH: Get User Info
try {
    $stmt = $pdo->prepare("SELECT full_name, email, university_name, is_verified FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    // 4. DATA FETCH: Get User's Active Listings
    $stmt_listings = $pdo->prepare("SELECT * FROM listings WHERE seller_id = ? ORDER BY created_at DESC");
    $stmt_listings->execute([$user_id]);
    $my_listings = $stmt_listings->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database Connection Error.");
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
        
        /* Navigation */
        nav { padding: 20px 5%; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center; background: #050505;}
        .brand { font-size: 1.8rem; font-weight: 900; color: #2DD4BF; text-decoration: none; letter-spacing: -1px;}
        .nav-links { display: flex; gap: 20px; align-items: center;}
        .nav-links a { color: #ccc; text-decoration: none; font-weight: bold; transition: 0.2s;}
        .nav-links a:hover { color: #2DD4BF;}
        .nav-links .danger { color: #F87171;}
        
        /* Main Container */
        .container { max-width: 1200px; margin: 40px auto; padding: 0 5%; flex: 1; width: 100%; box-sizing: border-box;}
        
        /* Alerts */
        .alert-success { background: rgba(45,212,191,0.1); color: #2DD4BF; border: 1px solid rgba(45,212,191,0.3); padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: bold; }
        .alert-error { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: bold; }

        /* Profile Header */
        .profile-card { background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0) 100%); border: 1px solid rgba(255,255,255,0.08); padding: 30px; border-radius: 20px; margin-bottom: 40px; position: relative; overflow: hidden;}
        .profile-card::before { content: ''; position: absolute; top: -50px; left: -50px; width: 150px; height: 150px; background: rgba(45,212,191,0.1); filter: blur(50px); pointer-events: none;}
        .profile-name { font-size: 1.8rem; font-weight: bold; margin-bottom: 10px; color: #fff; display: flex; align-items: center; gap: 10px;}
        .verified-badge { font-size: 0.8rem; background: rgba(45,212,191,0.2); color: #2DD4BF; padding: 4px 10px; border-radius: 50px; font-weight: bold; border: 1px solid rgba(45,212,191,0.3);}
        .profile-details { color: #888; font-size: 1rem; display: flex; gap: 20px;}

        /* Section Header */
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;}
        .section-title { font-size: 1.5rem; font-weight: bold; color: #fff;}
        .btn-primary { background: #2DD4BF; color: #000; padding: 12px 24px; border-radius: 12px; font-weight: bold; text-decoration: none; transition: 0.3s;}
        .btn-primary:hover { background: #fff; box-shadow: 0 5px 15px rgba(45,212,191,0.2);}

        /* Grid & Cards */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .card { background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; transition: 0.3s;}
        .card:hover { border-color: #2DD4BF; box-shadow: 0 10px 20px rgba(0,0,0,0.3); transform: translateY(-3px);}
        .card img { width: 100%; height: 200px; object-fit: cover; border-bottom: 1px solid rgba(255,255,255,0.05);}
        .card-body { padding: 20px; flex: 1; display: flex; flex-direction: column;}
        .card-category { font-size: 0.8rem; color: #888; text-transform: uppercase; font-weight: bold; margin-bottom: 5px;}
        .card-title { font-size: 1.1rem; font-weight: bold; color: #fff; margin-bottom: 10px; line-height: 1.4;}
        .card-price { font-size: 1.5rem; font-weight: 900; color: #2DD4BF; margin-bottom: 20px;}
        
        .btn-delete { width: 100%; background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); padding: 12px; border-radius: 10px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: 0.3s; margin-top: auto;}
        .btn-delete:hover { background: #F87171; color: #000;}

        /* Empty State */
        .empty-state { grid-column: 1 / -1; text-align: center; padding: 60px 20px; background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.2); border-radius: 16px;}
        .empty-state h3 { color: #fff; margin-bottom: 10px; font-size: 1.5rem;}
        .empty-state p { color: #888; margin-bottom: 25px;}
    </style>
</head>
<body>

<nav>
    <a href="index.php" class="brand">MILELE</a>
    <div class="nav-links">
        <a href="index.php">Marketplace</a>
        <a href="logout.php" class="danger">Log Out</a>
    </div>
</nav>

<div class="container">
    <?php if($message) echo "<div class='alert-success'>$message</div>"; ?>
    <?php if($error) echo "<div class='alert-error'>$error</div>"; ?>

    <div class="profile-card">
        <div class="profile-name">
            <?php echo htmlspecialchars($user['full_name']); ?>
            <?php if($user['is_verified']): ?>
                <span class="verified-badge">✓ Verified</span>
            <?php endif; ?>
        </div>
        <div class="profile-details">
            <span>📧 <?php echo htmlspecialchars($user['email']); ?></span>
            <span>🎓 <?php echo htmlspecialchars($user['university_name']); ?></span>
        </div>
    </div>

    <div class="section-header">
        <div class="section-title">My Active Listings</div>
        <a href="create_listing.php" class="btn-primary">+ Post New Item</a>
    </div>

    <div class="grid">
        <?php if (empty($my_listings)): ?>
            <div class="empty-state">
                <h3>Your shop is empty</h3>
                <p>You haven't posted any items for sale yet. Turn your unused campus items into cash today.</p>
                <a href="create_listing.php" class="btn-primary">Post Your First Item</a>
            </div>
        <?php else: ?>
            <?php foreach ($my_listings as $item): ?>
                <div class="card">
                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item Image">
                    <div class="card-body">
                        <div class="card-category"><?php echo htmlspecialchars($item['category']); ?></div>
                        <div class="card-title"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div class="card-price">Ksh <?php echo number_format($item['price']); ?></div>
                        
                        <form method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this item?');" style="margin-top:auto;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="listing_id" value="<?php echo $item['listing_id']; ?>">
                            <button type="submit" class="btn-delete">Delete Item</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>