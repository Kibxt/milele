<?php
// MILELE - Premium Global Feed (With Profile Pictures, Galleries & Auto-Patch)

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
$is_logged_in = isset($_SESSION['user_id']);

if ($is_logged_in) {
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
    die("<div style='background:#1A1040; color:#FF6B6B; padding:50px; text-align:center; font-family: sans-serif;'><strong>System Error Loading Feed:</strong> " . htmlspecialchars($e->getMessage()) . "</div>");
}

// Helper function to generate avatar initials
function get_initials($name) {
    $words = explode(' ', $name);
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MILELE — Buy, Sell & Trade on Campus</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --indigo: #1A1040;
    --indigo-mid: #2D1B69;
    --amber: #F5A623;
    --coral: #FF6B6B;
    --mint: #00D4AA;
    --chalk: #F7F5FF;
    --slate: #8B7FA8;
    --white: #ffffff;
    --card-border: rgba(26,16,64,0.10);
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body { font-family: 'Inter', sans-serif; background: var(--chalk); color: var(--indigo); overflow-x: hidden; }

  /* Live Ticker */
  .ticker-bar { background: var(--indigo); color: var(--amber); font-size: 12px; font-weight: 600; letter-spacing: 0.05em; padding: 8px 0; overflow: hidden; white-space: nowrap; }
  .ticker-inner { display: inline-block; animation: ticker 35s linear infinite; }
  .ticker-inner span { margin: 0 40px; }
  .ticker-inner span::before { content: '●'; margin-right: 10px; color: var(--mint); }
  @keyframes ticker { from { transform: translateX(0); } to { transform: translateX(-50%); } }

  /* Navigation & Blur */
  nav { background: rgba(247,245,255,0.94); backdrop-filter: blur(14px); border-bottom: 1px solid var(--card-border); position: sticky; top: 0; z-index: 100; padding: 0 5%; display: flex; align-items: center; justify-content: space-between; height: 70px; }
  .nav-logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 24px; color: var(--indigo); text-decoration: none; display: flex; align-items: center; gap: 8px; }
  .logo-dot { width: 10px; height: 10px; background: var(--amber); border-radius: 50%; display: inline-block; animation: pulse 2s ease-in-out infinite; }
  @keyframes pulse { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.4); opacity: 0.7; } }
  
  .nav-links { display: flex; gap: 28px; list-style: none; }
  .nav-links a { font-size: 14px; font-weight: 500; color: var(--slate); text-decoration: none; transition: color 0.2s; }
  .nav-links a:hover { color: var(--indigo); }
  
  .nav-right { display: flex; align-items: center; gap: 12px; }
  .btn-ghost { background: none; border: 1.5px solid var(--indigo); color: var(--indigo); padding: 9px 20px; border-radius: 50px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; text-decoration: none; position: relative;}
  .btn-ghost:hover { background: var(--indigo); color: var(--white); }
  .btn-primary { background: var(--amber); border: none; color: var(--indigo); padding: 10px 24px; border-radius: 50px; font-size: 13px; font-weight: 800; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; box-shadow: 0 4px 15px rgba(245,166,35,0.3); text-decoration: none;}
  .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(245,166,35,0.45); }
  .btn-danger { background: none; border: 1.5px solid var(--coral); color: var(--coral); padding: 9px 20px; border-radius: 50px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; text-decoration: none;}
  .btn-danger:hover { background: var(--coral); color: var(--white); }

  /* Premium Unread Badge */
  .notif-badge { position: absolute; top: -6px; right: -6px; background: var(--coral); color: var(--white); font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 10px; border: 2px solid var(--chalk); animation: pulse-coral 2s infinite; pointer-events: none;}
  @keyframes pulse-coral { 0% { box-shadow: 0 0 0 0 rgba(255, 107, 107, 0.7); } 70% { box-shadow: 0 0 0 6px rgba(255, 107, 107, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 107, 107, 0); } }

  /* Premium Toast Alert */
  .toast-alert { position: fixed; bottom: -100px; right: 20px; background: var(--white); border: 1px solid var(--card-border); padding: 16px 24px; border-radius: 16px; display: flex; align-items: center; gap: 16px; box-shadow: 0 12px 40px rgba(26,16,64,0.12); transition: bottom 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 1000; min-width: 280px;}
  .toast-alert.show { bottom: 24px; }
  .toast-icon { font-size: 24px; }
  .toast-content { flex-grow: 1; }
  .toast-title { font-weight: 800; color: var(--indigo); font-size: 15px; font-family: 'Syne', sans-serif; margin-bottom: 2px;}
  .toast-desc { color: var(--slate); font-size: 13px; }
  .toast-link { color: var(--amber); font-weight: 800; text-decoration: none; font-size: 13px; letter-spacing: 0.05em; text-transform: uppercase;}

  /* Hero Section */
  .hero { background: var(--indigo); color: var(--white); padding: 90px 5% 0; position: relative; overflow: hidden; min-height: 580px; display: flex; align-items: center; }
  .hero-bg-circle { position: absolute; border-radius: 50%; background: var(--indigo-mid); }
  .hero-bg-circle.c1 { width: 400px; height: 400px; top: -100px; right: -50px; opacity: 0.5; animation: float 8s ease-in-out infinite; }
  .hero-bg-circle.c2 { width: 250px; height: 250px; bottom: 40px; right: 200px; opacity: 0.3; animation: float 8s ease-in-out infinite; animation-delay: -3s; }
  .hero-content { position: relative; z-index: 2; max-width: 600px; }
  .hero-eyebrow { display: inline-flex; align-items: center; gap: 8px; background: rgba(245,166,35,0.15); border: 1px solid rgba(245,166,35,0.3); color: var(--amber); font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 6px 14px; border-radius: 50px; margin-bottom: 24px; }
  .eyebrow-dot { width: 6px; height: 6px; background: var(--amber); border-radius: 50%; animation: pulse 1.5s ease-in-out infinite; }
  .hero h1 { font-family: 'Syne', sans-serif; font-size: clamp(36px, 5vw, 58px); font-weight: 800; line-height: 1.05; margin-bottom: 20px; }
  .hero h1 .accent { color: var(--amber); }
  .hero-sub { font-size: 16px; line-height: 1.7; color: rgba(255,255,255,0.65); margin-bottom: 36px; max-width: 480px; }
  .hero-ctas { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 50px; }
  .btn-hero { background: var(--amber); border: none; color: var(--indigo); padding: 14px 30px; border-radius: 50px; font-size: 15px; font-weight: 800; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; box-shadow: 0 4px 20px rgba(245,166,35,0.4); text-decoration: none;}
  .btn-hero:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(245,166,35,0.55); }
  .btn-hero-ghost { background: rgba(255,255,255,0.08); border: 1.5px solid rgba(255,255,255,0.25); color: var(--white); padding: 14px 30px; border-radius: 50px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; text-decoration: none;}
  .btn-hero-ghost:hover { background: rgba(255,255,255,0.15); }
  
  .hero-right { position: absolute; right: 5%; bottom: 0; width: 420px; z-index: 2; }
  .hero-card-stack { position: relative; height: 420px; }
  .hero-card { position: absolute; background: var(--white); border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.35); }
  .hero-card.main { width: 260px; bottom: 0; left: 50%; transform: translateX(-50%); z-index: 3; }
  .hero-card.left { width: 200px; bottom: 30px; left: 0; z-index: 2; transform: rotate(-5deg); opacity: 0.9; }
  .hero-card.right { width: 200px; bottom: 30px; right: 0; z-index: 2; transform: rotate(5deg); opacity: 0.9; }
  .hero-card img { width: 100%; height: 160px; object-fit: cover; display: block; }
  .hero-card-body { padding: 12px 14px; }
  .hero-card-cat { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--slate); margin-bottom: 4px; }
  .hero-card-title { font-size: 14px; font-weight: 600; color: var(--indigo); line-height: 1.3; }
  .hero-card-price { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 800; color: var(--coral); margin-top: 6px; }
  .hero-card-badge { position: absolute; top: 10px; left: 10px; background: var(--mint); color: var(--indigo); font-size: 10px; font-weight: 800; letter-spacing: 0.05em; padding: 3px 8px; border-radius: 50px; }

  /* Trust Bar */
  .trust-bar { background: var(--indigo); padding: 24px 5%; display: flex; justify-content: center; gap: 50px; flex-wrap: wrap; border-top: 1px solid rgba(255,255,255,0.05);}
  .trust-item { display: flex; align-items: center; gap: 10px; color: rgba(255,255,255,0.6); font-size: 13px; font-weight: 500; }
  .trust-icon { font-size: 20px; }

  /* Search & Filters */
  .search-section { background: var(--white); padding: 40px 5%; border-bottom: 1px solid var(--card-border); }
  .search-wrap { max-width: 700px; margin: 0 auto; position: relative; display: flex; gap: 10px;}
  .search-input { flex-grow: 1; height: 56px; border: 2px solid var(--card-border); border-radius: 50px; padding: 0 24px; font-size: 15px; font-family: 'Inter', sans-serif; color: var(--indigo); background: var(--chalk); outline: none; transition: all 0.2s; }
  .search-input:focus { border-color: var(--amber); box-shadow: 0 0 0 4px rgba(245,166,35,0.12); background: var(--white);}
  .search-input::placeholder { color: var(--slate); }
  .search-btn { height: 56px; background: var(--amber); border: none; color: var(--indigo); font-size: 14px; font-weight: 800; padding: 0 32px; border-radius: 50px; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; }
  .search-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(245,166,35,0.3); }
  
  .filter-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 24px; justify-content: center; }
  .pill { background: var(--chalk); border: 1.5px solid var(--card-border); color: var(--indigo); font-size: 13px; font-weight: 600; padding: 8px 20px; border-radius: 50px; cursor: pointer; transition: all 0.2s; white-space: nowrap; text-decoration: none;}
  .pill:hover, .pill.active { background: var(--indigo); color: var(--white); border-color: var(--indigo); }

  /* Categories Section */
  .categories-section { padding: 60px 5%; background: var(--chalk); }
  .section-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 32px; }
  .section-title { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; color: var(--indigo); }
  .section-link { color: var(--amber); font-size: 13px; font-weight: 600; text-decoration: none; }
  .category-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
  .cat-card { background: var(--white); border: 1.5px solid var(--card-border); border-radius: 16px; padding: 22px 16px; text-align: center; cursor: pointer; transition: all 0.25s; text-decoration: none; color: var(--indigo); display: block; }
  .cat-card:hover { border-color: var(--amber); transform: translateY(-3px); box-shadow: 0 8px 24px rgba(245,166,35,0.15); }
  .cat-icon { font-size: 32px; margin-bottom: 10px; display: block; }
  .cat-name { font-size: 13px; font-weight: 600; }
  
  /* Listings Grid */
  .listings-section { padding: 60px 5%; background: var(--chalk); flex-grow: 1;}
  .listings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px; }
  .listing-card { background: var(--white); border: 1px solid var(--card-border); border-radius: 20px; overflow: hidden; transition: all 0.3s; cursor: pointer; position: relative; display: flex; flex-direction: column; text-decoration: none;}
  .listing-card:hover { border-color: var(--amber); transform: translateY(-4px); box-shadow: 0 16px 40px rgba(26,16,64,0.08); }
  
  /* Interactive Swipeable Gallery inside Card */
  .gallery-container { position: relative; width: 100%; height: 220px; }
  .gallery-track { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; -ms-overflow-style: none; width: 100%; height: 100%; background: var(--chalk); border-bottom: 1px solid var(--card-border);}
  .gallery-track::-webkit-scrollbar { display: none; } 
  .gallery-img { flex: 0 0 100%; width: 100%; height: 100%; object-fit: cover; scroll-snap-align: center; }
  .swipe-hint { position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%); background: rgba(26,16,64,0.7); backdrop-filter: blur(4px); padding: 4px 12px; border-radius: 50px; font-size: 10px; font-weight: 700; color: var(--white); opacity: 0; transition: 0.3s; pointer-events: none; letter-spacing: 0.05em; text-transform: uppercase;}
  .listing-card:hover .swipe-hint { opacity: 1; }
  
  .floating-badges { position: absolute; top: 12px; left: 12px; right: 12px; display: flex; justify-content: space-between; pointer-events: none; z-index: 10;}
  .glass-badge { background: var(--white); color: var(--indigo); font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; padding: 4px 10px; border-radius: 50px; box-shadow: 0 4px 12px rgba(26,16,64,0.1);}
  .badge-cat { background: var(--mint); }

  .listing-body { padding: 20px; flex: 1; display: flex; flex-direction: column;}
  .listing-title { font-size: 16px; font-weight: 700; color: var(--indigo); line-height: 1.4; margin-bottom: 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;}
  .listing-price { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; color: var(--indigo); margin-bottom: 20px;}
  .listing-price .kes { font-size: 14px; font-weight: 600; color: var(--slate); }
  
  .listing-footer { margin-top: auto; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--card-border); padding-top: 16px; }
  .listing-seller { display: flex; align-items: center; gap: 10px; }
  .seller-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; background: var(--indigo); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; color: var(--white); flex-shrink: 0;}
  .seller-details { display: flex; flex-direction: column; }
  .seller-name { font-size: 13px; font-weight: 700; color: var(--indigo); }
  .seller-verified { color: var(--mint); font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;}
  .btn-view { background: var(--chalk); color: var(--indigo); border: 1px solid var(--card-border); padding: 8px 16px; border-radius: 50px; font-size: 12px; font-weight: 700; transition: all 0.2s; }
  .listing-card:hover .btn-view { background: var(--amber); border-color: var(--amber); }

  .empty-state { text-align: center; padding: 80px 20px; background: var(--white); border-radius: 24px; border: 2px dashed var(--card-border); grid-column: 1 / -1; }
  .empty-state h3 { color: var(--indigo); margin-bottom: 10px; font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800;}
  .empty-state p { color: var(--slate); font-size: 15px; margin-bottom: 24px;}

  /* Semester Sale Banner */
  .semester-wrap { padding: 60px 5% 0; background: var(--white); }
  .semester-banner { background: var(--indigo); border-radius: 24px; padding: 50px 6%; display: flex; align-items: center; justify-content: space-between; gap: 30px; overflow: hidden; position: relative; flex-wrap: wrap; }
  .semester-banner::before { content: ''; position: absolute; width: 300px; height: 300px; border-radius: 50%; background: rgba(245,166,35,0.1); top: -100px; right: 100px; pointer-events: none; }
  .banner-tag { display: inline-block; background: rgba(245,166,35,0.2); color: var(--amber); font-size: 11px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; padding: 6px 14px; border-radius: 50px; margin-bottom: 16px; }
  .banner-title { font-family: 'Syne', sans-serif; font-size: clamp(24px, 3vw, 38px); font-weight: 800; color: var(--white); line-height: 1.15; margin-bottom: 12px; }
  .banner-title .hi { color: var(--amber); }
  .banner-sub { font-size: 15px; color: rgba(255,255,255,0.6); max-width: 420px; line-height: 1.6; margin-bottom: 28px; }
  .btn-amber-big { background: var(--amber); border: none; color: var(--indigo); padding: 14px 32px; border-radius: 50px; font-size: 15px; font-weight: 800; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; display: inline-block; text-decoration: none;}
  .btn-amber-big:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(245,166,35,0.45); }
  
  .banner-countdown { display: flex; gap: 16px; position: relative; z-index: 1; flex-wrap: wrap; }
  .countdown-block { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12); border-radius: 12px; width: 70px; text-align: center; padding: 12px 8px; }
  .countdown-num { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; color: var(--white); }
  .countdown-label { font-size: 10px; color: rgba(255,255,255,0.45); text-transform: uppercase; letter-spacing: 0.07em; }

  /* How it Works */
  .how-section { padding: 80px 5%; background: var(--white); }
  .steps-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-top: 40px; }
  .step-card { background: var(--chalk); border: 1.5px solid var(--card-border); border-radius: 20px; padding: 28px 24px; transition: all 0.3s; }
  .step-card:hover { border-color: var(--amber); transform: translateY(-4px); }
  .step-num { font-family: 'Syne', sans-serif; font-size: 40px; font-weight: 800; color: var(--amber); margin-bottom: 16px; opacity: 0.7; }
  .step-icon { font-size: 28px; margin-bottom: 12px; }
  .step-title { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; color: var(--indigo); margin-bottom: 10px; }
  .step-desc { font-size: 14px; color: var(--slate); line-height: 1.6; }

  /* Testimonials */
  .testimonials-section { padding: 20px 5% 80px; background: var(--white); }
  .testimonials-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 40px; }
  .testimonial-card { background: var(--chalk); border: 1.5px solid var(--card-border); border-radius: 18px; padding: 24px; transition: all 0.3s; }
  .testimonial-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(26,16,64,0.08); }
  .testimonial-stars { color: var(--amber); font-size: 14px; margin-bottom: 12px; }
  .testimonial-text { font-size: 14px; line-height: 1.65; color: var(--indigo); margin-bottom: 20px; font-style: italic; }
  .testimonial-author { display: flex; align-items: center; gap: 10px; }
  .author-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: var(--white); flex-shrink: 0; }
  .author-name { font-size: 14px; font-weight: 600; color: var(--indigo); }
  .author-course { font-size: 12px; color: var(--slate); }

  /* Sell CTA */
  .sell-cta { padding: 0 5% 80px; background: var(--white); }
  .sell-cta-inner { background: var(--amber); border-radius: 24px; padding: 50px; display: flex; justify-content: space-between; align-items: center; gap: 30px; flex-wrap: wrap; }
  .sell-label { font-size: 12px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(26,16,64,0.55); margin-bottom: 10px; }
  .sell-cta-inner h2 { font-family: 'Syne', sans-serif; font-size: clamp(24px, 3vw, 36px); font-weight: 800; color: var(--indigo); line-height: 1.1; margin-bottom: 12px; }
  .sell-cta-inner p { font-size: 15px; color: rgba(26,16,64,0.65); line-height: 1.6; }
  .sell-cta-actions { display: flex; gap: 12px; flex-wrap: wrap; }
  .btn-dark { background: var(--indigo); color: var(--white); padding: 14px 32px; border-radius: 50px; font-size: 15px; font-weight: 800; text-decoration: none; transition: all 0.2s;}
  .btn-dark:hover { background: var(--indigo-mid); transform: translateY(-1px); }
  .btn-outline-dark { background: transparent; color: var(--indigo); border: 2px solid var(--indigo); padding: 14px 32px; border-radius: 50px; font-size: 15px; font-weight: 700; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; text-decoration: none;}
  .btn-outline-dark:hover { background: var(--indigo); color: var(--white); }

  /* Footer */
  footer { background: var(--indigo); color: rgba(255,255,255,0.5); padding: 60px 5% 30px; }
  .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 40px; margin-bottom: 50px; }
  .footer-brand-logo { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; color: var(--white); margin-bottom: 14px; }
  .footer-brand p { font-size: 14px; line-height: 1.65; max-width: 240px; }
  .footer-social { display: flex; gap: 10px; margin-top: 20px; }
  .social-btn { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); display: flex; align-items: center; justify-content: center; font-size: 13px; cursor: pointer; transition: all 0.2s; color: rgba(255,255,255,0.6); }
  .social-btn:hover { background: var(--amber); color: var(--indigo); }
  .footer-col h4 { font-size: 13px; font-weight: 700; color: var(--white); letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 18px; }
  .footer-col ul { list-style: none; display: flex; flex-direction: column; gap: 10px; }
  .footer-col ul li a { color: rgba(255,255,255,0.45); font-size: 13px; text-decoration: none; transition: color 0.2s; }
  .footer-col ul li a:hover { color: var(--amber); }
  .footer-bottom { border-top: 1px solid rgba(255,255,255,0.08); padding-top: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 12px; }
  .footer-bottom a { color: var(--amber); text-decoration: none; }
  
  .reveal { opacity: 0; transform: translateY(24px); transition: opacity 0.6s ease, transform 0.6s ease; }
  .reveal.visible { opacity: 1; transform: none; }

  @media (max-width: 900px) { .hero-right { display: none; } .footer-grid { grid-template-columns: 1fr 1fr; } }
  @media (max-width: 768px) {
    .search-wrap { flex-direction: column; }
    .search-btn { width: 100%; }
    .nav-links { display: none; }
    .footer-grid { grid-template-columns: 1fr; } 
    .sell-cta-inner { padding: 32px 24px; } 
    .trust-bar { gap: 20px; }
  }
</style>
</head>
<body>

<!-- TICKER -->
<div class="ticker-bar" aria-hidden="true">
  <div class="ticker-inner">
    <span>MacBook Pro 2022 — KES 85,000</span>
    <span>Physics Textbook Bundle — KES 1,200</span>
    <span>Mini Fridge (barely used) — KES 4,500</span>
    <span>Graphic Calculator TI-84 — KES 3,200</span>
    <span>Dorm Desk Chair — KES 2,800</span>
    <span>iPhone 14 — KES 38,000</span>
    <span>Semester End Sale — All items reduced!</span>
  </div>
</div>

<!-- NAVIGATION -->
<nav>
  <a href="index.php" class="nav-logo"><span class="logo-dot"></span>MILELE</a>
  <ul class="nav-links">
    <li><a href="#browse">Browse Feed</a></li>
    <li><a href="#categories">Categories</a></li>
    <li><a href="#how">How it works</a></li>
    <!-- DIRECT LINK TO SELL -->
    <li><a href="post_item.php">Sell</a></li>
  </ul>
  <div class="nav-right">
    <?php if ($is_logged_in): ?>
        <a href="inbox.php" class="btn-ghost" style="border: none;">
            Inbox
            <?php if($unread_count > 0): ?>
                <span class="notif-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php" class="btn-ghost">Dashboard</a>
        <!-- DIRECT LINK TO SELL -->
        <a href="post_item.php" class="btn-primary">+ Post Listing</a>
        <a href="logout.php" class="btn-danger">Log Out</a>
    <?php else: ?>
        <a href="login.php" class="btn-ghost">Log In</a>
        <a href="register.php" class="btn-primary">Sign Up</a>
    <?php endif; ?>
  </div>
</nav>

<!-- TOAST ALERT FOR NEW MESSAGES -->
<?php if($unread_count > 0): ?>
    <div class="toast-alert" id="msgToast">
        <div class="toast-icon">💬</div>
        <div class="toast-content">
            <div class="toast-title">New Messages</div>
            <div class="toast-desc">You have <?php echo $unread_count; ?> unread message(s).</div>
        </div>
        <a href="inbox.php" class="toast-link">View</a>
    </div>
    <script>
        setTimeout(() => { document.getElementById('msgToast').classList.add('show'); }, 1000);
        setTimeout(() => { document.getElementById('msgToast').classList.remove('show'); }, 6000);
    </script>
<?php endif; ?>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg-circle c1"></div>
  <div class="hero-bg-circle c2"></div>
  <div class="hero-content">
    <div class="hero-eyebrow"><span class="eyebrow-dot"></span>The Campus Market</div>
    <h1>Zero Scams.<br>Bulletproof Escrow.<br><span class="accent">Exclusive to Students.</span></h1>
    <p class="hero-sub">Buy, sell, and trade safely within your university community. No external strangers. Zero platform fees.</p>
    <div class="hero-ctas">
      <a href="#browse" class="btn-hero">Browse Deals →</a>
      <!-- DIRECT LINK TO SELL -->
      <a href="post_item.php" class="btn-hero-ghost">Sell Something</a>
    </div>
  </div>
  <div class="hero-right">
    <div class="hero-card-stack">
      <div class="hero-card left">
        <img src="https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=400&q=80" alt="Textbooks">
        <div class="hero-card-body">
          <div class="hero-card-cat">Textbooks</div>
          <div class="hero-card-title">Calculus 10th Edition</div>
          <div class="hero-card-price">KES 1,200</div>
        </div>
        <div class="hero-card-badge">Good</div>
      </div>
      <div class="hero-card main">
        <img src="https://images.unsplash.com/photo-1517336714731-489689fd1ca4?w=400&q=80" alt="MacBook">
        <div class="hero-card-body">
          <div class="hero-card-cat">Electronics</div>
          <div class="hero-card-title">MacBook Pro M1 — 2021</div>
          <div class="hero-card-price">KES 85,000</div>
        </div>
        <div class="hero-card-badge">Verified ✓</div>
      </div>
      <div class="hero-card right">
        <img src="https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=400&q=80" alt="Sofa">
        <div class="hero-card-body">
          <div class="hero-card-cat">Furniture</div>
          <div class="hero-card-title">Study Desk + Chair</div>
          <div class="hero-card-price">KES 5,500</div>
        </div>
        <div class="hero-card-badge">New</div>
      </div>
    </div>
  </div>
</section>

<!-- TRUST BAR -->
<div class="trust-bar">
  <div class="trust-item"><span class="trust-icon">🎓</span> Student-verified profiles</div>
  <div class="trust-item"><span class="trust-icon">🔒</span> Safe campus meetups</div>
  <div class="trust-item"><span class="trust-icon">💬</span> In-app messaging</div>
  <div class="trust-item"><span class="trust-icon">⭐</span> Seller ratings system</div>
</div>

<!-- SEARCH & FILTERS -->
<section class="search-section" id="browse">
  <form action="index.php" method="GET" class="search-wrap reveal">
    <?php if($category): ?><input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>"><?php endif; ?>
    <input type="text" name="search" class="search-input" placeholder="Search textbooks, laptops, dorm furniture..." value="<?php echo htmlspecialchars($search); ?>">
    <button type="submit" class="search-btn">Search</button>
  </form>

  <div class="filter-pills reveal">
    <a href="index.php<?php echo $search ? '?search='.urlencode($search) : ''; ?>" class="pill <?php echo empty($category) ? 'active' : ''; ?>">✨ All Items</a>
    <?php 
    $cats = ['Textbooks', 'Electronics', 'Furniture', 'Clothes', 'Kitchen', 'Gaming', 'Other'];
    foreach ($cats as $cat): 
        $link = "?category=" . urlencode($cat);
        if ($search) $link .= "&search=" . urlencode($search);
    ?>
        <a href="<?php echo $link; ?>" class="pill <?php echo ($category === $cat) ? 'active' : ''; ?>"><?php echo htmlspecialchars($cat); ?></a>
    <?php endforeach; ?>
  </div>
</section>

<!-- CATEGORIES GRID -->
<section class="categories-section reveal" id="categories">
  <div class="section-header">
    <h2 class="section-title">Browse by Category</h2>
    <a href="index.php" class="section-link">View all →</a>
  </div>
  <div class="category-grid">
    <a href="index.php?category=Textbooks" class="cat-card"><span class="cat-icon">📚</span><div class="cat-name">Textbooks</div></a>
    <a href="index.php?category=Electronics" class="cat-card"><span class="cat-icon">💻</span><div class="cat-name">Electronics</div></a>
    <a href="index.php?category=Furniture" class="cat-card"><span class="cat-icon">🛋️</span><div class="cat-name">Furniture</div></a>
    <a href="index.php?category=Clothes" class="cat-card"><span class="cat-icon">👗</span><div class="cat-name">Clothes</div></a>
    <a href="index.php?category=Kitchen" class="cat-card"><span class="cat-icon">🍳</span><div class="cat-name">Kitchen</div></a>
    <a href="index.php?category=Gaming" class="cat-card"><span class="cat-icon">🎮</span><div class="cat-name">Gaming</div></a>
    <a href="index.php?category=Sports" class="cat-card"><span class="cat-icon">🏋️</span><div class="cat-name">Sports</div></a>
    <a href="index.php?category=Other" class="cat-card"><span class="cat-icon">📦</span><div class="cat-name">Other</div></a>
  </div>
</section>

<!-- LISTINGS GRID (DYNAMIC) -->
<section class="listings-section reveal">
  <div class="section-header">
    <h2 class="section-title">Fresh Listings</h2>
  </div>
  
  <?php if (empty($items)): ?>
      <div class="empty-state">
          <h3>No items found</h3>
          <p>We couldn't find any listings matching your current search or category.</p>
          <?php if(!empty($search) || !empty($category)): ?>
              <a href="index.php" class="btn-ghost" style="border-color: var(--amber); color: var(--amber);">Clear Filters</a>
          <?php else: ?>
              <!-- DIRECT LINK TO SELL -->
              <a href="post_item.php" class="btn-primary" style="display:inline-block; padding: 12px 24px;">Post a Listing</a>
          <?php endif; ?>
      </div>
  <?php else: ?>
      <div class="listings-grid">
          <?php foreach ($items as $item): 
              $images = [];
              $decoded_images = json_decode($item['image_path'], true);
              if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_images) && count($decoded_images) > 0) {
                  $images = $decoded_images;
              } else {
                  $images[] = !empty($item['image_path']) ? $item['image_path'] : 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500&q=80';
              }
          ?>
              <!-- REDIRECTING TO CHECKOUT/DETAILS -->
              <a href="checkout.php?id=<?php echo $item['listing_id']; ?>" class="listing-card">
                  <div class="gallery-container">
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
                  </div>
                  
                  <div class="listing-body">
                      <div class="listing-title"><?php echo htmlspecialchars($item['title']); ?></div>
                      <div class="listing-price"><span class="kes">KES </span><?php echo number_format($item['price']); ?></div>
                      
                      <div class="listing-footer">
                          <div class="listing-seller">
                              <?php if(!empty($item['profile_picture'])): ?>
                                  <img src="<?php echo htmlspecialchars($item['profile_picture']); ?>" class="seller-avatar" alt="Avatar">
                              <?php else: ?>
                                  <div class="seller-avatar"><?php echo get_initials($item['full_name']); ?></div>
                              <?php endif; ?>
                              
                              <div class="seller-details">
                                  <span class="seller-name"><?php echo htmlspecialchars(explode(' ', $item['full_name'])[0]); ?></span>
                                  <?php if ($item['is_verified']): ?>
                                      <span class="seller-verified">✓ Verified</span>
                                  <?php endif; ?>
                              </div>
                          </div>
                          <div class="btn-view">View</div>
                      </div>
                  </div>
              </a>
          <?php endforeach; ?>
      </div>
  <?php endif; ?>
</section>

<!-- SEMESTER SALE BANNER & COUNTDOWN -->
<div class="semester-wrap reveal">
  <div class="semester-banner">
    <div>
      <div class="banner-tag">⏰ Semester End Sale</div>
      <h2 class="banner-title">Leaving campus soon?<br>Turn your stuff into <span class="hi">cash.</span></h2>
      <p class="banner-sub">Don't pack what you can sell — next year's intake is already looking.</p>
      <!-- DIRECT LINK TO SELL -->
      <a href="post_item.php" class="btn-amber-big">List Your Items Now →</a>
    </div>
    <div class="banner-countdown">
      <div class="countdown-block">
        <div class="countdown-num" id="cd-days">14</div>
        <div class="countdown-label">Days</div>
      </div>
      <div class="countdown-block">
        <div class="countdown-num" id="cd-hrs">08</div>
        <div class="countdown-label">Hours</div>
      </div>
      <div class="countdown-block">
        <div class="countdown-num" id="cd-min">42</div>
        <div class="countdown-label">Mins</div>
      </div>
      <div class="countdown-block">
        <div class="countdown-num" id="cd-sec">17</div>
        <div class="countdown-label">Secs</div>
      </div>
    </div>
  </div>
</div>

<!-- HOW IT WORKS -->
<section class="how-section reveal" id="how">
  <div class="section-header">
    <h2 class="section-title">How MILELE works</h2>
  </div>
  <div class="steps-grid">
    <div class="step-card">
      <div class="step-num">01</div>
      <div class="step-icon">🎓</div>
      <div class="step-title">Sign up securely</div>
      <p class="step-desc">Verify your student status with your campus email or Google account. Your profile is trusted from day one.</p>
    </div>
    <div class="step-card">
      <div class="step-num">02</div>
      <div class="step-icon">📸</div>
      <div class="step-title">Snap a photo, set your price</div>
      <p class="step-desc">Listing takes under 2 minutes. Add photos, write a quick description, choose a price, and you're live.</p>
    </div>
    <div class="step-card">
      <div class="step-num">03</div>
      <div class="step-icon">💳</div>
      <div class="step-title">Secure M-Pesa Checkout</div>
      <p class="step-desc">Buyers pay securely through our M-Pesa escrow integration. Funds are held safely until delivery.</p>
    </div>
    <div class="step-card">
      <div class="step-num">04</div>
      <div class="step-icon">🤝</div>
      <div class="step-title">Meet & Handover</div>
      <p class="step-desc">Meet at a safe campus location, hand over the item, and the escrow releases the funds directly to you.</p>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonials-section reveal">
  <div class="section-header">
    <h2 class="section-title">Students love MILELE</h2>
  </div>
  <div class="testimonials-grid">
    <div class="testimonial-card">
      <div class="testimonial-stars">★★★★★</div>
      <p class="testimonial-text">"Sold my entire dorm setup in one week at the end of 2nd year. Made over KES 18,000. Way better than lugging everything home or letting it rot in storage."</p>
      <div class="testimonial-author">
        <div class="author-avatar" style="background:#6366f1;">PM</div>
        <div>
          <div class="author-name">Purity M.</div>
          <div class="author-course">BSc Computer Science, UoN</div>
        </div>
      </div>
    </div>
    <div class="testimonial-card">
      <div class="testimonial-stars">★★★★★</div>
      <p class="testimonial-text">"Found all my 1st year textbooks for less than half the bookshop price. Saved me like KES 8,000. Absolute lifesaver when you're a broke fresher."</p>
      <div class="testimonial-author">
        <div class="author-avatar" style="background:#14b8a6;">DO</div>
        <div>
          <div class="author-name">David O.</div>
          <div class="author-course">BCom Finance, Strathmore</div>
        </div>
      </div>
    </div>
    <div class="testimonial-card">
      <div class="testimonial-stars">★★★★☆</div>
      <p class="testimonial-text">"The verified student profile thing is what sold me on this. I knew I was buying from a real student on campus — not some random internet stranger. Felt totally safe."</p>
      <div class="testimonial-author">
        <div class="author-avatar" style="background:#ec4899;">FN</div>
        <div>
          <div class="author-name">Fatuma N.</div>
          <div class="author-course">BA Psychology, USIU</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- SELL CTA -->
<section class="sell-cta reveal">
  <div class="sell-cta-inner">
    <div>
      <div class="sell-label">Start selling today</div>
      <h2>Your campus stuff<br>deserves a second life.</h2>
      <p>Join thousands of students already making money safely on campus.</p>
    </div>
    <div class="sell-cta-actions">
      <?php if (!$is_logged_in): ?>
          <a href="register.php" class="btn-dark">Create Free Account</a>
      <?php endif; ?>
      <!-- DIRECT LINK TO SELL -->
      <a href="post_item.php" class="btn-outline-dark" style="border-color: var(--indigo); color: var(--indigo);">Post a Listing</a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-grid">
    <div class="footer-brand">
      <div class="footer-brand-logo">MILELE</div>
      <p>The student marketplace built for campus life in Kenya. Buy and sell safely within your university community.</p>
      <div class="footer-social">
        <div class="social-btn">𝕏</div>
        <div class="social-btn">in</div>
        <div class="social-btn">ig</div>
        <div class="social-btn">yt</div>
      </div>
    </div>
    <div class="footer-col">
      <h4>Browse</h4>
      <ul>
        <li><a href="#browse">All Listings</a></li>
        <li><a href="index.php?category=Textbooks">Textbooks</a></li>
        <li><a href="index.php?category=Electronics">Electronics</a></li>
        <li><a href="index.php?category=Furniture">Furniture</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Sell</h4>
      <ul>
        <!-- DIRECT LINK TO SELL -->
        <li><a href="post_item.php">Post a Listing</a></li>
        <li><a href="profile.php">Seller Dashboard</a></li>
        <li><a href="#how">Pricing Tips</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Company</h4>
      <ul>
        <li><a href="#">About Us</a></li>
        <li><a href="#">Universities</a></li>
        <li><a href="#">Safety Policy</a></li>
        <li><a href="#">Contact</a></li>
        <li><a href="#">Terms</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© 2026 MILELE. Made with ❤️ for Kenyan students.</span>
    <span><a href="#">Privacy Policy</a> · <a href="#">Terms of Service</a></span>
  </div>
</footer>

<script>
  // Scroll Animations
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
  }, { threshold: 0.08 });
  document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

  // Semester Sale Countdown Timer
  (function countdown() {
    const end = new Date();
    end.setDate(end.getDate() + 14);
    end.setHours(end.getHours() + 8, end.getMinutes() + 42, 17, 0);
    function tick() {
      const diff = end - new Date();
      if (diff <= 0) return;
      const d = Math.floor(diff / 86400000);
      const h = Math.floor((diff % 86400000) / 3600000);
      const m = Math.floor((diff % 3600000) / 60000);
      const s = Math.floor((diff % 60000) / 1000);
      document.getElementById('cd-days').textContent = String(d).padStart(2,'0');
      document.getElementById('cd-hrs').textContent = String(h).padStart(2,'0');
      document.getElementById('cd-min').textContent = String(m).padStart(2,'0');
      document.getElementById('cd-sec').textContent = String(s).padStart(2,'0');
    }
    tick();
    setInterval(tick, 1000);
  })();
</script>
</body>
</html>