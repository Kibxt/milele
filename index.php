<?php
// MILELE - Premium Global Feed (With Search & Filters)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

// Capture search and category parameters
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS);
$category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS);

try {
    // Dynamic Query Builder
    $sql = "SELECT l.*, u.university_name FROM listings l JOIN users u ON l.seller_id = u.user_id WHERE l.listing_status = 'active'";
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
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System Error Loading Feed.</div>");
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
        
        /* Navigation */
        .nav-bar { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(5,5,5,0.8); backdrop-filter: blur(20px); position: sticky; top: 0; z-index: 100;}
        .brand { font-size: 1.8rem; font-weight: 800; color: #2DD4BF; text-decoration: none; letter-spacing: -1px;}
        .nav-actions { display: flex; gap: 15px; }
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; transition: 0.3s; font-size: 0.95rem; font-weight: bold;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }
        .btn-accent { background: #2DD4BF; color: #000; border: none; }
        .btn-accent:hover { background: #fff; }

        /* Hero & Search Engine */
        .hero { padding: 80px 20px 60px; text-align: center; background: radial-gradient(circle at 50% -20%, rgba(45,212,191,0.1), transparent 50%); }
        .hero h1 { font-size: 3.5rem; margin: 0 0 15px 0; line-height: 1.1; }
        .hero p { color: #888; font-size: 1.1rem; margin-bottom: 40px; }
        
        .search-form { max-width: 600px; margin: 0 auto; display: flex; gap: 10px; }
        .search-input { flex-grow: 1; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 18px 24px; border-radius: 16px; color: #fff; font-size: 1.1rem; outline: none; transition: 0.3s;}
        .search-input:focus { border-color: #2DD4BF; background: rgba(255,255,255,0.08);}
        .search-btn { padding: 0 30px; background: #2DD4BF; color: #000; border: none; border-radius: 16px; font-weight: bold; font-size: 1rem; cursor: pointer; transition: 0.2s;}
        .search-btn:hover { background: #fff; transform: translateY(-2px);}

        /* Category Pills */
        .categories { display: flex; justify-content: center; gap: 10px; padding: 0 20px 40px; flex-wrap: wrap; }
        .cat-pill { padding: 10px 20px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); color: #888; text-decoration: none; border-radius: 30px; font-size: 0.9rem; transition: 0.2s; white-space: nowrap;}
        .cat-pill:hover, .cat-pill.active { background: rgba(45,212,191,0.1); color: #2DD4BF; border-color: rgba(45,212,191,0.3); }

        /* The Premium Grid */
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px 80px; flex-grow: 1; width: 100%; box-sizing: border-box;}
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px; }
        
        .card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; overflow: hidden; transition: 0.3s; display: flex; flex-direction: column; text-decoration: none;}
        .card:hover { transform: translateY(-5px); border-color: rgba(45,212,191,0.3); box-shadow: 0 10px 30px rgba(0,0,0,0.5);}
        
        .card-img { width: 100%; aspect-ratio: 1/1; object-fit: cover; background: #111; border-bottom: 1px solid rgba(255,255,255,0.05);}
        
        .card-body { padding: 20px; display: flex; flex-direction: column; flex-grow: 1; }
        .card-cat { font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .card-title { font-size: 1.1rem; font-weight: bold; color: #fff; margin: 0 0 10px 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;}
        .card-price { font-size: 1.3rem; color: #2DD4BF; font-weight: bold; margin-bottom: 15px;}
        
        .card-footer { margin-top: auto; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px;}
        .card-uni { font-size: 0.8rem; color: #666; display: flex; align-items: center; gap: 5px;}
        .btn-view { padding: 6px 12px; background: rgba(255,255,255,0.05); color: #fff; border-radius: 8px; font-size: 0.85rem; font-weight: bold;}
        .card:hover .btn-view { background: #2DD4BF; color: #000; }

        /* Empty State */
        .empty-state { text-align: center; padding: 80px 20px; color: #666; }
        .empty-state h2 { color: #fff; margin-bottom: 10px;}

        @media (max-width: 768px) {
            .nav-bar { padding: 15px 20px; }
            .hero h1 { font-size: 2.5rem; }
            .search-form { flex-direction: column; }
            .search-btn { padding: 18px; }
        }
    </style>
</head>
<body>

<nav class="nav-bar">
    <a href="index.php" class="brand">MILELE</a>
    <div class="nav-actions">
        <a href="post_item.php" class="btn-glass btn-accent">+ Sell</a>
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="profile.php" class="btn-glass">Profile</a>
        <?php else: ?>
            <a href="login.php" class="btn-glass">Login</a>
        <?php endif; ?>
    </div>
</nav>

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
        // Build dynamic link preserving search query
        $link = "?category=" . urlencode($cat);
        if ($search) $link .= "&search=" . urlencode($search);
    ?>
        <a href="<?php echo $link; ?>" class="cat-pill <?php echo ($category === $cat) ? 'active' : ''; ?>">
            <?php echo $cat; ?>
        </a>
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
            <?php foreach ($items as $item): ?>
                <a href="item.php?id=<?php echo $item['listing_id']; ?>" class="card">
                    <?php $img = !empty($item['image_path']) ? htmlspecialchars($item['image_path']) : 'https://via.placeholder.com/400x400/111111/333333?text=MILELE'; ?>
                    <img src="<?php echo $img; ?>" loading="lazy" class="card-img" alt="Item">
                    
                    <div class="card-body">
                        <div class="card-cat"><?php echo htmlspecialchars($item['category']); ?></div>
                        <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <div class="card-price">KES <?php echo number_format($item['price'], 2); ?></div>
                        
                        <div class="card-footer">
                            <div class="card-uni">🎓 <?php echo htmlspecialchars(explode(' ', $item['university_name'])[0]); ?></div>
                            <div class="btn-view">View</div>
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