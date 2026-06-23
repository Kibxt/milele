<?php
// MILELE - Premium Global Feed (With Profile Pictures & Auto-Patch)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS);
$category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS);

// ==========================================
// 🛠️ SILENT DATABASE UPGRADE
// ==========================================
// If the user visits the feed before their profile, this ensures the column exists!
try { $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e) {}

// 🔔 UNREAD MESSAGE TRACKER
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt_msg = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = 0");
        $stmt_msg->execute([':uid' => $_SESSION['user_id']]);
        $unread_count = $stmt_msg->fetchColumn();
    } catch (PDOException $e) {
        $unread_count = 0; 
    }
}

// ==========================================
// 🛒 LOAD FEED ITEMS 
// ==========================================
try {
    $sql = "SELECT l.*, u.university_name, u.full_name, u.profile_picture FROM listings l JOIN users u ON l.seller_id = u.user_id WHERE l.listing_status = 'active'";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (l.title LIKE :search OR l.description LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if (!empty($category)) {
        $sql .= " AND l.category = :category";
        $params[':category'] = $category;
    }

    $sql .= " ORDER BY l.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

} catch (PDOException $e) {
    // UNMASKED ERROR: If it fails again, it will tell us exactly why.
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'><strong>System Error Loading Feed:</strong> " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MILELE | Campus Marketplace</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; min-height: 100vh; display: flex; flex-direction: column;}
        
        .nav-bar { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(5,5,5,0.8); backdrop-filter: blur(20px); position: sticky; top: 0; z-index: 100;}
        .brand { font-size: 1.8rem; font-weight: 800; color: #2DD4BF; text-decoration: none; letter-spacing: -1px;}
        .nav-actions { display: flex; gap: 15px; align-items: center;}
        
        .btn-glass { position: relative; padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; transition: 0.3s; font-size: 0.95rem; font-weight: bold;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }
        .btn-accent { background: #2DD4BF; color: #000; border: none; }
        .btn-accent:hover { background: #fff; }

        .notif-badge { position: absolute; top: -6px; right: -6px; background: #EF4444; color: #fff; font-size: 0.7rem; font-weight: bold; padding: 2px 6px; border-radius: 10px; border: 2px solid #050505; animation: pulse 2s infinite; pointer-events: none;}
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .toast-alert { position: fixed; bottom: -100px; right: 20px; background: rgba(20,20,20,0.9); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); padding: 15px 20px; border-radius: 16px; display: flex; align-items: center; gap: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.8); transition: bottom 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 1000; min-width: 250px;}
        .toast-alert.show { bottom: 20px; }

        .hero { padding: 80px 20px 60px; text-align: center; background: radial-gradient(circle at 50% -20%, rgba(45,212,191,0.1), transparent 50%); }
        .hero h1 { font-size: 3.5rem; margin: 0 0 15px 0; line-height: 1.1; }
        .hero p { color: #888; font-size: 1.1rem; margin-bottom: 40px; }
        
        .search-form { max-width: 600px; margin: 0 auto; display: flex; gap: 10px; }
        .search-input { flex-grow: 1; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 18px 24px; border-radius: 16px; color: #fff; font-size: 1.1rem; outline: none; transition: 0.3s;}
        .search-input:focus { border-color: #2DD4BF; background: rgba(255,255,255,0.08);}
        .search-btn { padding: 0 30px; background: #2DD4BF; color: #000; border: none; border-radius: 16px; font-weight: bold; font-size: 1rem; cursor: pointer; transition: 0.2s;}
        .search-btn:hover { background: #fff; transform: translateY(-2px);}

        .categories { display: flex; justify-content: center; gap: 10px; padding: 0 20px 40px; flex-wrap: wrap; }
        .cat-pill { padding: 10px 20px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); color: #888; text-decoration: none; border-radius: 30px; font-size: 0.9rem; transition: 0.2s; white-space: nowrap;}
        .cat-pill:hover, .cat-pill.active { background: rgba(45,212,191,0.1); color: #2DD4BF; border-color: rgba(45,212,191,0.3); }

        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px 80px; flex-grow: 1; width: 100%; box-sizing: border-box;}
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }
        
        .card { background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0) 100%); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; overflow: hidden; transition: transform 0.3s, box-shadow 0.3s; display: flex; flex-direction: column; text-decoration: none; position: relative;}
        .card:hover { transform: translateY(-5px); border-color: rgba(45,212,191,0.3); box-shadow: 0 20px 40px rgba(0,0,0,0.4);}
        
        .floating-badges { position: absolute; top: 15px; left: 15px; right: 15px; display: flex; justify-content: space-between; z-index: 10; pointer-events: none;}
        .glass-badge { background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.1); padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; color: #fff; text-transform: uppercase; letter-spacing: 1px;}
        .badge-cat { color: #2DD4BF; border-color: rgba(45,212,191,0.3); }

        .gallery-track { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; -ms-overflow-style: none; width: 100%; aspect-ratio: 1/1; background: radial-gradient(circle, #1a1a1a 0%, #050505 100%); border-bottom: 1px solid rgba(255,255,255,0.05);}
        .gallery-track::-webkit-scrollbar { display: none; } 
        .gallery-img { flex: 0 0 100%; width: 100%; height: 100%; object-fit: contain; scroll-snap-align: center; pointer-events: none;}
        
        .swipe-hint { position: absolute; bottom: 120px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; color: #aaa; pointer-events: none; opacity: 0; transition: 0.3s;}
        .card:hover .swipe-hint { opacity: 1; }

        .card-body { padding: 25px 20px 20px; display: flex; flex-direction: column; flex-grow: 1; }
        .card-title { font-size: 1.15rem; font-weight: bold; color: #fff; margin: 0 0 10px 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4;}
        .card-price { font-size: 1.4rem; color: #2DD4BF; font-weight: bold; margin-bottom: 15px;}
        
        .card-footer { margin-top: auto; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px;}
        
        .seller-info { display: flex; align-items: center; gap: 10px;}
        .seller-avatar { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(45,212,191,0.5); background: #111; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: bold; color: #2DD4BF;}
        .seller-details { display: flex; flex-direction: column; line-height: 1.2;}
        .seller-name { font-size: 0.85rem; font-weight: bold; color: #fff;}
        .card-uni { font-size: 0.75rem; color: #888;}
        
        .btn-view { padding: 8px 16px; background: rgba(255,255,255,0.05); color: #fff; border-radius: 10px; font-size: 0.85rem; font-weight: bold; transition: 0.2s;}
        .card:hover .btn-view { background: #2DD4BF; color: #000; }

        .empty-state { text-align: center; padding: 80px 20px; color: #666; }
        .empty-state h2 { color: #fff; margin-bottom: 10px;}

        @media (max-width: 768px) {
            .nav-bar { padding: 15px 20px; }
            .hero h1 { font-size: 2.5rem; }
            .search-form { flex-direction: column; }
            .search-btn { padding: 18px; }
            .swipe-hint { opacity: 1; } 
            .nav-actions { gap: 10px; }
            .btn-glass { padding: 8px 12px; font-size: 0.85rem; }
            .brand { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

<nav class="nav-bar">
    <a href="index.php" class="brand">MILELE</a>
    <div class="nav-actions">
        <a href="post_item.php" class="btn-glass btn-accent">+ Sell</a>
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="inbox.php" class="btn-glass">
                Inbox
                <?php if($unread_count > 0): ?>
                    <span class="notif-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php" class="btn-glass">Profile</a>
        <?php else: ?>
            <a href="login.php" class="btn-glass">Login</a>
        <?php endif; ?>
    </div>
</nav>

<?php if($unread_count > 0): ?>
    <div class="toast-alert" id="msgToast">
        <div style="font-size: 1.8rem; line-height: 1;">💬</div>
        <div style="flex-grow: 1;">
            <div style="font-weight: bold; color: #fff; font-size: 0.95rem;">New Messages</div>
            <div style="color: #aaa; font-size: 0.8rem;">You have <?php echo $unread_count; ?> unread message(s).</div>
        </div>
        <a href="inbox.php" style="color: #2DD4BF; font-weight: bold; text-decoration: none; font-size: 0.9rem; padding: 5px;">View</a>
    </div>
    <script>
        setTimeout(() => { document.getElementById('msgToast').classList.add('show'); }, 1000);
        setTimeout(() => { document.getElementById('msgToast').classList.remove('show'); }, 6000);
    </script>
<?php endif; ?>

<header class="hero">
    <h1>The Campus Market.</h1>
    <p>Zero Scams. Bulletproof Escrow. Exclusive to Students.</p>
    
    <form action="index.php" method="GET" class="search-form">
        <?php if($category): ?><input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>"><?php endif; ?>
        <input type="text" name="search" class="search-input" placeholder="Search for laptops, textbooks, shoes..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="search-btn">Search</button>
    </form>
</header>

<div class="categories">
    <a href="index.php<?php echo $search ? '?search='.urlencode($search) : ''; ?>" class="cat-pill <?php echo empty($category) ? 'active' : ''; ?>">All Items</a>
    <?php 
    $cats = ['Electronics', 'Textbooks', 'Fashion', 'Dorm Essentials', 'Services', 'Other'];
    foreach ($cats as $cat): 
        $link = "?category=" . urlencode($cat);
        if ($search) $link .= "&search=" . urlencode($search);
    ?>
        <a href="<?php echo $link; ?>" class="cat-pill <?php echo ($category === $cat) ? 'active' : ''; ?>"><?php echo $cat; ?></a>
    <?php endforeach; ?>
</div>

<main class="container">
    <?php if (empty($items)): ?>
        <div class="empty-state">
            <h2>No items found</h2>
            <p>We couldn't find any listings matching your current search or category.</p>
            <a href="index.php" style="color: #2DD4BF; text-decoration: none; font-weight: bold; margin-top: 20px; display: inline-block;">Clear Filters</a>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($items as $item): 
                $images = [];
                $decoded_images = json_decode($item['image_path'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_images) && count($decoded_images) > 0) {
                    $images = $decoded_images;
                } else {
                    $images[] = !empty($item['image_path']) ? $item['image_path'] : 'https://via.placeholder.com/400x400/111111/333333?text=MILELE';
                }
            ?>
                <a href="item.php?id=<?php echo $item['listing_id']; ?>" class="card">
                    <div class="floating-badges">
                        <span class="glass-badge badge-cat"><?php echo htmlspecialchars($item['category']); ?></span>
                        <?php if(isset($item['item_type'])): ?>
                            <span class="glass-badge"><?php echo $item['item_type'] == 'Digital' ? '📄 Digital' : '📦 Physical'; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="gallery-track">
                        <?php foreach ($images as $img): ?>
                            <img src="<?php echo htmlspecialchars($img); ?>" loading="lazy" class="gallery-img" alt="Item Image">
                        <?php endforeach; ?>
                    </div>

                    <?php if(count($images) > 1): ?>
                        <div class="swipe-hint">↔ Swipe for more</div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <div class="card-price">KES <?php echo number_format($item['price'], 2); ?></div>
                        
                        <div class="card-footer">
                            <div class="seller-info">
                                <?php if($item['profile_picture']): ?>
                                    <img src="<?php echo htmlspecialchars($item['profile_picture']); ?>" class="seller-avatar" alt="Avatar">
                                <?php else: ?>
                                    <div class="seller-avatar"><?php echo strtoupper(substr($item['full_name'], 0, 1)); ?></div>
                                <?php endif; ?>
                                
                                <div class="seller-details">
                                    <span class="seller-name"><?php echo htmlspecialchars(explode(' ', $item['full_name'])[0]); ?></span>
                                    <span class="card-uni">🎓 <?php echo htmlspecialchars(explode(' ', $item['university_name'])[0]); ?></span>
                                </div>
                            </div>
                            
                            <div class="btn-view">Details</div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>

</body>
</html>