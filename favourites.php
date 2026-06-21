<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT l.*, u.full_name as seller_name, 1 as is_favorited 
    FROM favorites f
    JOIN listings l ON f.listing_id = l.listing_id
    JOIN users u ON l.seller_id = u.user_id
    WHERE f.user_id = :uid AND l.listing_status = 'active'
    ORDER BY f.created_at DESC
");
$stmt->execute([':uid' => $user_id]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favourites | MILELE</title>
    <style>
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 50px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 20px; }
        .nav-bar h1 { color: #fff; margin: 0; font-size: 2rem;}
        .btn-glass { padding: 10px 18px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; font-weight: bold;}
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }
        .item-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 24px; display: flex; flex-direction: column; position: relative;}
        .image-container { position: relative; width: 100%; margin-bottom: 20px; }
        .item-card img { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; border-radius: 16px; background: rgba(255,255,255,0.05); }
        .btn-heart { position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.1); color: #fff; width: 40px; height: 40px; border-radius: 50%; display: flex; justify-content: center; align-items: center; text-decoration: none; font-size: 1.2rem; transition: 0.2s;}
        .heart-active { color: #F87171; border-color: #F87171; }
        .item-category { font-size: 0.75rem; color: #2DD4BF; text-transform: uppercase; margin-bottom: 8px; }
        .item-title { font-weight: bold; margin-bottom: 8px; font-size: 1.25rem; color: #fff; }
        .item-price { color: #fff; font-size: 1.4rem; font-weight: bold; margin-bottom: 20px; }
        .btn-buy { display: block; text-align: center; width: 100%; padding: 14px; background: rgba(45,212,191,0.1); color: #2DD4BF; border-radius: 12px; text-decoration: none; font-weight: bold; border: 1px solid rgba(45,212,191,0.2); margin-top: auto;}
    </style>
</head>
<body>
<div class="container">
    <div class="nav-bar">
        <h1>❤️ Saved Items</h1>
        <a href="index.php" class="btn-glass">← Back to Market</a>
    </div>

    <?php if (empty($items)): ?>
        <p style="color: #666; text-align: center; padding: 50px;">You haven't saved any items yet.</p>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($items as $item): ?>
                <div class="item-card">
                    <div class="image-container">
                        <?php $image_src = !empty($item['image_path']) ? htmlspecialchars($item['image_path']) : 'https://via.placeholder.com/400'; ?>
                        <img src="<?php echo $image_src; ?>" onerror="this.onerror=null; this.src='https://via.placeholder.com/400';">
                        <a href="toggle_favorite.php?id=<?php echo $item['listing_id']; ?>" class="btn-heart heart-active">❤️</a>
                    </div>
                    <div class="item-category"><?php echo htmlspecialchars($item['category'] ?? 'Campus Goods'); ?></div>
                    <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div class="item-price">KES <?php echo number_format($item['price'], 2); ?></div>
                    <a href="item.php?id=<?php echo $item['listing_id']; ?>" class="btn-buy">View Item</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>