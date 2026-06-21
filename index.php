<?php
// MILELE - Global Marketplace Feed

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

try {
    // Fetch all active items, newest first
    $stmt = $pdo->query("
        SELECT l.*, u.full_name as seller_name 
        FROM listings l 
        JOIN users u ON l.seller_id = u.user_id 
        WHERE l.listing_status = 'active' 
        ORDER BY l.created_at DESC
    ");
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System error loading market.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace | MILELE</title>
    <style>
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 50px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 20px; }
        .nav-bar h1 { color: #2DD4BF; margin: 0; font-size: 2.5rem; letter-spacing: -1px; font-weight: 800;}
        .nav-buttons { display: flex; gap: 15px; }
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; transition: 0.3s; font-size: 0.95rem; font-weight: bold;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }
        .btn-accent { background: #2DD4BF; color: #000; border: none; }
        .btn-accent:hover { background: #fff; }

        .header-section { text-align: center; margin-bottom: 60px; }
        .header-section h2 { font-size: 2.5rem; margin: 0 0 15px 0; }
        .header-section p { color: #888; font-size: 1.1rem; max-width: 600px; margin: 0 auto; }

        /* The Premium Grid */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }
        .item-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 24px; transition: 0.3s; display: flex; flex-direction: column; }
        .item-card:hover { border-color: rgba(45,212,191,0.3); transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        
        /* The Uniform Image Fix */
        .item-card img {
            width: 100%;
            aspect-ratio: 1 / 1; 
            object-fit: cover; 
            border-radius: 16px;
            margin-bottom: 20px;
            background: rgba(255,255,255,0.05); 
        }

        .item-category { font-size: 0.75rem; color: #2DD4BF; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .item-title { font-weight: bold; margin-bottom: 8px; font-size: 1.25rem; color: #fff; }
        .item-seller { font-size: 0.85rem; color: #666; margin-bottom: 15px; }
        .item-price { color: #fff; font-size: 1.4rem; font-weight: bold; margin-bottom: 20px; }
        
        .btn-buy { display: block; text-align: center; width: 100%; padding: 14px; background: rgba(45,212,191,0.1); color: #2DD4BF; border-radius: 12px; text-decoration: none; font-weight: bold; font-size: 1rem; transition: 0.2s; border: 1px solid rgba(45,212,191,0.2); box-sizing: border-box;}
        .btn-buy:hover { background: #2DD4BF; color: #000; }
    </style>
</head>
<body>

<div class="container">
    <div class="nav-bar">
        <h1>MILELE</h1>
        <div class="nav-buttons">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="post_item.php" class="btn-glass">Sell an Item</a>
                <a href="profile.php" class="btn-glass btn-accent">My Profile</a>
            <?php else: ?>
                <a href="login.php" class="btn-glass">Log In</a>
                <a href="register.php" class="btn-glass btn-accent">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="header-section">
        <h2>The Campus Escrow Market</h2>
        <p>Buy and sell safely. Funds are locked in the Safaricom cloud until you meet up and inspect the item.</p>
    </div>

    <?php if (empty($items)): ?>
        <p style="color: #666; text-align: center; padding: 50px; background: rgba(255,255,255,0.02); border-radius: 24px;">The marketplace is currently empty. Be the first to post an item!</p>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($items as $item): ?>
                <div class="item-card">
                    <img src="https://via.placeholder.com/400x400/111111/333333?text=MILELE+Item" alt="Item Image">
                    
                    <div class="item-category">Campus Goods</div>
                    <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div class="item-seller">Seller: <?php echo htmlspecialchars(explode(' ', $item['seller_name'])[0]); ?></div>
                    <div class="item-price">KES <?php echo number_format($item['price'], 2); ?></div>
                    
                    <a href="checkout.php?id=<?php echo $item['listing_id']; ?>" class="btn-buy">Purchase Securely</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>