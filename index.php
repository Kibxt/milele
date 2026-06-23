<?php
// MILELE - Dynamic Marketplace Homepage

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

// 1. Check if user is logged in for the Navigation bar
$is_logged_in = isset($_SESSION['user_id']);

// 2. Fetch real listings from the database, newest first
try {
    // We join with the users table to get the seller's name and verification status
    $stmt = $pdo->query("
        SELECT l.*, u.full_name, u.is_verified 
        FROM listings l 
        JOIN users u ON l.seller_id = u.user_id 
        ORDER BY l.created_at DESC 
        LIMIT 12
    ");
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $listings = []; // Failsafe if the database isn't ready
}

// 3. Helper function to generate avatar initials (e.g., "Ken Lang'at" -> "KL")
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
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  /* YOUR EXACT BEAUTIFUL CSS REMAINS UNTOUCHED */
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

  .ticker-bar { background: var(--indigo); color: var(--amber); font-size: 12px; font-weight: 600; letter-spacing: 0.05em; padding: 8px 0; overflow: hidden; white-space: nowrap; }
  .ticker-inner { display: inline-block; animation: ticker 35s linear infinite; }
  .ticker-inner span { margin: 0 40px; }
  .ticker-inner span::before { content: '●'; margin-right: 10px; color: var(--mint); }
  @keyframes ticker { from { transform: translateX(0); } to { transform: translateX(-50%); } }

  nav { background: rgba(247,245,255,0.94); backdrop-filter: blur(14px); border-bottom: 1px solid var(--card-border); position: sticky; top: 0; z-index: 100; padding: 0 5%; display: flex; align-items: center; justify-content: space-between; height: 64px; }
  .nav-logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 22px; color: var(--indigo); text-decoration: none; display: flex; align-items: center; gap: 8px; }
  .logo-dot { width: 10px; height: 10px; background: var(--amber); border-radius: 50%; display: inline-block; animation: pulse 2s ease-in-out infinite; }
  @keyframes pulse { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.4); opacity: 0.7; } }
  .nav-links { display: flex; gap: 28px; list-style: none; }
  .nav-links a { font-size: 14px; font-weight: 500; color: var(--slate); text-decoration: none; transition: color 0.2s; }
  .nav-links a:hover { color: var(--indigo); }
  .nav-right { display: flex; align-items: center; gap: 12px; }
  .btn-ghost { background: none; border: 1.5px solid var(--indigo); color: var(--indigo); padding: 8px 18px; border-radius: 50px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; text-decoration: none;}
  .btn-ghost:hover { background: var(--indigo); color: var(--white); }
  .btn-primary { background: var(--amber); border: none; color: var(--indigo); padding: 9px 20px; border-radius: 50px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; box-shadow: 0 2px 12px rgba(245,166,35,0.35); text-decoration: none;}
  .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 20px rgba(245,166,35,0.5); }

  .hero { background: var(--indigo); color: var(--white); padding: 90px 5% 0; position: relative; overflow: hidden; min-height: 580px; display: flex; align-items: center; }
  .hero-bg-circle { position: absolute; border-radius: 50%; background: var(--indigo-mid); }
  .hero-bg-circle.c1 { width: 400px; height: 400px; top: -100px; right: -50px; opacity: 0.5; animation: float 8s ease-in-out infinite; }
  .hero-bg-circle.c2 { width: 250px; height: 250px; bottom: 40px; right: 200px; opacity: 0.3; animation: float 8s ease-in-out infinite; animation-delay: -3s; }
  .hero-bg-circle.c3 { width: 150px; height: 150px; top: 60px; right: 350px; opacity: 0.2; animation: float 8s ease-in-out infinite; animation-delay: -5s; }
  @keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-18px); } }
  .hero-content { position: relative; z-index: 2; max-width: 600px; }
  .hero-eyebrow { display: inline-flex; align-items: center; gap: 8px; background: rgba(245,166,35,0.15); border: 1px solid rgba(245,166,35,0.3); color: var(--amber); font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 6px 14px; border-radius: 50px; margin-bottom: 24px; }
  .eyebrow-dot { width: 6px; height: 6px; background: var(--amber); border-radius: 50%; animation: pulse 1.5s ease-in-out infinite; }
  .hero h1 { font-family: 'Syne', sans-serif; font-size: clamp(36px, 5vw, 58px); font-weight: 800; line-height: 1.05; margin-bottom: 20px; }
  .hero h1 .accent { color: var(--amber); }
  .hero-sub { font-size: 16px; line-height: 1.7; color: rgba(255,255,255,0.65); margin-bottom: 36px; max-width: 480px; }
  .hero-ctas { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 50px; }
  .btn-hero { background: var(--amber); border: none; color: var(--indigo); padding: 14px 30px; border-radius: 50px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; box-shadow: 0 4px 20px rgba(245,166,35,0.4); text-decoration: none;}
  .btn-hero:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(245,166,35,0.55); }
  .btn-hero-ghost { background: rgba(255,255,255,0.08); border: 1.5px solid rgba(255,255,255,0.25); color: var(--white); padding: 14px 30px; border-radius: 50px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; text-decoration: none;}
  .btn-hero-ghost:hover { background: rgba(255,255,255,0.15); }
  .hero-stats { display: flex; gap: 36px; flex-wrap: wrap; }
  .hero-stat-num { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; color: var(--white); }
  .hero-stat-num span { color: var(--amber); }
  .hero-stat-label { font-size: 12px; color: rgba(255,255,255,0.5); margin-top: 2px; }
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

  .search-section { background: var(--white); padding: 40px 5%; border-bottom: 1px solid var(--card-border); }
  .search-wrap { max-width: 700px; margin: 0 auto; position: relative; }
  .search-input { width: 100%; height: 56px; border: 2px solid var(--card-border); border-radius: 50px; padding: 0 60px 0 54px; font-size: 15px; font-family: 'Inter', sans-serif; color: var(--indigo); background: var(--chalk); outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
  .search-input:focus { border-color: var(--amber); box-shadow: 0 0 0 4px rgba(245,166,35,0.12); }
  .search-input::placeholder { color: var(--slate); }
  .search-icon-wrap { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: var(--slate); font-size: 18px; }
  .search-btn { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); background: var(--amber); border: none; color: var(--indigo); font-size: 13px; font-weight: 700; padding: 10px 22px; border-radius: 50px; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; }
  .search-btn:hover { background: var(--indigo); color: var(--white); }
  .filter-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; justify-content: center; }
  .pill { background: var(--chalk); border: 1.5px solid var(--card-border); color: var(--indigo); font-size: 13px; font-weight: 500; padding: 6px 16px; border-radius: 50px; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
  .pill:hover, .pill.active { background: var(--indigo); color: var(--white); border-color: var(--indigo); }

  .categories-section { padding: 60px 5%; background: var(--chalk); }
  .section-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 32px; }
  .section-title { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; color: var(--indigo); }
  .section-link { color: var(--amber); font-size: 13px; font-weight: 600; text-decoration: none; }
  .category-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
  .cat-card { background: var(--white); border: 1.5px solid var(--card-border); border-radius: 16px; padding: 22px 16px; text-align: center; cursor: pointer; transition: all 0.25s; text-decoration: none; color: var(--indigo); display: block; }
  .cat-card:hover { border-color: var(--amber); transform: translateY(-3px); box-shadow: 0 8px 24px rgba(245,166,35,0.15); }
  .cat-icon { font-size: 32px; margin-bottom: 10px; display: block; }
  .cat-name { font-size: 13px; font-weight: 600; }
  .cat-count { font-size: 11px; color: var(--slate); margin-top: 3px; }

  .listings-section { padding: 60px 5%; background: var(--white); }
  .listings-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; gap: 16px; flex-wrap: wrap; }
  .sort-select { border: 1.5px solid var(--card-border); border-radius: 8px; padding: 8px 14px; font-size: 13px; font-family: 'Inter', sans-serif; color: var(--indigo); background: var(--chalk); outline: none; cursor: pointer; }
  .listings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; }
  .listing-card { background: var(--white); border: 1.5px solid var(--card-border); border-radius: 18px; overflow: hidden; transition: all 0.3s; cursor: pointer; position: relative; }
  .listing-card:hover { border-color: var(--amber); transform: translateY(-4px); box-shadow: 0 12px 36px rgba(26,16,64,0.12); }
  .listing-img { width: 100%; height: 190px; object-fit: cover; display: block; }
  .listing-fav { position: absolute; top: 12px; right: 12px; width: 32px; height: 32px; background: rgba(255,255,255,0.9); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: none; font-size: 15px; transition: all 0.2s; }
  .listing-fav:hover { transform: scale(1.15); }
  .listing-condition { position: absolute; top: 12px; left: 12px; font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 4px 10px; border-radius: 50px; }
  .cond-new { background: var(--mint); color: var(--indigo); }
  .listing-body { padding: 14px 16px 18px; }
  .listing-campus { font-size: 11px; color: var(--slate); font-weight: 500; margin-bottom: 5px; }
  .listing-title { font-size: 15px; font-weight: 600; color: var(--indigo); line-height: 1.3; margin-bottom: 8px; }
  .listing-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
  .listing-price { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; color: var(--indigo); }
  .listing-price .kes { font-size: 13px; font-weight: 600; color: var(--slate); }
  .listing-btn { background: var(--indigo); color: var(--white); border: none; padding: 7px 16px; border-radius: 50px; font-size: 12px; font-weight: 700; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; text-decoration: none;}
  .listing-btn:hover { background: var(--amber); color: var(--indigo); }
  .listing-seller { display: flex; align-items: center; gap: 6px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--card-border); }
  .seller-avatar { width: 24px; height: 24px; border-radius: 50%; font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center; color: var(--white); flex-shrink: 0; }
  .seller-name { font-size: 12px; color: var(--slate); }
  .seller-verified { color: var(--mint); font-size: 11px; font-weight: 700; margin-left: auto; }
  .badge-new { background: var(--coral); color: var(--white); font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 4px; letter-spacing: 0.06em; margin-left: 6px; vertical-align: middle; }
  
  /* Rest of sections */
  .semester-wrap { padding: 0 0 60px; background: var(--white); }
  .semester-banner { margin: 0 5%; background: var(--indigo); border-radius: 24px; padding: 50px 6%; display: flex; align-items: center; justify-content: space-between; gap: 30px; overflow: hidden; position: relative; flex-wrap: wrap; }
  .semester-banner::before { content: ''; position: absolute; width: 300px; height: 300px; border-radius: 50%; background: rgba(245,166,35,0.1); top: -100px; right: 100px; pointer-events: none; }
  .banner-tag { display: inline-block; background: rgba(245,166,35,0.2); color: var(--amber); font-size: 11px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; padding: 5px 14px; border-radius: 50px; margin-bottom: 16px; }
  .banner-title { font-family: 'Syne', sans-serif; font-size: clamp(24px, 3vw, 38px); font-weight: 800; color: var(--white); line-height: 1.15; margin-bottom: 12px; }
  .banner-title .hi { color: var(--amber); }
  .banner-sub { font-size: 15px; color: rgba(255,255,255,0.6); max-width: 420px; line-height: 1.6; margin-bottom: 28px; }
  .btn-amber-big { background: var(--amber); border: none; color: var(--indigo); padding: 14px 32px; border-radius: 50px; font-size: 15px; font-weight: 700; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; }
  .btn-amber-big:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(245,166,35,0.45); }
  .banner-countdown { display: flex; gap: 16px; position: relative; z-index: 1; flex-wrap: wrap; }
  .countdown-block { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12); border-radius: 12px; width: 70px; text-align: center; padding: 12px 8px; }
  .countdown-num { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; color: var(--white); }
  .countdown-label { font-size: 10px; color: rgba(255,255,255,0.45); text-transform: uppercase; letter-spacing: 0.07em; }

  .how-section { padding: 80px 5%; background: var(--chalk); }
  .steps-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-top: 40px; }
  .step-card { background: var(--white); border: 1.5px solid var(--card-border); border-radius: 20px; padding: 28px 24px; transition: all 0.3s; }
  .step-card:hover { border-color: var(--amber); transform: translateY(-4px); }
  .step-num { font-family: 'Syne', sans-serif; font-size: 40px; font-weight: 800; color: var(--amber); margin-bottom: 16px; opacity: 0.7; }
  .step-icon { font-size: 28px; margin-bottom: 12px; }
  .step-title { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; color: var(--indigo); margin-bottom: 10px; }
  .step-desc { font-size: 14px; color: var(--slate); line-height: 1.6; }

  .testimonials-section { padding: 80px 5%; background: var(--white); }
  .testimonials-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 40px; }
  .testimonial-card { background: var(--chalk); border: 1.5px solid var(--card-border); border-radius: 18px; padding: 24px; transition: all 0.3s; }
  .testimonial-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(26,16,64,0.08); }
  .testimonial-stars { color: var(--amber); font-size: 14px; margin-bottom: 12px; }
  .testimonial-text { font-size: 14px; line-height: 1.65; color: var(--indigo); margin-bottom: 20px; font-style: italic; }
  .testimonial-author { display: flex; align-items: center; gap: 10px; }
  .author-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: var(--white); flex-shrink: 0; }
  .author-name { font-size: 14px; font-weight: 600; color: var(--indigo); }
  .author-course { font-size: 12px; color: var(--slate); }

  .sell-cta { padding: 40px 5% 80px; background: var(--chalk); }
  .sell-cta-inner { background: var(--amber); border-radius: 24px; padding: 50px; display: flex; justify-content: space-between; align-items: center; gap: 30px; flex-wrap: wrap; }
  .sell-label { font-size: 12px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(26,16,64,0.55); margin-bottom: 10px; }
  .sell-cta-inner h2 { font-family: 'Syne', sans-serif; font-size: clamp(24px, 3vw, 36px); font-weight: 800; color: var(--indigo); line-height: 1.1; margin-bottom: 12px; }
  .sell-cta-inner p { font-size: 15px; color: rgba(26,16,64,0.65); line-height: 1.6; }
  .sell-cta-actions { display: flex; gap: 12px; flex-wrap: wrap; }
  .btn-dark { background: var(--indigo); color: var(--white); border: none; padding: 14px 32px; border-radius: 50px; font-size: 15px; font-weight: 700; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; text-decoration: none;}
  .btn-dark:hover { background: var(--indigo-mid); transform: translateY(-1px); }
  .btn-outline-dark { background: transparent; color: var(--indigo); border: 2px solid var(--indigo); padding: 14px 32px; border-radius: 50px; font-size: 15px; font-weight: 700; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; }
  .btn-outline-dark:hover { background: var(--indigo); color: var(--white); }

  .trust-bar { background: var(--indigo); padding: 24px 5%; display: flex; justify-content: center; gap: 50px; flex-wrap: wrap; }
  .trust-item { display: flex; align-items: center; gap: 10px; color: rgba(255,255,255,0.6); font-size: 13px; font-weight: 500; }
  .trust-icon { font-size: 20px; }

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

  .load-more-btn { background: none; border: 2px solid var(--indigo); color: var(--indigo); padding: 13px 36px; border-radius: 50px; font-size: 14px; font-weight: 700; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; }
  .load-more-btn:hover { background: var(--indigo); color: var(--white); }
  
  /* Fallback empty state styling */
  .empty-state { text-align: center; padding: 40px; background: var(--chalk); border-radius: 16px; border: 1.5px dashed var(--card-border); grid-column: 1 / -1; }
  .empty-state h3 { color: var(--indigo); margin-bottom: 10px; font-family: 'Syne', sans-serif; }
  .empty-state p { color: var(--slate); font-size: 14px; margin-bottom: 20px;}
</style>
</head>
<body>

<div class="ticker-bar" aria-hidden="true">
  <div class="ticker-inner">
    <span>MacBook Pro 2022 — KES 85,000</span>
    <span>Physics Textbook Bundle — KES 1,200</span>
    <span>Mini Fridge (barely used) — KES 4,500</span>
    <span>Graphic Calculator TI-84 — KES 3,200</span>
    <span>Dorm Desk Chair — KES 2,800</span>
    <span>iPhone 14 — KES 38,000</span>
    <span>Engineering Drawing Set — KES 950</span>
    <span>Semester End Sale — All items reduced!</span>
  </div>
</div>

<nav>
  <a href="index.php" class="nav-logo"><span class="logo-dot"></span>MILELE</a>
  <ul class="nav-links">
    <li><a href="#browse">Browse</a></li>
    <li><a href="#categories">Categories</a></li>
    <li><a href="#how">How it works</a></li>
    <li><a href="create_listing.php">Sell</a></li>
  </ul>
  <div class="nav-right">
    <?php if ($is_logged_in): ?>
        <a href="profile.php" class="btn-ghost">Dashboard</a>
        <a href="logout.php" class="btn-ghost" style="border-color: var(--coral); color: var(--coral);">Log Out</a>
    <?php else: ?>
        <a href="login.php" class="btn-ghost">Log in</a>
        <a href="register.php" class="btn-primary">Sign Up</a>
    <?php endif; ?>
  </div>
</nav>

<section class="hero">
  <div class="hero-bg-circle c1"></div>
  <div class="hero-bg-circle c2"></div>
  <div class="hero-bg-circle c3"></div>
  <div class="hero-content">
    <div class="hero-eyebrow"><span class="eyebrow-dot"></span>Campus Marketplace — Kenya</div>
    <h1>Your campus.<br>Your <span class="accent">deals.</span><br>Your community.</h1>
    <p class="hero-sub">Buy, sell, and trade textbooks, electronics, furniture, and more — directly with fellow students on your campus. No strangers. Zero fees. Just campus-to-campus.</p>
    <div class="hero-ctas">
      <a href="#browse" class="btn-hero">Browse Deals →</a>
      <a href="create_listing.php" class="btn-hero-ghost">Sell Something</a>
    </div>
    <div class="hero-stats">
      <div class="hero-stat">
        <div class="hero-stat-num">4,<span>200</span>+</div>
        <div class="hero-stat-label">Active listings</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num">12<span>K</span></div>
        <div class="hero-stat-label">Students joined</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num"><span>KES</span> 0</div>
        <div class="hero-stat-label">Platform fees</div>
      </div>
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

<div class="trust-bar">
  <div class="trust-item"><span class="trust-icon">🎓</span> Student-verified profiles</div>
  <div class="trust-item"><span class="trust-icon">🔒</span> Safe campus meetups</div>
  <div class="trust-item"><span class="trust-icon">💬</span> In-app messaging</div>
  <div class="trust-item"><span class="trust-icon">⭐</span> Seller ratings system</div>
  <div class="trust-item"><span class="trust-icon">🚫</span> Zero platform fees</div>
</div>

<section class="search-section" id="browse">
  <div class="search-wrap">
    <span class="search-icon-wrap">🔍</span>
    <input class="search-input" type="text" placeholder="Search textbooks, laptops, furniture, bikes...">
    <button class="search-btn">Search</button>
  </div>
  <div class="filter-pills">
    <button class="pill active">✨ All Items</button>
    <button class="pill">📚 Textbooks</button>
    <button class="pill">💻 Electronics</button>
    <button class="pill">🛋️ Furniture</button>
  </div>
</section>

<section class="categories-section reveal" id="categories">
  <div class="section-header">
    <h2 class="section-title">Browse by Category</h2>
    <a href="#" class="section-link">View all →</a>
  </div>
  <div class="category-grid">
    <a href="#" class="cat-card"><span class="cat-icon">📚</span><div class="cat-name">Textbooks</div><div class="cat-count">842 listings</div></a>
    <a href="#" class="cat-card"><span class="cat-icon">💻</span><div class="cat-name">Electronics</div><div class="cat-count">431 listings</div></a>
    <a href="#" class="cat-card"><span class="cat-icon">🛋️</span><div class="cat-name">Furniture</div><div class="cat-count">219 listings</div></a>
    <a href="#" class="cat-card"><span class="cat-icon">👗</span><div class="cat-name">Clothes</div><div class="cat-count">674 listings</div></a>
  </div>
</section>

<section class="listings-section reveal">
  <div class="listings-toolbar">
    <h2 class="section-title">Fresh Listings <span class="badge-new">LIVE</span></h2>
    <select class="sort-select">
      <option>Sort: Newest first</option>
      <option>Sort: Price — Low to High</option>
      <option>Sort: Price — High to Low</option>
    </select>
  </div>
  
  <div class="listings-grid">
    <?php if (empty($listings)): ?>
        <div class="empty-state">
            <h3>No items listed yet!</h3>
            <p>Be the first to list an item on the new MILELE marketplace.</p>
            <a href="create_listing.php" class="btn-primary" style="display:inline-block; padding: 12px 24px;">Post a Listing</a>
        </div>
    <?php else: ?>
        <?php foreach ($listings as $item): ?>
            <div class="listing-card">
              <img class="listing-img" src="<?php echo htmlspecialchars($item['image_path'] ?? 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500&q=80'); ?>" alt="Item Image">
              
              <span class="listing-condition cond-new"><?php echo htmlspecialchars($item['category'] ?? 'Item'); ?></span>
              <button class="listing-fav">🤍</button>
              
              <div class="listing-body">
                <div class="listing-title"><?php echo htmlspecialchars($item['title']); ?></div>
                <div class="listing-footer">
                  <div class="listing-price"><span class="kes">KES </span><?php echo number_format($item['price']); ?></div>
                  <a href="checkout.php?id=<?php echo $item['listing_id']; ?>" class="listing-btn">View</a>
                </div>
                
                <div class="listing-seller">
                  <div class="seller-avatar" style="background:#6366f1;">
                      <?php echo get_initials($item['full_name']); ?>
                  </div>
                  <span class="seller-name"><?php echo htmlspecialchars(explode(' ', $item['full_name'])[0]); ?></span>
                  
                  <?php if ($item['is_verified']): ?>
                      <span class="seller-verified">✓ Verified</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
  </div>
  
  <?php if (!empty($listings)): ?>
      <div style="text-align:center; margin-top: 36px;">
        <button class="load-more-btn">Load More Listings →</button>
      </div>
  <?php endif; ?>
</section>

<div class="semester-wrap">
  <div class="semester-banner reveal">
    <div>
      <div class="banner-tag">⏰ Semester End Sale</div>
      <h2 class="banner-title">Leaving campus soon?<br>Turn your stuff into <span class="hi">cash.</span></h2>
      <p class="banner-sub">Every semester, thousands of students list their dorm essentials, electronics, and textbooks. Don't pack what you can sell — next year's intake is already looking.</p>
      <a href="create_listing.php" class="btn-amber-big" style="display:inline-block; text-decoration:none;">List Your Items Now →</a>
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
    </div>
  </div>
</div>

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

<footer>
  <div class="footer-grid">
    <div class="footer-brand">
      <div class="footer-brand-logo">MILELE</div>
      <p>The secure student marketplace built for campus life in Kenya.</p>
    </div>
    <div class="footer-col">
      <h4>Company</h4>
      <ul>
        <li><a href="#">About Us</a></li>
        <li><a href="#">Safety Policy</a></li>
        <li><a href="#">Contact</a></li>
        <li><a href="#">Terms</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© 2026 MILELE. Made with ❤️ for Kenyan students.</span>
  </div>
</footer>

<script>
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
  }, { threshold: 0.08 });
  document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

  document.querySelectorAll('.pill').forEach(pill => {
    pill.addEventListener('click', () => {
      document.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
    });
  });

  document.querySelectorAll('.listing-fav').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      btn.textContent = btn.textContent.trim() === '🤍' ? '❤️' : '🤍';
    });
  });
</script>
</body>
</html>