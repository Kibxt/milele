<?php
// MILELE - Detailed Item Page (Cloud Image Supported & Messaging Wired)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

$listing_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$current_user_id = $_SESSION['user_id'] ?? null;

if (!$listing_id) {
    header("Location: index.php");
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name as seller_name, u.university_name 
        FROM listings l 
        JOIN users u ON l.seller_id = u.user_id 
        WHERE l.listing_id = :id AND l.listing_status = 'active'
    ");
    $stmt->execute([':id' => $listing_id]);
    $item = $stmt->fetch();

    if (!$item) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:sans-serif;'>This item is no longer available.</div>");
    }

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center; font-family:sans-serif;'>System error loading item.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['title']); ?> | MILELE</title>
    <style>
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; transition: 0.3s; font-size: 0.9rem; }
        .btn-glass:hover { background: rgba(255,255,255,0.1); }

        .product-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 32px; padding: 40px; }
        
        @media (max-width: 768px) {
            .product-grid { grid-template-columns: 1fr; padding: 20px; }
        }

        /* Large Premium Image Display */
        .product-image { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; border-radius: 24px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); }

        .product-info { display: flex; flex-direction: column; }
        .category-badge { color: #2DD4BF; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; font-weight: bold; }
        .title { font-size: 2.5rem; margin: 0 0 15px 0; color: #fff; line-height: 1.2; }
        .price { font-size: 2rem; color: #2DD4BF; font-weight: bold; margin-bottom: 30px; }
        
        .seller-box { background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.05); padding: 15px 20px; border-radius: 16px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;}
        .seller-name { font-weight: bold; color: #fff; display: block; margin-bottom: 5px; }
        .seller-uni { color: #888; font-size: 0.85rem; }
        .btn-msg { background: rgba(255,255,255,0.1); color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; transition: 0.2s;}
        .btn-msg:hover { background: #fff; color: #000; }

        .description { color: #ccc; line-height: 1.6; margin-bottom: 40px; white-space: pre-wrap; flex-grow: 1;}

        .btn-buy { width: 100%; padding: 18px; background: #2DD4BF; color: #000; border: none; border-radius: 16px; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: 0.2s; text-align: center; text-decoration: none; display: block; box-sizing: border-box;}
        .btn-buy:hover { background: #fff; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(45,212,191,0.2); }
        .btn-disabled { background: rgba(255,255,255,0.1); color: #888; cursor: not-allowed; }
        .btn-disabled:hover { background: rgba(255,255,255,0.1); transform: none; box-shadow: none; }
    </style>
</head>
<body>

<div class="container">
    <div class="nav-bar">
        <a href="index.php" class="btn-glass">← Back to Market</a>
    </div>

    <div class="product-grid">
        <div>
            <?php $image_src = !empty($item['image_path']) ? htmlspecialchars($item['image_path']) : 'https://via.placeholder.com/800x800/111111/333333?text=MILELE'; ?>
            <img src="<?php echo $image_src; ?>" onerror="this.onerror=null; this.src='https://via.placeholder.com/800x800/111111/333333?text=No+Photo';" class="product-image" alt="Item Image">
        </div>

        <div class="product-info">
            <div class="category-badge"><?php echo htmlspecialchars($item['category'] ?? 'Campus Goods'); ?></div>
            <h1 class="title"><?php echo htmlspecialchars($item['title']); ?></h1>
            <div class="price">KES <?php echo number_format($item['price'], 2); ?></div>

            <div class="seller-box">
                <div>
                    <span class="seller-name"><?php echo htmlspecialchars($item['seller_name']); ?></span>
                    <span class="seller-uni">🎓 <?php echo htmlspecialchars($item['university_name']); ?></span>
                </div>
                <?php if ($current_user_id !== $item['seller_id']): ?>
                    <a href="inbox.php?user=<?php echo $item['seller_id']; ?>&listing=<?php echo $item['listing_id']; ?>" class="btn-msg">💬 Chat with Seller</a>
                <?php endif; ?>
            </div>

            <div class="description"><?php echo htmlspecialchars($item['description']); ?></div>

            <?php if ($current_user_id === $item['seller_id']): ?>
                <a href="edit_listing.php?id=<?php echo $item['listing_id']; ?>" class="btn-buy" style="background: rgba(255,255,255,0.1); color: #fff;">Edit My Item</a>
            <?php elseif (!$current_user_id): ?>
                <a href="login.php" class="btn-buy btn-disabled">Log in to Purchase</a>
            <?php else: ?>
                <a href="checkout.php?id=<?php echo $item['listing_id']; ?>" class="btn-buy">Purchase Securely</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>