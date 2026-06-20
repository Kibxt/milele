<?php
// MILELE - Premium Saved Items Dashboard

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$user_id = $_SESSION['user_id'];

try {
    // Fetch only the items this specific user has saved
    $sql = "SELECT l.*, u.full_name 
            FROM saved_items s
            JOIN listings l ON s.listing_id = l.listing_id
            JOIN users u ON l.seller_id = u.user_id
            WHERE s.user_id = :uid AND l.listing_status = 'active'
            ORDER BY s.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $user_id]);
    $saved_items = $stmt->fetchAll();

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System error loading saved items.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Items | MILELE</title>
    <style>
        /* Shared Premium Glass Aesthetic */
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; min-height: 100vh; }
        
        .navbar { background: rgba(255,255,255,0.02); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .brand { font-size: 1.5rem; font-weight: 800; letter-spacing: 2px; color: #fff; text-decoration: none; }
        .brand span { color: #2DD4BF; }
        .btn-back { color: #ccc; text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: 0.2s; border: 1px solid rgba(255,255,255,0.1); padding: 8px 16px; border-radius: 12px; }
        .btn-back:hover { background: rgba(255,255,255,0.1); color: #fff; }

        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        
        .header h1 { font-size: 2.5rem; margin: 0 0 10px 0; color: #fff; }
        .header p { color: #888; font-size: 1.1rem; margin-bottom: 40px; }

        /* Grid System */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }
        
        .card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; overflow: hidden; transition: 0.3s; text-decoration: none; color: inherit; display: flex; flex-direction: column; position: relative; }
        .card:hover { border-color: rgba(45,212,191,0.3); transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        
        /* The Unsave Button on the card */
        .btn-unsave { position: absolute; top: 15px; left: 15px; background: rgba(0,0,0,0.7); backdrop-filter: blur(10px); padding: 8px; border-radius: 50%; color: #F87171; border: 1px solid rgba(248,113,113,0.2); z-index: 10; text-decoration: none; font-size: 1.2rem; display: flex; justify-content: center; align-items: center; transition: 0.2s; }
        .btn-unsave:hover { background: #F87171; color: #000; }

        .card-img { width: 100%; aspect-ratio: 4/3; background: #111; display: flex; justify-content: center; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .card-img img { width: 100%; height: 100%; object-fit: cover; }
        
        .card-body { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .card-title { font-size: 1.1rem; font-weight: bold; margin: 0 0 10px 0; line-height: 1.4; }
        .card-price { color: #2DD4BF; font-size: 1.2rem; font-weight: bold; margin-bottom: 15px; }
        .seller-name { font-size: 0.85rem; color: #888; margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.05); }
        
        .empty-state { text-align: center; padding: 100px 20px; color: #666; grid-column: 1 / -1; background: rgba(255,255,255,0.02); border-radius: 24px; border: 1px dashed rgba(255,255,255,0.1); }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="brand">MILE<span>LE</span></a>
    <a href="index.php" class="btn-back">← Back to Feed</a>
</nav>

<div class="container">
    <div class="header">
        <h1>Your Vault</h1>
        <p>Items you've bookmarked for later.</p>
    </div>

    <div class="grid">
        <?php if (empty($saved_items)): ?>
            <div class="empty-state">
                <div style="font-size: 3rem; margin-bottom: 15px;">🔖</div>
                <h2>Your vault is empty.</h2>
                <p>When you see an item you like on the feed, save it, and it will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($saved_items as $item): ?>
                <div class="card">
                    <a href="toggle_save.php?id=<?php echo $item['listing_id']; ?>" class="btn-unsave" title="Remove from Saved">✖</a>
                    
                    <a href="item.php?id=<?php echo $item['listing_id']; ?>" style="text-decoration: none; color: inherit; display: flex; flex-direction: column; height: 100%;">
                        <div class="card-img">
                            <?php if (!empty($item['file_path']) && $item['item_type'] === 'physical'): ?>
                                <img src="<?php echo htmlspecialchars($item['file_path']); ?>" alt="Cover">
                            <?php else: ?>
                                <div style="color: #444; font-size: 2rem;">
                                    <?php echo $item['item_type'] === 'digital' ? '📄' : '📷'; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                            <div class="card-price">KES <?php echo number_format($item['price'], 2); ?></div>
                            <div class="seller-name">Sold by <?php echo htmlspecialchars(explode(' ', $item['full_name'])[0]); ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>