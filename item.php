<?php
// MILELE - Detailed Item Page (Interactive Slider & Uncropped Images)

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

    $images = [];
    $decoded_images = json_decode($item['image_path'], true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_images) && count($decoded_images) > 0) {
        $images = $decoded_images;
    } elseif (!empty($item['image_path'])) {
        $images[] = $item['image_path'];
    } else {
        $images[] = 'https://via.placeholder.com/800x800/111111/333333?text=MILELE';
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

        /* Upgraded Interactive Slider Styling */
        .gallery-container { display: flex; flex-direction: column; gap: 15px; }
        .main-image-wrapper { width: 100%; aspect-ratio: 1/1; border-radius: 24px; overflow: hidden; background: #0a0a0a; border: 1px solid rgba(255,255,255,0.1); position: relative; display: flex; align-items: center; justify-content: center;}
        
        /* FIXED: object-fit changed to contain to prevent cropping */
        .product-image { width: 100%; height: 100%; object-fit: contain; transition: opacity 0.3s; }
        
        /* The Next/Prev Floating Arrows */
        .slider-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); color: #fff; border: 1px solid rgba(255,255,255,0.2); width: 45px; height: 45px; border-radius: 50%; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; z-index: 10; padding: 0;}
        .slider-btn:hover { background: #2DD4BF; color: #000; border-color: #2DD4BF;}
        .btn-prev { left: 15px; }
        .btn-next { right: 15px; }

        .thumbnail-row { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; scroll-behavior: smooth;}
        .thumbnail-row::-webkit-scrollbar { height: 6px; }
        .thumbnail-row::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        
        .thumb-img { width: 80px; height: 80px; object-fit: cover; border-radius: 12px; cursor: pointer; border: 2px solid transparent; opacity: 0.4; transition: 0.2s; background: #111; flex-shrink: 0;}
        .thumb-img:hover { opacity: 0.8; }
        .thumb-img.active { border-color: #2DD4BF; opacity: 1; }

        .product-info { display: flex; flex-direction: column; }
        .meta-badges { display: flex; gap: 10px; margin-bottom: 10px; }
        .badge { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; padding: 4px 10px; border-radius: 8px; }
        .cat-badge { color: #2DD4BF; background: rgba(45,212,191,0.1); border: 1px solid rgba(45,212,191,0.2); }
        .format-badge { color: #ccc; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.1); }

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
        
        <div class="gallery-container">
            <div class="main-image-wrapper">
                <?php if (count($images) > 1): ?>
                    <button class="slider-btn btn-prev" onclick="changeImage(-1)">&#10094;</button>
                    <button class="slider-btn btn-next" onclick="changeImage(1)">&#10095;</button>
                <?php endif; ?>
                
                <img src="<?php echo htmlspecialchars($images[0]); ?>" id="mainDisplayImage" class="product-image" alt="Item Image">
            </div>
            
            <?php if (count($images) > 1): ?>
                <div class="thumbnail-row" id="thumbnailRow">
                    <?php foreach ($images as $index => $img): ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" 
                             class="thumb-img <?php echo $index === 0 ? 'active' : ''; ?>" 
                             data-index="<?php echo $index; ?>"
                             onclick="jumpToImage(<?php echo $index; ?>)">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="product-info">
            <div class="meta-badges">
                <div class="badge cat-badge"><?php echo htmlspecialchars($item['category'] ?? 'Campus Goods'); ?></div>
                <?php if (isset($item['item_type'])): ?>
                    <div class="badge format-badge"><?php echo $item['item_type'] == 'Digital' ? '📄 Digital File' : '📦 Physical Handover'; ?></div>
                <?php endif; ?>
            </div>

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

<script>
    // Inject the PHP array securely into the JavaScript engine
    const galleryImages = <?php echo json_encode($images); ?>;
    let currentIndex = 0;

    function changeImage(direction) {
        currentIndex += direction;
        
        // Loop back to the end/beginning seamlessly
        if (currentIndex < 0) {
            currentIndex = galleryImages.length - 1;
        } else if (currentIndex >= galleryImages.length) {
            currentIndex = 0;
        }
        updateGalleryUI();
    }

    function jumpToImage(index) {
        currentIndex = index;
        updateGalleryUI();
    }

    function updateGalleryUI() {
        // Change the main image
        document.getElementById('mainDisplayImage').src = galleryImages[currentIndex];
        
        // Remove active state from all thumbnails
        let thumbs = document.getElementsByClassName('thumb-img');
        for (let i = 0; i < thumbs.length; i++) {
            thumbs[i].classList.remove('active');
        }
        
        // Highlight the active thumbnail and automatically scroll it into view
        if(thumbs[currentIndex]) {
            thumbs[currentIndex].classList.add('active');
            thumbs[currentIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }
</script>

</body>
</html>