<?php
// MILELE - Secure User Dashboard

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

// 1. BOUNCER: Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// 2. ACTION LISTENER: Handle Listing Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $listing_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
    
    if ($listing_id) {
        try {
            // Secure Delete: Ensure the listing actually belongs to the logged-in user
            $stmt = $pdo->prepare("DELETE FROM listings WHERE listing_id = ? AND seller_id = ?");
            $stmt->execute([$listing_id, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = "Listing permanently deleted.";
            } else {
                $error = "Could not delete listing. It may have already been removed.";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// 3. DATA FETCH: Get User Info
try {
    $stmt = $pdo->prepare("SELECT full_name, email, university_name, is_verified FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    // 4. DATA FETCH: Get User's Active Listings
    $stmt_listings = $pdo->prepare("SELECT * FROM listings WHERE seller_id = ? ORDER BY created_at DESC");
    $stmt_listings->execute([$user_id]);
    $my_listings = $stmt_listings->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database Connection Error.");
}

// Helper function for avatar initials
function get_initials($name) {
    $words = explode(' ', $name);
    if (count($words) >= 2) return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    return strtoupper(substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | MILELE</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --indigo: #1A1040;
            --amber: #F5A623;
            --coral: #FF6B6B;
            --mint: #00D4AA;
            --chalk: #F7F5FF;
            --slate: #8B7FA8;
            --white: #ffffff;
            --card-border: rgba(26,16,64,0.10);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0;}
        body { background: var(--chalk); color: var(--indigo); font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column;}
        
        /* Navigation */
        nav { background: var(--white); border-bottom: 1px solid var(--card-border); padding: 0 5%; display: flex; align-items: center; justify-content: space-between; height: 70px; position: sticky; top: 0; z-index: 100;}
        .nav-logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 22px; color: var(--indigo); text-decoration: none; }
        .nav-logo span { color: var(--amber); }
        .nav-links { display: flex; gap: 20px; align-items: center;}
        .btn-ghost { text-decoration: none; font-size: 14px; font-weight: 600; color: var(--slate); transition: 0.2s;}
        .btn-ghost:hover { color: var(--indigo); }
        .btn-danger { text-decoration: none; color: var(--coral); font-weight: 600; font-size: 14px; border: 1px solid var(--coral); padding: 8px 16px; border-radius: 50px; transition: 0.2s;}
        .btn-danger:hover { background: var(--coral); color: var(--white); }
        
        /* Main Container */
        .container { max-width: 1200px; margin: 0 auto; padding: 40px 5%; flex: 1; width: 100%;}
        
        /* Alerts */
        .alert-success { background: rgba(0,212,170,0.1); color: #059669; border: 1px solid rgba(0,212,170,0.2); padding: 14px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 600; }
        .alert-error { background: rgba(255,107,107,0.1); color: var(--coral); border: 1px solid rgba(255,107,107,0.2); padding: 14px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 600; }

        /* Profile Header */
        .profile-header { background: var(--white); border: 1px solid var(--card-border); border-radius: 24px; padding: 40px; display: flex; align-items: center; gap: 30px; box-shadow: 0 10px 30px rgba(26,16,64,0.03); margin-bottom: 40px; flex-wrap: wrap;}
        .profile-avatar { width: 90px; height: 90px; background: var(--indigo); color: var(--white); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; flex-shrink: 0;}
        .profile-info h1 { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; margin-bottom: 5px; color: var(--indigo); display: flex; align-items: center; gap: 10px;}
        .badge-verified { background: var(--mint); color: var(--indigo); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 50px; letter-spacing: 0.05em; text-transform: uppercase;}
        .profile-details { color: var(--slate); font-size: 15px; display: flex; gap: 15px; flex-wrap: wrap;}
        .profile-details span { display: flex; align-items: center; gap: 5px;}

        /* Section Title & Action */
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;}
        .section-header h2 { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800;}
        .btn-primary { background: var(--amber); color: var(--indigo); text-decoration: none; padding: 10px 24px; border-radius: 50px; font-weight: 700; font-size: 14px; transition: 0.2s; box-shadow: 0 4px 15px rgba(245,166,35,0.3);}
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(245,166,35,0.4);}

        /* Listings Grid */
        .listings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; }
        .listing-card { background: var(--white); border: 1px solid var(--card-border); border-radius: 18px; overflow: hidden; transition: 0.3s; position: relative; display: flex; flex-direction: column;}
        .listing-card:hover { border-color: var(--amber); transform: translateY(-4px); box-shadow: 0 12px 30px rgba(26,16,64,0.08); }
        .listing-img { width: 100%; height: 200px; object-fit: cover; display: block; border-bottom: 1px solid var(--card-border);}
        .listing-badge { position: absolute; top: 12px; left: 12px; font-size: 11px; font-weight: 700; background: var(--white); color: var(--indigo); padding: 4px 12px; border-radius: 50px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);}
        
        .listing-body { padding: 20px; flex: 1; display: flex; flex-direction: column;}
        .listing-title { font-size: 16px; font-weight: 700; color: var(--indigo); line-height: 1.3; margin-bottom: 8px;}
        .listing-price { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; color: var(--indigo); margin-bottom: 20px;}
        .listing-price span { font-size: 14px; font-weight: 600; color: var(--slate);}
        
        /* Delete Button Form */
        .listing-actions { margin-top: auto; display: flex; gap: 10px;}
        .form-delete { flex: 1; }
        .btn-delete { width: 100%; background: var(--chalk); color: var(--coral); border: 1px solid rgba(255,107,107,0.3); padding: 10px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; transition: 0.2s; font-family: 'Inter', sans-serif;}
        .btn-delete:hover { background: var(--coral); color: var(--white); border-color: var(--coral);}

        /* Empty State */
        .empty-state { text-align: center; padding: 60px 20px; background: var(--white); border-radius: 20px; border: 2px dashed var(--card-border); grid-column: 1 / -1;}
        .empty-state-icon { font-size: 48px; margin-bottom: 15px;}
        .empty-state h3 { font-family: 'Syne', sans-serif; font-size: 20px; color: var(--indigo); margin-bottom: 10px;}
        .empty-state p { color: var(--slate); font-size: 15px; margin-bottom: 20px;}
    </style>
</head>
<body>

<nav>
    <a href="index.php" class="nav-logo">MILELE<span>.</span></a>
    <div class="nav-links">
        <a href="index.php" class="btn-ghost">Browse Feed</a>
        <a href="logout.php" class="btn-danger">Log Out</a>
    </div>
</nav>

<div class="container">
    <?php if($message) echo "<div class='alert-success'>$message</div>"; ?>
    <?php if($error) echo "<div class='alert-error'>$error</div>"; ?>

    <div class="profile-header">
        <div class="profile-avatar">
            <?php echo get_initials($user['full_name']); ?>
        </div>
        <div class="profile-info">
            <h1>
                <?php echo htmlspecialchars($user['full_name']); ?>
                <?php if($user['is_verified']): ?>
                    <span class="badge-verified">✓ Verified</span>
                <?php endif; ?>
            </h1>
            <div class="profile-details">
                <span>📧 <?php echo htmlspecialchars($user['email']); ?></span>
                <span>🎓 <?php echo htmlspecialchars($user['university_name']); ?></span>
            </div>
        </div>
    </div>

    <div class="section-header">
        <h2>My Active Listings</h2>
        <a href="create_listing.php" class="btn-primary">+ Post New Item</a>
    </div>

    <div class="listings-grid">
        <?php if (empty($my_listings)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📦</div>
                <h3>Your shop is empty</h3>
                <p>You haven't posted any items for sale yet. Turn your unused campus items into cash today.</p>
                <a href="create_listing.php" class="btn-primary" style="display:inline-block;">Post Your First Item</a>
            </div>
        <?php else: ?>
            <?php foreach ($my_listings as $item): ?>
                <div class="listing-card">
                    <img class="listing-img" src="<?php echo htmlspecialchars($item['image_path'] ?? 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500&q=80'); ?>" alt="Listing Image">
                    <div class="listing-badge"><?php echo htmlspecialchars($item['category']); ?></div>
                    
                    <div class="listing-body">
                        <div class="listing-title"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div class="listing-price"><span>KES </span><?php echo number_format($item['price']); ?></div>
                        
                        <div class="listing-actions">
                            <form method="POST" class="form-delete" onsubmit="return confirm('Are you sure you want to permanently delete this item?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="listing_id" value="<?php echo $item['listing_id']; ?>">
                                <button type="submit" class="btn-delete">Delete Item</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>