<?php
// MILELE - Premium Global Feed

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if (isset($_SESSION['account_state']) && $_SESSION['account_state'] === 'registered') { header("Location: verification_center.php"); exit(); }

require 'db.php';

try {
    // Fetch all active listings
    $sql = "SELECT l.*, u.full_name, u.university_name 
            FROM listings l 
            JOIN users u ON l.seller_id = u.user_id 
            WHERE l.listing_status = 'active' 
            ORDER BY l.created_at DESC LIMIT 50";
    $live_listings = $pdo->query($sql)->fetchAll();
} catch (Exception $e) {
    $live_listings = [];
}

$first_name = explode(' ', $_SESSION['full_name'] ?? 'Student')[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace | MILELE</title>
    <style>
        /* Ultra-Premium Glass Aesthetic */
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; min-height: 100vh; }
        
        /* Top Navigation */
        .navbar { background: rgba(255,255,255,0.02); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .brand { font-size: 1.5rem; font-weight: 800; letter-spacing: 2px; color: #fff; text-decoration: none; }
        .brand span { color: #2DD4BF; }
        
        .nav-links { display: flex; gap: 20px; align-items: center; }
        .nav-item { color: #ccc; text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: 0.2s; }
        .nav-item:hover { color: #2DD4BF; }
        .btn-studio { background: #2DD4BF; color: #000; padding: 10px 20px; border-radius: 12px; font-weight: bold; text-decoration: none; transition: 0.2s; }
        .btn-studio:hover { background: #fff; transform: translateY(-2px); }

        /* Main Content */
        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        
        .feed-header { margin-bottom: 40px; }
        .feed-header h1 { font-size: 2.5rem; margin: 0 0 10px 0; background: linear-gradient(135deg, #fff 0%, #aaa 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .feed-header p { color: #888; font-size: 1.1rem; margin: 0; }

        /* Grid System */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }
        
        .card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; overflow: hidden; transition: 0.3s; text-decoration: none; color: inherit; display: flex; flex-direction: column; }
        .card:hover { border-color: rgba(45,212,191,0.3); transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        
        .card-img { width: 100%; aspect-ratio: 4/3; background: #111; display: flex; justify-content: center; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); position: relative; }
        .card-img img { width: 100%; height: 100%; object-fit: cover; }
        
        .type-badge { position: absolute; top: 15px; right: 15px; background: rgba(0,0,0,0.7); backdrop-filter: blur(10px); padding: 5px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: bold; color: #2DD4BF; border: 1px solid rgba(45,212,191,0.2); }

        .card-body { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .card-title { font-size: 1.1rem; font-weight: bold; margin: 0 0 10px 0; line-height: 1.4; }
        .card-price { color: #2DD4BF; font-size: 1.2rem; font-weight: bold; margin-bottom: 15px; }
        
        .card-footer { margin-top: auto; display: flex; align-items: center; gap: 10px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.05); }
        .seller-name { font-size: 0.85rem; color: #888; }
        
        .empty-state { text-align: center; padding: 100px 20px; color: #666; grid-column: 1 / -1; }

        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .nav-item { display: none; } /* Hide text links on mobile, keep studio button */
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="brand">MILE<span>LE</span></a>
    <div class="nav-links">
        <a href="saved.php" class="nav-item">Saved</a>
        <a href="inbox.php" class="nav-item">Inbox</a>
        <a href="profile.php" class="nav-item">Profile</a>
        <a href="Notesing.php" class="btn-studio">+ Create</a>
    </div>
</nav>

<div class="container">
    <div class="feed-header">
        <h1>Welcome, <?php echo htmlspecialchars($first_name); ?>.</h1>
        <p>Discover notes, textbooks, and gear from students across campus.</p>
    </div>

    <div class="grid">
        <?php if (empty($live_listings)): ?>
            <div class="empty-state">
                <div style="font-size: 3rem; margin-bottom: 15px;">🌐</div>
                <h2>The feed is quiet...</h2>
                <p>Be the first to post an item to the marketplace.</p>
            </div>
        <?php else: ?>
            <?php foreach ($live_listings as $item): ?>
                <a href="item.php?id=<?php echo $item['listing_id']; ?>" class="card">
                    <div class="card-img">
                        <div class="type-badge"><?php echo ucfirst($item['item_type']); ?></div>
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
                        <div class="card-footer">
                            <span class="seller-name">Sold by <?php echo htmlspecialchars(explode(' ', $item['full_name'])[0]); ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>