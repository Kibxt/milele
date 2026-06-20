<?php
// MILELE - Premium Item Showcase (With Save Feature)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$listing_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$listing_id) {
    header("Location: index.php");
    exit();
}

$current_user = $_SESSION['user_id'];

try {
    // 1. Fetch item and seller details
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name, u.university_name, u.user_id as seller_id 
        FROM listings l 
        JOIN users u ON l.seller_id = u.user_id 
        WHERE l.listing_id = :id AND l.listing_status = 'active'
    ");
    $stmt->execute([':id' => $listing_id]);
    $item = $stmt->fetch();

    if (!$item) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>Item not found or no longer available.</div>");
    }

    // 2. Check if the current user has saved this item
    $check_save = $pdo->prepare("SELECT id FROM saved_items WHERE user_id = :uid AND listing_id = :lid");
    $check_save->execute([':uid' => $current_user, ':lid' => $listing_id]);
    $is_saved = $check_save->fetch() ? true : false;

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System error loading item.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['title']); ?> | MILELE</title>
    <style>
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; background-image: radial-gradient(circle at 50% 0%, rgba(45, 212, 191, 0.05), transparent 50%); }
        .container { max-width: 900px; margin: 0 auto; }
        
        .nav-top { margin-bottom: 30px; }
        .btn-back { display: inline-block; padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); transition: 0.2s; font-size: 0.9rem; }
        .btn-back:hover { background: rgba(255,255,255,0.1); }

        .showcase-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; background: rgba(255,255,255,0.02); backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.05); padding: 40px; border-radius: 32px; box-shadow: 0 24px 48px rgba(0,0,0,0.5); }
        
        .image-container { width: 100%; aspect-ratio: 1/1; background: rgba(0,0,0,0.5); border-radius: 24px; overflow: hidden; display: flex; justify-content: center; align-items: center; border: 1px solid rgba(255,255,255,0.05); }
        .image-container img { width: 100%; height: 100%; object-fit: cover; }
        .no-image { color: #444; font-size: 3rem; }

        .details { display: flex; flex-direction: column; justify-content: center; }
        .badge { display: inline-block; padding: 6px 12px; background: rgba(45, 212, 191, 0.1); color: #2DD4BF; border-radius: 8px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; margin-bottom: 15px; width: fit-content; }
        
        h1 { margin: 0 0 10px 0; font-size: 2.5rem; line-height: 1.2; }
        .price { font-size: 2rem; color: #2DD4BF; font-weight: bold; margin-bottom: 20px; }
        
        .seller-info { display: flex; align-items: center; gap: 15px; padding: 20px 0; border-top: 1px solid rgba(255,255,255,0.1); border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .seller-avatar { width: 45px; height: 45px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; color: #2DD4BF; }
        .seller-details h3 { margin: 0 0 5px 0; font-size: 1rem; }
        .seller-details p { margin: 0; color: #888; font-size: 0.85rem; }

        .description { color: #ccc; line-height: 1.6; margin-bottom: 30px; font-size: 1rem; }

        .action-buttons { display: flex; gap: 15px; flex-wrap: wrap; }
        .btn { flex: 1; padding: 16px; text-align: center; border-radius: 16px; font-weight: bold; font-size: 1.05rem; cursor: pointer; transition: 0.2s; text-decoration: none; border: none; min-width: 140px; }
        
        .btn-buy { background: #2DD4BF; color: #000; }
        .btn-buy:hover { background: #fff; transform: translateY(-2px); }
        
        .btn-msg { background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1); }
        .btn-msg:hover { background: rgba(255,255,255,0.1); }

        /* The Save Heart Button */
        .btn-save { display: flex; align-items: center; justify-content: center; gap: 8px; background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #fff; }
        .btn-save:hover { background: rgba(255,255,255,0.05); }
        .btn-save.saved { background: rgba(248, 113, 113, 0.1); border-color: #F87171; color: #F87171; }
        .btn-save.saved:hover { background: rgba(248, 113, 113, 0.2); }

        @media (max-width: 768px) {
            .showcase-grid { grid-template-columns: 1fr; padding: 20px; }
            h1 { font-size: 2rem; }
            .action-buttons { flex-direction: column; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="nav-top">
        <a href="index.php" class="btn-back">← Back to Feed</a>
    </div>

    <div class="showcase-grid">
        <div class="image-container">
            <?php if (!empty($item['file_path']) && $item['item_type'] === 'physical'): ?>
                <img src="<?php echo htmlspecialchars($item['file_path']); ?>" alt="Item Image">
            <?php elseif ($item['item_type'] === 'digital'): ?>
                <div class="no-image" style="color: #2DD4BF;">📄 Digital File</div>
            <?php else: ?>
                <div class="no-image">📷 No Image</div>
            <?php endif; ?>
        </div>

        <div class="details">
            <div class="badge"><?php echo htmlspecialchars($item['category']); ?></div>
            <h1><?php echo htmlspecialchars($item['title']); ?></h1>
            <div class="price">KES <?php echo number_format($item['price'], 2); ?></div>

            <div class="seller-info">
                <div class="seller-avatar">
                    <?php echo strtoupper(substr($item['full_name'], 0, 1)); ?>
                </div>
                <div class="seller-details">
                    <h3><?php echo htmlspecialchars($item['full_name']); ?></h3>
                    <p>🎓 <?php echo htmlspecialchars($item['university_name']); ?></p>
                </div>
            </div>

            <div class="description">
                <?php echo nl2br(htmlspecialchars($item['description'])); ?>
            </div>

            <div class="action-buttons">
                <?php if ($current_user != $item['seller_id']): ?>
                    <a href="checkout.php?id=<?php echo $item['listing_id']; ?>" class="btn btn-buy">Purchase Now</a>
                    <a href="chat.php?seller=<?php echo $item['seller_id']; ?>&item=<?php echo $item['listing_id']; ?>" class="btn btn-msg">Message</a>
                    
                    <a href="toggle_save.php?id=<?php echo $item['listing_id']; ?>" class="btn btn-save <?php echo $is_saved ? 'saved' : ''; ?>">
                        <?php echo $is_saved ? '♥ Saved' : '♡ Save'; ?>
                    </a>
                <?php else: ?>
                    <button class="btn btn-msg" disabled style="opacity: 0.5; cursor: not-allowed;">This is your listing</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>