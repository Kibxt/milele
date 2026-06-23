<?php
// MILELE - Secure Public Marketplace (Dark Mode)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

// Check if user is logged in for Navigation bar
$is_logged_in = isset($_SESSION['user_id']);

// Fetch latest active listings securely from the database
try {
    $stmt = $pdo->query("
        SELECT l.*, u.full_name, u.is_verified 
        FROM listings l 
        JOIN users u ON l.seller_id = u.user_id 
        ORDER BY l.created_at DESC 
        LIMIT 20
    ");
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $listings = []; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace | MILELE</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; min-height: 100vh;}
        
        /* Navigation */
        nav { padding: 20px 5%; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center; background: #050505; position: sticky; top: 0; z-index: 100;}
        .brand { font-size: 1.8rem; font-weight: 900; color: #2DD4BF; text-decoration: none; letter-spacing: -1px;}
        .nav-links { display: flex; gap: 20px; align-items: center;}
        .nav-links a { color: #ccc; text-decoration: none; font-weight: bold; transition: 0.2s;}
        .nav-links a:hover { color: #2DD4BF;}
        .btn-primary-sm { background: #2DD4BF; color: #000; padding: 8px 16px; border-radius: 8px; font-weight: bold; text-decoration: none; transition: 0.3s;}
        .btn-primary-sm:hover { background: #fff;}

        /* Hero Section */
        .hero { text-align: center; padding: 80px 20px; background: linear-gradient(180deg, rgba(45,212,191,0.05) 0%, #050505 100%); border-bottom: 1px solid rgba(255,255,255,0.05);}
        .hero h1 { font-size: 3rem; margin-bottom: 15px; color: #fff;}
        .hero h1 span { color: #2DD4BF;}
        .hero p { color: #888; font-size: 1.1rem; max-width: 600px; margin: 0 auto 30px;}
        .btn-primary { background: #2DD4BF; color: #000; padding: 15px 30px; border-radius: 12px; font-size: 1.1rem; font-weight: bold; text-decoration: none; transition: 0.3s; display: inline-block;}
        .btn-primary:hover { background: #fff; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(45,212,191,0.2);}

        /* Main Container */
        .container { max-width: 1200px; margin: 40px auto; padding: 0 5%;}
        .section-title { font-size: 1.5rem; font-weight: bold; color: #fff; margin-bottom: 25px; display: flex; align-items: center; gap: 10px;}
        .live-dot { width: 10px; height: 10px; background: #2DD4BF; border-radius: 50%; box-shadow: 0 0 10px #2DD4BF; animation: pulse 2s infinite;}
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }

        /* Grid & Cards */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .card { background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; transition: 0.3s;}
        .card:hover { border-color: #2DD4BF; box-shadow: 0 10px 20px rgba(0,0,0,0.3); transform: translateY(-3px);}
        .card img { width: 100%; height: 200px; object-fit: cover; border-bottom: 1px solid rgba(255,255,255,0.05);}
        .card-body { padding: 20px; flex: 1; display: flex; flex-direction: column;}
        .card-category { font-size: 0.8rem; color: #888; text-transform: uppercase; font-weight: bold; margin-bottom: 5px;}
        .card-title { font-size: 1.1rem; font-weight: bold; color: #fff; margin-bottom: 10px; line-height: 1.4;}
        .card-price { font-size: 1.5rem; font-weight: 900; color: #2DD4BF; margin-bottom: 20px;}
        
        /* Seller Info Area */
        .seller-info { margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem;}
        .seller-name { color: #ccc;}
        .verified { color: #2DD4BF; font-weight: bold; font-size: 0.8rem; background: rgba(45,212,191,0.1); padding: 3px 8px; border-radius: 4px;}

        /* Buttons inside card */
        .btn-view { width: 100%; text-align: center; background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1); padding: 12px; border-radius: 10px; font-weight: bold; text-decoration: none; transition: 0.3s; margin-top: 15px;}
        .btn-view:hover { background: #2DD4BF; color: #000; border-color: #2DD4BF;}

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
        <?php if ($is_logged_in): ?>
            <a href="create_listing.php" class="btn-primary-sm">+ Post Listing</a>
            <a href="profile.php">Dashboard</a>
            <a href="logout.php">Log Out</a>
        <?php else: ?>
            <a href="login.php">Log In</a>
            <a href="register.php" class="btn-primary-sm">Sign Up</a>
        <?php endif; ?>
    </div>
</nav>

<div class="hero">
    <h1>Your campus. Your <span>deals.</span></h1>
    <p>Buy, sell, and trade directly with fellow students on your campus. No strangers. Zero platform fees.</p>
    <a href="create_listing.php" class="btn-primary">Sell Something Now</a>
</div>

<div class="container">
    <div class="section-title">
        <div class="live-dot"></div> Fresh Listings
    </div>

    <div class="grid">
        <?php if (empty($listings)): ?>
            <div class="empty-state">
                <h3>No items listed yet</h3>
                <p>Be the first to list an item on the MILELE marketplace.</p>
                <a href="create_listing.php" class="btn-primary">Post Your First Item</a>
            </div>
        <?php else: ?>
            <?php foreach ($listings as $item): ?>
                <div class="card">
                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item Image">
                    <div class="card-body">
                        <div class="card-category"><?php echo htmlspecialchars($item['category']); ?></div>
                        <div class="card-title"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div class="card-price">Ksh <?php echo number_format($item['price']); ?></div>
                        
                        <div class="seller-info">
                            <div class="seller-name">Seller: <?php echo htmlspecialchars(explode(' ', $item['full_name'])[0]); ?></div>
                            <?php if ($item['is_verified']): ?>
                                <div class="verified">✓ Verified</div>
                            <?php endif; ?>
                        </div>

                        <!-- Routes to the Item View page for Checkout -->
                        <a href="checkout.php?id=<?php echo $item['listing_id']; ?>" class="btn-view">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>