<?php
// MILELE - Premium Item Details & Escrow Bridge

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

$item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$my_id = $_SESSION['user_id'] ?? null;

if (!$item_id) {
    header("Location: index.php");
    exit();
}

try {
    // Fetch item + seller details + profile picture
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name, u.university_name, u.profile_picture, u.account_state 
        FROM listings l 
        JOIN users u ON l.seller_id = u.user_id 
        WHERE l.listing_id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();

    if (!$item) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>Item not found or has been deleted.</div>");
    }

    // Decode Image Gallery
    $images = [];
    $decoded_images = json_decode($item['image_path'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_images) && count($decoded_images) > 0) {
        $images = $decoded_images;
    } else {
        $images[] = !empty($item['image_path']) ? $item['image_path'] : 'https://via.placeholder.com/600x600/111111/333333?text=No+Image';
    }

    $is_seller = ($my_id == $item['seller_id']);
    $is_active = ($item['listing_status'] === 'active');

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['title']); ?> | MILELE</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; min-height: 100vh; display: flex; flex-direction: column;}
        
        .nav-bar { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(5,5,5,0.8); backdrop-filter: blur(20px); position: sticky; top: 0; z-index: 100;}
        .brand { font-size: 1.8rem; font-weight: 800; color: #2DD4BF; text-decoration: none; letter-spacing: -1px;}
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; font-weight: bold; transition: 0.3s;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }

        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 50px; flex-grow: 1;}
        
        /* Left: Gallery */
        .gallery-box { display: flex; flex-direction: column; gap: 15px;}
        .main-img-container { background: radial-gradient(circle, #1a1a1a 0%, #050505 100%); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; overflow: hidden; aspect-ratio: 1/1; display: flex; align-items: center; justify-content: center; position: relative;}
        .main-img { width: 100%; height: 100%; object-fit: contain; transition: opacity 0.3s;}
        
        .thumbnail-track { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px;}
        .thumbnail-track::-webkit-scrollbar { height: 6px; }
        .thumbnail-track::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px;}
        .thumb-img { width: 80px; height: 80px; border-radius: 12px; object-fit: cover; cursor: pointer; border: 2px solid transparent; transition: 0.2s; background: #111; opacity: 0.6;}
        .thumb-img:hover { opacity: 1; }
        .thumb-img.active { border-color: #2DD4BF; opacity: 1;}

        /* Right: Details */
        .details-box { display: flex; flex-direction: column;}
        .badges { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;}
        .badge { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 6px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; color: #aaa; text-transform: uppercase; letter-spacing: 1px;}
        .badge-cat { color: #2DD4BF; border-color: rgba(45,212,191,0.3); background: rgba(45,212,191,0.05);}
        
        .item-title { font-size: 2.5rem; margin: 0 0 10px 0; line-height: 1.2;}
        .item-price { font-size: 2.5rem; color: #2DD4BF; font-weight: bold; margin-bottom: 30px;}
        
        .desc-box { background: rgba(255,255,255,0.02); padding: 20px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 30px; line-height: 1.6; color: #ddd; font-size: 1.05rem; white-space: pre-wrap;}

        /* Seller Card */
        .seller-card { background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.08); padding: 20px; border-radius: 20px; display: flex; align-items: center; gap: 15px; text-decoration: none; transition: 0.3s; margin-bottom: 30px;}
        .seller-card:hover { border-color: rgba(45,212,191,0.3); background: rgba(45,212,191,0.02);}
        .seller-avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #2DD4BF; background: #111; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; color: #2DD4BF;}
        .seller-info { flex-grow: 1;}
        .seller-name { font-size: 1.2rem; font-weight: bold; color: #fff; margin: 0 0 5px 0;}
        .verified-tick { color: #3B82F6; font-size: 1rem; }
        .seller-uni { color: #888; font-size: 0.9rem;}

        /* Actions */
        .action-area { display: flex; flex-direction: column; gap: 15px;}
        .btn-buy { background: #2DD4BF; color: #000; padding: 20px; border-radius: 16px; text-align: center; font-weight: bold; font-size: 1.2rem; text-decoration: none; transition: 0.3s; border: none; cursor: pointer;}
        .btn-buy:hover { background: #fff; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(45,212,191,0.2);}
        .btn-msg { background: rgba(255,255,255,0.05); color: #fff; padding: 20px; border-radius: 16px; text-align: center; font-weight: bold; font-size: 1.1rem; text-decoration: none; transition: 0.3s; border: 1px solid rgba(255,255,255,0.1);}
        .btn-msg:hover { background: rgba(255,255,255,0.1); border-color: #fff;}

        .status-banner { padding: 20px; border-radius: 16px; text-align: center; font-weight: bold; font-size: 1.2rem; margin-bottom: 15px;}
        .status-escrow { background: rgba(245,158,11,0.1); color: #F59E0B; border: 1px solid rgba(245,158,11,0.3);}
        .status-sold { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3);}
        .status-owner { background: rgba(255,255,255,0.05); color: #aaa; border: 1px solid rgba(255,255,255,0.1);}

        @media (max-width: 768px) {
            .container { grid-template-columns: 1fr; gap: 30px;}
            .item-title { font-size: 2rem; }
        }
    </style>
</head>
<body>

<nav class="nav-bar">
    <a href="index.php" class="brand">MILELE</a>
    <a href="javascript:history.back()" class="btn-glass">← Back</a>
</nav>

<div class="container">
    
    <div class="gallery-box">
        <div class="main-img-container">
            <?php if(!$is_active): ?>
                <div style="position:absolute; top:20px; right:20px; background:rgba(0,0,0,0.8); backdrop-filter:blur(5px); padding:8px 15px; border-radius:12px; font-weight:bold; color: <?php echo $item['listing_status'] === 'sold' ? '#F87171' : '#F59E0B'; ?>; border: 1px solid currentColor; z-index:10;">
                    <?php echo $item['listing_status'] === 'sold' ? 'Sold' : 'In Escrow'; ?>
                </div>
            <?php endif; ?>
            <img src="<?php echo htmlspecialchars($images[0]); ?>" id="mainImage" class="main-img" alt="Product Image">
        </div>

        <?php if(count($images) > 1): ?>
            <div class="thumbnail-track">
                <?php foreach($images as $index => $img): ?>
                    <img src="<?php echo htmlspecialchars($img); ?>" class="thumb-img <?php echo $index === 0 ? 'active' : ''; ?>" onclick="swapImage(this, '<?php echo htmlspecialchars($img); ?>')" alt="Thumbnail">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="details-box">
        <div class="badges">
            <span class="badge badge-cat"><?php echo htmlspecialchars($item['category']); ?></span>
            <?php if(isset($item['item_type'])): ?>
                <span class="badge"><?php echo $item['item_type']; ?></span>
            <?php endif; ?>
        </div>

        <h1 class="item-title"><?php echo htmlspecialchars($item['title']); ?></h1>
        <div class="item-price">KES <?php echo number_format($item['price'], 2); ?></div>

        <a href="public_profile.php?id=<?php echo $item['seller_id']; ?>" class="seller-card">
            <?php if($item['profile_picture']): ?>
                <img src="<?php echo htmlspecialchars($item['profile_picture']); ?>" class="seller-avatar" alt="Avatar">
            <?php else: ?>
                <div class="seller-avatar"><?php echo strtoupper(substr($item['full_name'], 0, 1)); ?></div>
            <?php endif; ?>
            
            <div class="seller-info">
                <div class="seller-name">
                    <?php echo htmlspecialchars($item['full_name']); ?>
                    <?php if($item['account_state'] === 'campus_verified'): ?>
                        <span class="verified-tick" title="Campus Verified User">✔️</span>
                    <?php endif; ?>
                </div>
                <div class="seller-uni">🎓 <?php echo htmlspecialchars($item['university_name']); ?></div>
            </div>
            <div style="color:#888;">➔</div>
        </a>

        <div class="desc-box"><?php echo nl2br(htmlspecialchars($item['description'])); ?></div>

        <div class="action-area">
            <?php if (!$my_id): ?>
                <div class="status-banner status-owner">Login to purchase or message the seller.</div>
                <a href="login.php?redirect=item.php?id=<?php echo $item_id; ?>" class="btn-buy">Login to Buy</a>
            
            <?php elseif ($is_seller): ?>
                <div class="status-banner status-owner">This is your listing.</div>
                <a href="profile.php" class="btn-msg">Manage in Dashboard</a>
            
            <?php elseif (!$is_active): ?>
                <div class="status-banner <?php echo $item['listing_status'] === 'sold' ? 'status-sold' : 'status-escrow'; ?>">
                    This item is <?php echo $item['listing_status'] === 'sold' ? 'already Sold' : 'currently locked in Escrow'; ?>.
                </div>
                <a href="inbox.php?user=<?php echo $item['seller_id']; ?>&listing=<?php echo $item_id; ?>" class="btn-msg">Message Seller Anyway</a>

            <?php else: ?>
                <a href="checkout.php?id=<?php echo $item['listing_id']; ?>" class="btn-buy">Buy Now with Escrow</a>
                
                <a href="inbox.php?user=<?php echo $item['seller_id']; ?>&listing=<?php echo $item_id; ?>" class="btn-msg">💬 Message Seller</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function swapImage(element, newSrc) {
        // Change main image
        const mainImg = document.getElementById('mainImage');
        mainImg.style.opacity = '0.5';
        setTimeout(() => {
            mainImg.src = newSrc;
            mainImg.style.opacity = '1';
        }, 150);

        // Update active state on thumbnails
        document.querySelectorAll('.thumb-img').forEach(img => {
            img.classList.remove('active');
        });
        element.classList.add('active');
    }
</script>

</body>
</html>