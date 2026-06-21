<?php
// MILELE - Premium Global Feed (With Live Notification Engine)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS);
$category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS);

// ==========================================
// 🔔 UNREAD MESSAGE TRACKER
// ==========================================
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

// Load Feed Items
try {
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
    die("<div style='background:#030712; color:#f87171; padding:60px; text-align:center; font-family:sans-serif; font-size:1.1rem;'>Could not load listings. Please try again.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MILELE | The Campus Marketplace</title>
    <meta name="description" content="Buy, sell and swap with students at your university. Zero scams. Verified students only.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
    /* ============================================================
       DESIGN TOKENS — MILELE DARK CAMPUS PREMIUM THEME
       Night ink base + electric teal accent + warm amber flash
    ============================================================ */
    :root {
        --ink:        #030712;   /* near-black — main bg */
        --ink2:       #0c111f;   /* card surfaces */
        --ink3:       #131929;   /* elevated surfaces */
        --line:       rgba(255,255,255,0.06);
        --line2:      rgba(255,255,255,0.10);

        --teal:       #2DD4BF;   /* primary accent */
        --teal-dk:    #0d9488;
        --teal-glow:  rgba(45,212,191,0.15);
        --teal-glow2: rgba(45,212,191,0.06);

        --amber:      #fbbf24;   /* secondary flash — urgency, prices */
        --red:        #ef4444;
        --green:      #22c55e;

        --txt:        #f1f5f9;
        --txt2:       #94a3b8;
        --txt3:       #475569;

        --r:          16px;
        --r-sm:       10px;
        --r-pill:     999px;
        --shadow:     0 8px 32px rgba(0,0,0,0.5);
        --shadow-card: 0 4px 24px rgba(0,0,0,0.4);

        --font-head:  'Sora', sans-serif;
        --font-body:  'Inter', sans-serif;
    }

    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    html { scroll-behavior: smooth; }

    body {
        background: var(--ink);
        color: var(--txt);
        font-family: var(--font-body);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
    }

    /* ============================================================
       ANNOUNCEMENT BAR
    ============================================================ */
    .announce-bar {
        background: linear-gradient(90deg, var(--teal-dk), var(--teal));
        color: #030712;
        text-align: center;
        padding: 9px 20px;
        font-size: 0.82rem;
        font-weight: 600;
        letter-spacing: 0.2px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    .announce-bar a {
        color: #030712;
        text-decoration: underline;
        font-weight: 700;
        white-space: nowrap;
    }
    .announce-pulse {
        display: inline-block;
        width: 7px; height: 7px;
        border-radius: 50%;
        background: #030712;
        opacity: 0.5;
        animation: blink 1.6s ease-in-out infinite;
    }
    @keyframes blink { 0%,100%{opacity:0.5} 50%{opacity:1} }

    /* ============================================================
       NAVIGATION
    ============================================================ */
    .nav-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 40px;
        height: 66px;
        background: rgba(3,7,18,0.85);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border-bottom: 1px solid var(--line);
        position: sticky;
        top: 0;
        z-index: 500;
        gap: 20px;
    }

    .nav-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        flex-shrink: 0;
    }
    .nav-logo-icon {
        width: 36px; height: 36px;
        background: var(--teal);
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
    }
    .nav-logo-icon svg { width: 20px; height: 20px; stroke: #030712; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
    .nav-brand-name {
        font-family: var(--font-head);
        font-weight: 800;
        font-size: 1.25rem;
        color: #fff;
        letter-spacing: -0.5px;
    }
    .nav-brand-name span { color: var(--teal); }

    .nav-search-wrap {
        flex: 1;
        max-width: 440px;
        display: flex;
        align-items: center;
        background: var(--ink3);
        border: 1px solid var(--line2);
        border-radius: var(--r-pill);
        padding: 0 14px;
        height: 40px;
        gap: 8px;
        transition: border-color .2s;
    }
    .nav-search-wrap:focus-within { border-color: var(--teal); }
    .nav-search-wrap svg { width: 16px; height: 16px; stroke: var(--txt3); fill: none; stroke-width: 2; flex-shrink: 0; }
    .nav-search-input {
        flex: 1;
        background: transparent;
        border: none;
        outline: none;
        color: var(--txt);
        font-family: var(--font-body);
        font-size: 0.875rem;
    }
    .nav-search-input::placeholder { color: var(--txt3); }

    .nav-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }

    .nav-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: var(--ink3);
        color: var(--txt);
        border: 1px solid var(--line2);
        border-radius: var(--r-sm);
        font-family: var(--font-body);
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        transition: all .18s;
        cursor: pointer;
        position: relative;
        white-space: nowrap;
    }
    .nav-btn:hover { background: var(--ink2); border-color: var(--line2); color: #fff; }
    .nav-btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; flex-shrink: 0; }

    .nav-btn-primary {
        background: var(--teal);
        color: #030712;
        border-color: var(--teal);
        font-weight: 700;
    }
    .nav-btn-primary:hover { background: #fff; border-color: #fff; color: #030712; }

    .notif-badge {
        position: absolute;
        top: -5px; right: -5px;
        background: var(--red);
        color: #fff;
        font-size: 0.68rem;
        font-weight: 700;
        padding: 2px 5px;
        border-radius: var(--r-pill);
        border: 2px solid var(--ink);
        animation: notifpulse 2s infinite;
        pointer-events: none;
        min-width: 18px;
        text-align: center;
    }
    @keyframes notifpulse {
        0%   { box-shadow: 0 0 0 0 rgba(239,68,68,0.6); }
        70%  { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
        100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
    }

    /* ============================================================
       TOAST NOTIFICATION
    ============================================================ */
    .toast-alert {
        position: fixed;
        bottom: -120px;
        right: 24px;
        background: var(--ink3);
        border: 1px solid var(--line2);
        border-left: 3px solid var(--teal);
        padding: 14px 18px;
        border-radius: var(--r);
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: var(--shadow);
        transition: bottom 0.45s cubic-bezier(0.175,0.885,0.32,1.275);
        z-index: 9999;
        min-width: 280px;
        max-width: 340px;
    }
    .toast-alert.show { bottom: 24px; }
    .toast-icon { font-size: 1.6rem; line-height: 1; flex-shrink: 0; }
    .toast-body { flex: 1; }
    .toast-title { font-weight: 700; color: #fff; font-size: 0.9rem; margin-bottom: 2px; }
    .toast-sub { color: var(--txt2); font-size: 0.78rem; }
    .toast-link { color: var(--teal); font-weight: 600; font-size: 0.85rem; text-decoration: none; white-space: nowrap; flex-shrink: 0; }
    .toast-link:hover { text-decoration: underline; }

    /* ============================================================
       HERO
    ============================================================ */
    .hero {
        position: relative;
        overflow: hidden;
        padding: 0;
    }

    /* Full-bleed split hero */
    .hero-split {
        display: grid;
        grid-template-columns: 1fr 1fr;
        min-height: 520px;
    }

    .hero-left {
        padding: 64px 48px 64px 48px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
        z-index: 2;
        background: linear-gradient(135deg, #030712 60%, rgba(3,7,18,0.9) 100%);
    }
    .hero-left::after {
        content: '';
        position: absolute;
        top: 0; right: 0; bottom: 0;
        width: 1px;
        background: linear-gradient(to bottom, transparent, var(--teal), transparent);
    }

    .hero-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: var(--teal-glow);
        border: 1px solid rgba(45,212,191,0.25);
        color: var(--teal);
        padding: 5px 13px;
        border-radius: var(--r-pill);
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        width: fit-content;
        margin-bottom: 20px;
    }
    .hero-live-dot {
        width: 6px; height: 6px;
        border-radius: 50%;
        background: var(--teal);
        animation: blink 1.4s ease-in-out infinite;
    }

    .hero-h1 {
        font-family: var(--font-head);
        font-size: 3.2rem;
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -1.5px;
        color: #fff;
        margin-bottom: 16px;
    }
    .hero-h1 em {
        font-style: normal;
        color: var(--teal);
        display: block;
    }
    .hero-h1 .accent-line {
        color: var(--amber);
    }

    .hero-sub {
        color: var(--txt2);
        font-size: 1rem;
        line-height: 1.75;
        max-width: 400px;
        margin-bottom: 32px;
    }
    .hero-sub strong { color: var(--txt); font-weight: 600; }

    /* Hero search bar */
    .hero-search-form {
        display: flex;
        background: var(--ink3);
        border: 1px solid var(--line2);
        border-radius: var(--r);
        padding: 6px 6px 6px 18px;
        gap: 8px;
        align-items: center;
        margin-bottom: 20px;
        max-width: 480px;
        transition: border-color .2s;
    }
    .hero-search-form:focus-within { border-color: var(--teal); box-shadow: 0 0 0 3px var(--teal-glow); }
    .hero-search-form svg { width: 18px; height: 18px; stroke: var(--txt3); fill: none; stroke-width: 2; flex-shrink: 0; }
    .hero-search-input {
        flex: 1;
        background: transparent;
        border: none;
        outline: none;
        color: var(--txt);
        font-family: var(--font-body);
        font-size: 0.95rem;
        padding: 6px 0;
    }
    .hero-search-input::placeholder { color: var(--txt3); }
    .hero-search-btn {
        background: var(--teal);
        color: #030712;
        border: none;
        padding: 10px 22px;
        border-radius: 10px;
        font-family: var(--font-body);
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        white-space: nowrap;
        transition: all .18s;
    }
    .hero-search-btn:hover { background: #fff; }

    /* Hero quick links */
    .hero-quick {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .hero-quick-label {
        font-size: 0.75rem;
        color: var(--txt3);
        width: 100%;
        margin-bottom: 2px;
    }
    .quick-chip {
        display: flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        background: var(--ink3);
        border: 1px solid var(--line);
        border-radius: var(--r-pill);
        font-size: 0.8rem;
        color: var(--txt2);
        text-decoration: none;
        transition: all .18s;
        white-space: nowrap;
    }
    .quick-chip:hover { background: var(--teal-glow); border-color: rgba(45,212,191,0.3); color: var(--teal); }

    /* Hero right — image collage */
    .hero-right {
        position: relative;
        overflow: hidden;
        background: var(--ink2);
    }
    .hero-collage {
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 1fr 1fr;
        height: 100%;
        gap: 2px;
    }
    .hero-collage-img {
        width: 100%; height: 100%;
        object-fit: cover;
        display: block;
        filter: brightness(0.7) saturate(0.9);
        transition: filter .3s;
    }
    .hero-collage-img:hover { filter: brightness(0.85) saturate(1); }
    .hero-collage-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to right, #030712 0%, transparent 30%, transparent 70%, rgba(3,7,18,0.4) 100%);
        pointer-events: none;
    }

    /* Floating stat cards on hero image */
    .hero-float-card {
        position: absolute;
        background: rgba(12,17,31,0.88);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--line2);
        border-radius: var(--r-sm);
        padding: 10px 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: floatup 3s ease-in-out infinite;
    }
    @keyframes floatup { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-5px)} }
    .hero-float-card.card-a { bottom: 90px; right: 24px; animation-delay: 0s; }
    .hero-float-card.card-b { top: 80px; right: 24px; animation-delay: 1s; }
    .hero-float-icon { font-size: 1.3rem; }
    .hero-float-num { font-family: var(--font-head); font-size: 1.1rem; font-weight: 800; color: #fff; line-height: 1; }
    .hero-float-label { font-size: 0.72rem; color: var(--txt2); }

    /* Hero stats row */
    .hero-stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        border-top: 1px solid var(--line);
    }
    .hero-stat {
        padding: 18px 24px;
        text-align: center;
        border-right: 1px solid var(--line);
        background: var(--ink2);
    }
    .hero-stat:last-child { border-right: none; }
    .stat-num {
        font-family: var(--font-head);
        font-size: 1.5rem;
        font-weight: 800;
        color: #fff;
        line-height: 1;
        margin-bottom: 3px;
    }
    .stat-num span { color: var(--teal); }
    .stat-label { font-size: 0.75rem; color: var(--txt3); }

    /* ============================================================
       TRUST BAR
    ============================================================ */
    .trust-bar {
        background: var(--ink2);
        border-bottom: 1px solid var(--line);
        padding: 0 40px;
        display: flex;
        align-items: center;
        gap: 0;
        overflow-x: auto;
        scrollbar-width: none;
    }
    .trust-bar::-webkit-scrollbar { display: none; }
    .trust-item {
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 13px 20px;
        font-size: 0.8rem;
        color: var(--txt3);
        white-space: nowrap;
        border-right: 1px solid var(--line);
        flex-shrink: 0;
    }
    .trust-item:last-child { border-right: none; }
    .trust-item svg { width: 14px; height: 14px; stroke: var(--teal); fill: none; stroke-width: 2.2; stroke-linecap: round; flex-shrink: 0; }

    /* ============================================================
       SOCIAL PROOF STRIP (real student photos)
    ============================================================ */
    .proof-strip {
        padding: 48px 40px;
        background: linear-gradient(180deg, var(--ink2) 0%, var(--ink) 100%);
        border-bottom: 1px solid var(--line);
    }
    .proof-strip-inner { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 16px; }
    .proof-card {
        background: var(--ink3);
        border: 1px solid var(--line);
        border-radius: var(--r);
        overflow: hidden;
        position: relative;
        cursor: pointer;
        transition: all .22s;
    }
    .proof-card:hover { border-color: rgba(45,212,191,0.3); transform: translateY(-3px); }
    .proof-card img { width: 100%; height: 140px; object-fit: cover; display: block; filter: brightness(0.65) saturate(0.8); transition: filter .3s; }
    .proof-card:hover img { filter: brightness(0.8) saturate(1); }
    .proof-card-body { padding: 14px; }
    .proof-cat { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--teal); margin-bottom: 4px; }
    .proof-name { font-size: 0.875rem; font-weight: 600; color: var(--txt); margin-bottom: 2px; }
    .proof-detail { font-size: 0.78rem; color: var(--txt2); }
    .proof-price { font-family: var(--font-head); font-size: 1rem; font-weight: 700; color: var(--amber); margin-top: 6px; }
    .proof-badge {
        position: absolute;
        top: 10px; left: 10px;
        background: rgba(3,7,18,0.8);
        backdrop-filter: blur(8px);
        color: #fff;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: var(--r-pill);
        border: 1px solid var(--line2);
    }

    /* ============================================================
       LIFE ON CAMPUS SECTION (vibe-setter photos)
    ============================================================ */
    .campus-life {
        padding: 48px 40px;
        background: var(--ink);
        border-bottom: 1px solid var(--line);
    }
    .campus-life-inner { max-width: 1200px; margin: 0 auto; }
    .campus-life-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr;
        grid-template-rows: 200px 200px;
        gap: 12px;
        margin-top: 24px;
    }
    .campus-photo {
        border-radius: var(--r);
        overflow: hidden;
        position: relative;
    }
    .campus-photo.big { grid-row: 1 / 3; }
    .campus-photo img { width: 100%; height: 100%; object-fit: cover; display: block; filter: brightness(0.6) saturate(0.85); transition: all .35s; }
    .campus-photo:hover img { filter: brightness(0.8) saturate(1); transform: scale(1.02); }
    .campus-photo-label {
        position: absolute;
        bottom: 12px; left: 12px;
        background: rgba(3,7,18,0.75);
        backdrop-filter: blur(8px);
        color: #fff;
        font-size: 0.78rem;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: var(--r-pill);
        border: 1px solid var(--line2);
    }

    /* ============================================================
       MAIN LAYOUT
    ============================================================ */
    .page-wrap {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 24px 80px;
        flex: 1;
    }

    /* ============================================================
       SECTION HEADER
    ============================================================ */
    .sec-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 20px;
        gap: 16px;
    }
    .sec-head-left {}
    .sec-title {
        font-family: var(--font-head);
        font-size: 1.25rem;
        font-weight: 700;
        color: #fff;
        line-height: 1.2;
    }
    .sec-sub { font-size: 0.82rem; color: var(--txt3); margin-top: 3px; }
    .sec-link {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--teal);
        text-decoration: none;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
    }
    .sec-link:hover { text-decoration: underline; }
    .sec-link svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

    /* ============================================================
       FEATURED BANNERS
    ============================================================ */
    .featured-banners {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 48px;
    }
    .feat-banner {
        border-radius: var(--r);
        overflow: hidden;
        position: relative;
        min-height: 200px;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 24px;
        text-decoration: none;
        cursor: pointer;
        transition: transform .22s;
    }
    .feat-banner:hover { transform: translateY(-3px); }
    .feat-banner img {
        position: absolute;
        inset: 0;
        width: 100%; height: 100%;
        object-fit: cover;
        transition: transform .35s;
    }
    .feat-banner:hover img { transform: scale(1.04); }
    .feat-banner-overlay {
        position: absolute;
        inset: 0;
        pointer-events: none;
    }
    .feat-banner-content { position: relative; z-index: 1; }
    .feat-banner-tag {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        padding: 3px 10px;
        border-radius: var(--r-pill);
        margin-bottom: 8px;
    }
    .feat-banner h3 {
        font-family: var(--font-head);
        font-size: 1.3rem;
        font-weight: 800;
        color: #fff;
        line-height: 1.25;
        margin-bottom: 4px;
    }
    .feat-banner p { font-size: 0.82rem; color: rgba(255,255,255,0.7); margin-bottom: 14px; }
    .feat-banner-btn {
        display: inline-block;
        padding: 8px 18px;
        border-radius: var(--r-pill);
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
        border: none;
        font-family: var(--font-body);
        transition: all .18s;
    }
    .feat-banner-btn:hover { transform: scale(1.04); }
    .feat-a { }
    .feat-a img { filter: brightness(0.45) saturate(0.8); }
    .feat-a .feat-banner-overlay { background: linear-gradient(135deg, rgba(45,212,191,0.5) 0%, rgba(3,7,18,0.6) 100%); }
    .feat-a .feat-banner-tag { background: rgba(45,212,191,0.2); color: var(--teal); border: 1px solid rgba(45,212,191,0.3); }
    .feat-a .feat-banner-btn { background: var(--teal); color: #030712; }
    .feat-b img { filter: brightness(0.4) saturate(0.7); }
    .feat-b .feat-banner-overlay { background: linear-gradient(135deg, rgba(251,191,36,0.45) 0%, rgba(3,7,18,0.65) 100%); }
    .feat-b .feat-banner-tag { background: rgba(251,191,36,0.2); color: var(--amber); border: 1px solid rgba(251,191,36,0.3); }
    .feat-b .feat-banner-btn { background: var(--amber); color: #030712; }

    /* ============================================================
       CATEGORY PILLS
    ============================================================ */
    .cat-row {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        scrollbar-width: none;
        padding-bottom: 2px;
        margin-bottom: 32px;
    }
    .cat-row::-webkit-scrollbar { display: none; }
    .cat-pill {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: var(--ink3);
        border: 1px solid var(--line);
        border-radius: var(--r-pill);
        font-size: 0.82rem;
        font-weight: 500;
        color: var(--txt2);
        text-decoration: none;
        white-space: nowrap;
        transition: all .18s;
        flex-shrink: 0;
    }
    .cat-pill:hover { background: var(--teal-glow); border-color: rgba(45,212,191,0.25); color: var(--teal); }
    .cat-pill.active { background: var(--teal-glow); border-color: rgba(45,212,191,0.4); color: var(--teal); font-weight: 600; }
    .cat-pill .pill-icon { font-size: 0.95rem; }
    .cat-pill .pill-count { font-size: 0.72rem; background: rgba(255,255,255,0.07); padding: 1px 6px; border-radius: var(--r-pill); color: var(--txt3); }

    /* ============================================================
       PRODUCT GRID
    ============================================================ */
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
        gap: 20px;
        margin-bottom: 48px;
    }

    .prod-card {
        background: var(--ink2);
        border: 1px solid var(--line);
        border-radius: var(--r);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        text-decoration: none;
        position: relative;
        transition: all .25s cubic-bezier(.34,1.56,.64,1);
    }
    .prod-card:hover {
        transform: translateY(-6px);
        border-color: rgba(45,212,191,0.3);
        box-shadow: 0 20px 48px rgba(0,0,0,0.5), 0 0 0 1px rgba(45,212,191,0.1);
    }

    /* Image gallery inside card */
    .prod-gallery {
        position: relative;
        aspect-ratio: 1 / 1;
        overflow: hidden;
        background: radial-gradient(circle at 30% 30%, #1a1f30, #050810);
    }
    .prod-gallery-track {
        display: flex;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scrollbar-width: none;
        height: 100%;
        width: 100%;
    }
    .prod-gallery-track::-webkit-scrollbar { display: none; }
    .prod-gallery-img {
        flex: 0 0 100%;
        width: 100%;
        height: 100%;
        object-fit: contain;
        scroll-snap-align: start;
        pointer-events: none;
    }
    .prod-gallery-placeholder {
        width: 100%; height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3.5rem;
        background: linear-gradient(135deg, var(--ink3), var(--ink2));
    }

    /* Floating badges on card image */
    .prod-float-top {
        position: absolute;
        top: 11px; left: 11px; right: 11px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        pointer-events: none;
        z-index: 10;
    }
    .prod-cat-badge {
        background: rgba(3,7,18,0.78);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(45,212,191,0.3);
        color: var(--teal);
        font-size: 0.68rem;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: var(--r-pill);
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }
    .prod-type-badge {
        background: rgba(3,7,18,0.78);
        backdrop-filter: blur(8px);
        border: 1px solid var(--line2);
        color: var(--txt2);
        font-size: 0.68rem;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: var(--r-pill);
    }
    .prod-swipe-hint {
        position: absolute;
        bottom: 10px; left: 50%;
        transform: translateX(-50%);
        background: rgba(3,7,18,0.65);
        backdrop-filter: blur(6px);
        color: var(--txt3);
        font-size: 0.68rem;
        padding: 3px 10px;
        border-radius: var(--r-pill);
        border: 1px solid var(--line);
        pointer-events: none;
        opacity: 0;
        transition: opacity .2s;
        white-space: nowrap;
    }
    .prod-card:hover .prod-swipe-hint { opacity: 1; }

    /* Card body */
    .prod-body {
        padding: 16px 16px 14px;
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    .prod-title {
        font-family: var(--font-head);
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--txt);
        line-height: 1.35;
        margin-bottom: 10px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        transition: color .18s;
    }
    .prod-card:hover .prod-title { color: #fff; }
    .prod-price {
        font-family: var(--font-head);
        font-size: 1.2rem;
        font-weight: 800;
        color: var(--amber);
        margin-bottom: 12px;
        letter-spacing: -0.5px;
    }
    .prod-price-sub { font-size: 0.72rem; color: var(--txt3); font-weight: 400; font-family: var(--font-body); }

    .prod-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 12px;
        border-top: 1px solid var(--line);
        margin-top: auto;
        gap: 8px;
    }
    .prod-uni {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.78rem;
        color: var(--txt3);
        min-width: 0;
    }
    .prod-uni-dot {
        width: 6px; height: 6px;
        border-radius: 50%;
        background: var(--teal);
        flex-shrink: 0;
    }
    .prod-uni-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .prod-view-btn {
        padding: 6px 14px;
        background: var(--ink3);
        border: 1px solid var(--line2);
        color: var(--txt2);
        border-radius: var(--r-sm);
        font-size: 0.78rem;
        font-weight: 600;
        transition: all .18s;
        flex-shrink: 0;
    }
    .prod-card:hover .prod-view-btn {
        background: var(--teal);
        border-color: var(--teal);
        color: #030712;
    }

    /* ============================================================
       EMPTY STATE
    ============================================================ */
    .empty-state {
        text-align: center;
        padding: 80px 24px;
        grid-column: 1 / -1;
    }
    .empty-icon { font-size: 3rem; margin-bottom: 16px; opacity: 0.6; }
    .empty-state h2 { font-family: var(--font-head); font-size: 1.3rem; color: #fff; margin-bottom: 8px; }
    .empty-state p { color: var(--txt3); font-size: 0.9rem; margin-bottom: 20px; }
    .empty-link {
        display: inline-block;
        color: var(--teal);
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        border: 1px solid rgba(45,212,191,0.3);
        padding: 10px 22px;
        border-radius: var(--r-pill);
        transition: all .18s;
    }
    .empty-link:hover { background: var(--teal-glow); }

    /* ============================================================
       SELLER CTA BANNER
    ============================================================ */
    .seller-cta {
        background: var(--ink2);
        border: 1px solid var(--line);
        border-radius: var(--r);
        padding: 40px;
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
        gap: 32px;
        margin-bottom: 48px;
        position: relative;
        overflow: hidden;
    }
    .seller-cta::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 80% 50%, var(--teal-glow), transparent 60%);
        pointer-events: none;
    }
    .seller-cta-left { position: relative; z-index: 1; }
    .seller-cta-eyebrow { font-size: 0.75rem; font-weight: 700; color: var(--teal); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 8px; }
    .seller-cta h2 { font-family: var(--font-head); font-size: 1.6rem; font-weight: 800; color: #fff; line-height: 1.25; margin-bottom: 8px; letter-spacing: -0.5px; }
    .seller-cta p { color: var(--txt2); font-size: 0.9rem; line-height: 1.7; max-width: 480px; }
    .seller-cta-actions { display: flex; gap: 10px; flex-shrink: 0; flex-direction: column; align-items: flex-end; }
    .cta-btn-primary {
        background: var(--teal);
        color: #030712;
        border: none;
        padding: 13px 28px;
        border-radius: var(--r-pill);
        font-family: var(--font-body);
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        transition: all .18s;
        white-space: nowrap;
    }
    .cta-btn-primary:hover { background: #fff; transform: translateY(-2px); }
    .cta-btn-ghost {
        background: transparent;
        color: var(--txt2);
        border: 1px solid var(--line2);
        padding: 11px 24px;
        border-radius: var(--r-pill);
        font-family: var(--font-body);
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        transition: all .18s;
        white-space: nowrap;
    }
    .cta-btn-ghost:hover { color: var(--txt); border-color: var(--txt3); }

    /* ============================================================
       HOW IT WORKS
    ============================================================ */
    .how-section {
        background: var(--ink2);
        border: 1px solid var(--line);
        border-radius: var(--r);
        padding: 40px;
        margin-bottom: 48px;
    }
    .how-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        margin-top: 24px;
        position: relative;
    }
    .how-step {
        padding: 24px;
        border-radius: var(--r-sm);
        background: var(--ink3);
        border: 1px solid var(--line);
        position: relative;
        transition: all .2s;
    }
    .how-step:hover { border-color: rgba(45,212,191,0.25); }
    .how-step-num {
        display: inline-block;
        font-family: var(--font-head);
        font-size: 0.72rem;
        font-weight: 800;
        color: var(--teal);
        background: var(--teal-glow);
        border: 1px solid rgba(45,212,191,0.2);
        padding: 3px 9px;
        border-radius: var(--r-pill);
        margin-bottom: 14px;
    }
    .how-step-icon {
        width: 44px; height: 44px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 12px;
    }
    .how-step-icon svg { width: 22px; height: 22px; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .how-step h4 { font-family: var(--font-head); font-size: 0.95rem; font-weight: 700; color: #fff; margin-bottom: 6px; }
    .how-step p { font-size: 0.82rem; color: var(--txt2); line-height: 1.65; }

    /* ============================================================
       STUDENT REVIEWS
    ============================================================ */
    .reviews-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 14px;
        margin-bottom: 48px;
    }
    .review-card {
        background: var(--ink2);
        border: 1px solid var(--line);
        border-radius: var(--r);
        padding: 20px;
        transition: all .2s;
    }
    .review-card:hover { border-color: rgba(45,212,191,0.2); transform: translateY(-2px); }
    .review-stars { color: var(--amber); font-size: 0.85rem; letter-spacing: 2px; margin-bottom: 10px; }
    .review-text { font-size: 0.875rem; color: var(--txt2); line-height: 1.7; margin-bottom: 14px; font-style: italic; }
    .review-text::before { content: '"'; color: var(--teal); font-size: 1.2rem; font-style: normal; }
    .review-text::after { content: '"'; color: var(--teal); font-size: 1.2rem; font-style: normal; }
    .reviewer { display: flex; align-items: center; gap: 10px; }
    .reviewer-av {
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        color: #030712;
        font-size: 0.75rem;
        font-weight: 800;
        font-family: var(--font-head);
        flex-shrink: 0;
    }
    .reviewer-name { font-size: 0.82rem; font-weight: 600; color: var(--txt); }
    .reviewer-school { font-size: 0.72rem; color: var(--txt3); }

    /* ============================================================
       APP DOWNLOAD SECTION
    ============================================================ */
    .app-section {
        background: var(--ink2);
        border: 1px solid var(--line);
        border-radius: var(--r);
        padding: 40px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 32px;
        margin-bottom: 48px;
        overflow: hidden;
        position: relative;
    }
    .app-section::before {
        content: '';
        position: absolute;
        width: 300px; height: 300px;
        border-radius: 50%;
        background: radial-gradient(circle, var(--teal-glow), transparent);
        right: -80px; top: -80px;
        pointer-events: none;
    }
    .app-text { position: relative; z-index: 1; }
    .app-text h3 { font-family: var(--font-head); font-size: 1.4rem; font-weight: 800; color: #fff; margin-bottom: 8px; }
    .app-text p { font-size: 0.875rem; color: var(--txt2); line-height: 1.7; max-width: 380px; margin-bottom: 20px; }
    .app-badges { display: flex; gap: 10px; flex-wrap: wrap; }
    .app-store-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        background: var(--ink3);
        border: 1px solid var(--line2);
        border-radius: var(--r-sm);
        padding: 11px 18px;
        cursor: pointer;
        transition: all .18s;
        text-decoration: none;
    }
    .app-store-btn:hover { background: var(--ink); border-color: var(--teal); }
    .app-store-icon { font-size: 1.4rem; }
    .app-store-sub { font-size: 0.68rem; color: var(--txt3); display: block; line-height: 1; margin-bottom: 2px; }
    .app-store-name { font-size: 0.9rem; font-weight: 700; color: var(--txt); display: block; }
    .app-phones { font-size: 5rem; line-height: 1; position: relative; z-index: 1; }

    /* ============================================================
       FOOTER
    ============================================================ */
    footer {
        background: var(--ink2);
        border-top: 1px solid var(--line);
        padding: 48px 40px 24px;
    }
    .footer-inner {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 40px;
        margin-bottom: 36px;
    }
    .footer-brand-name {
        font-family: var(--font-head);
        font-size: 1.3rem;
        font-weight: 800;
        color: var(--teal);
        letter-spacing: -0.5px;
        margin-bottom: 8px;
    }
    .footer-desc { font-size: 0.82rem; color: var(--txt3); line-height: 1.75; max-width: 240px; margin-bottom: 18px; }
    .footer-socials { display: flex; gap: 8px; }
    .soc-btn {
        width: 32px; height: 32px;
        border-radius: 50%;
        background: var(--ink3);
        border: 1px solid var(--line2);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        font-size: 0.8rem;
        color: var(--txt3);
        text-decoration: none;
        transition: all .18s;
    }
    .soc-btn:hover { background: var(--teal-glow); border-color: rgba(45,212,191,0.4); color: var(--teal); }
    .footer-col h4 { font-family: var(--font-head); font-size: 0.82rem; font-weight: 700; color: var(--txt); margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
    .footer-col ul { list-style: none; }
    .footer-col ul li { margin-bottom: 9px; }
    .footer-col ul li a { font-size: 0.82rem; color: var(--txt3); text-decoration: none; transition: color .15s; }
    .footer-col ul li a:hover { color: var(--teal); }
    .footer-bottom {
        max-width: 1200px;
        margin: 0 auto;
        padding-top: 20px;
        border-top: 1px solid var(--line);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .footer-copy { font-size: 0.78rem; color: var(--txt3); }
    .footer-bottom-links { display: flex; gap: 16px; }
    .footer-bottom-links a { font-size: 0.78rem; color: var(--txt3); text-decoration: none; }
    .footer-bottom-links a:hover { color: var(--teal); }

    /* Newsletter inside footer */
    .footer-newsletter {
        background: var(--ink3);
        border: 1px solid var(--line);
        border-radius: var(--r-sm);
        padding: 16px;
        margin-top: 16px;
    }
    .footer-newsletter p { font-size: 0.78rem; color: var(--txt3); margin-bottom: 10px; }
    .newsletter-row { display: flex; gap: 6px; }
    .newsletter-row input {
        flex: 1;
        background: var(--ink2);
        border: 1px solid var(--line2);
        border-radius: var(--r-sm);
        padding: 8px 12px;
        color: var(--txt);
        font-size: 0.8rem;
        font-family: var(--font-body);
        outline: none;
        transition: border-color .18s;
    }
    .newsletter-row input:focus { border-color: var(--teal); }
    .newsletter-row input::placeholder { color: var(--txt3); }
    .newsletter-row button {
        background: var(--teal);
        color: #030712;
        border: none;
        padding: 8px 14px;
        border-radius: var(--r-sm);
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
        font-family: var(--font-body);
        white-space: nowrap;
    }

    /* ============================================================
       RESPONSIVE
    ============================================================ */
    @media (max-width: 900px) {
        .nav-bar { padding: 0 20px; }
        .nav-search-wrap { display: none; }
        .nav-links-desktop { display: none; }
        .hero-split { grid-template-columns: 1fr; }
        .hero-right { display: none; }
        .hero-left { padding: 48px 24px; }
        .hero-h1 { font-size: 2.4rem; }
        .hero-stats-row { grid-template-columns: 1fr 1fr; }
        .hero-stat { border-right: none; border-bottom: 1px solid var(--line); }
        .hero-stat:nth-child(odd) { border-right: 1px solid var(--line); }
        .proof-strip { padding: 32px 20px; }
        .proof-strip-inner { grid-template-columns: 1fr 1fr; }
        .campus-life { padding: 32px 20px; }
        .campus-life-grid { grid-template-columns: 1fr 1fr; grid-template-rows: repeat(3, 160px); }
        .campus-photo.big { grid-row: auto; }
        .featured-banners { grid-template-columns: 1fr; }
        .seller-cta { grid-template-columns: 1fr; }
        .seller-cta-actions { flex-direction: row; align-items: flex-start; }
        .how-grid { grid-template-columns: 1fr; }
        .footer-inner { grid-template-columns: 1fr 1fr; }
        .trust-bar { padding: 0 20px; }
        .page-wrap { padding: 28px 16px 60px; }
    }
    @media (max-width: 600px) {
        .hero-h1 { font-size: 2rem; }
        .hero-left { padding: 36px 20px; }
        .hero-stats-row { grid-template-columns: 1fr 1fr; }
        .proof-strip-inner { grid-template-columns: 1fr; }
        .footer-inner { grid-template-columns: 1fr; }
        .footer-socials { flex-wrap: wrap; }
        .app-section { flex-direction: column; }
        .products-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
        .campus-life-grid { grid-template-columns: 1fr; grid-template-rows: repeat(5, 160px); }
        .featured-banners { gap: 12px; }
        .seller-cta { padding: 24px; }
        .seller-cta-actions { flex-direction: column; width: 100%; }
        .cta-btn-primary, .cta-btn-ghost { width: 100%; text-align: center; }
        .how-section { padding: 24px; }
        .reviews-grid { grid-template-columns: 1fr; }
    }

    /* Reduced motion */
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
    }
    </style>
</head>
<body>

<!-- ============================================================
     ANNOUNCEMENT BAR
============================================================ -->
<div class="announce-bar">
    <span class="announce-pulse"></span>
    🎉 New — Post your semester notes and earn cash.
    <a href="post_item.php">List for free →</a>
</div>

<!-- ============================================================
     NAVIGATION
============================================================ -->
<nav class="nav-bar">
    <a href="index.php" class="nav-brand">
        <div class="nav-logo-icon">
            <svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        </div>
        <span class="nav-brand-name">MIL<span>ELE</span></span>
    </a>

    <!-- Inline nav search (visible desktop) -->
    <div class="nav-search-wrap">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" class="nav-search-input" placeholder="Search textbooks, laptops, notes..."
            value="<?php echo htmlspecialchars($search ?? ''); ?>"
            onkeydown="if(event.key==='Enter'){ window.location='index.php?search='+encodeURIComponent(this.value)<?php echo $category ? "+'&category='.encodeURIComponent('".htmlspecialchars($category)."')" : ''; ?>; }">
    </div>

    <div class="nav-actions">
        <a href="post_item.php" class="nav-btn nav-btn-primary">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Sell Something
        </a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="inbox.php" class="nav-btn" style="position:relative;">
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                Inbox
                <?php if ($unread_count > 0): ?>
                    <span class="notif-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php" class="nav-btn">
                <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Profile
            </a>
        <?php else: ?>
            <a href="login.php" class="nav-btn">Log in</a>
            <a href="register.php" class="nav-btn" style="border-color:rgba(45,212,191,0.3);color:var(--teal);">Sign up free</a>
        <?php endif; ?>
    </div>
</nav>

<!-- ============================================================
     TOAST — UNREAD MESSAGES
============================================================ -->
<?php if ($unread_count > 0): ?>
<div class="toast-alert" id="msgToast">
    <div class="toast-icon">💬</div>
    <div class="toast-body">
        <div class="toast-title">You have <?php echo $unread_count; ?> new message<?php echo $unread_count > 1 ? 's' : ''; ?></div>
        <div class="toast-sub">A fellow student is waiting to hear back from you</div>
    </div>
    <a href="inbox.php" class="toast-link">Open →</a>
</div>
<script>
    setTimeout(() => document.getElementById('msgToast').classList.add('show'), 1200);
    setTimeout(() => document.getElementById('msgToast').classList.remove('show'), 7000);
</script>
<?php endif; ?>

<!-- ============================================================
     HERO
============================================================ -->
<section class="hero">
    <div class="hero-split">

        <!-- LEFT: copy + search -->
        <div class="hero-left">
            <div class="hero-eyebrow">
                <span class="hero-live-dot"></span>
                3,240 listings live right now
            </div>

            <h1 class="hero-h1">
                The market<br>
                <em>built for<br>campus life.</em>
            </h1>

            <p class="hero-sub">
                Buy, sell and swap with <strong>verified students at your university</strong> — textbooks, laptops, notes, rooms, and everything in between. No strangers. No scams.
            </p>

            <!-- Hero search -->
            <form action="index.php" method="GET" class="hero-search-form">
                <?php if ($category): ?><input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>"><?php endif; ?>
                <svg viewBox="0 0 24 24" style="stroke:var(--txt3);fill:none;stroke-width:2;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" name="search" class="hero-search-input" placeholder="Search for textbooks, electronics, rooms..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                <button type="submit" class="hero-search-btn">Search</button>
            </form>

            <!-- Quick links -->
            <div class="hero-quick">
                <div class="hero-quick-label">Popular right now →</div>
                <a href="?category=Textbooks" class="quick-chip">📚 Textbooks</a>
                <a href="?category=Electronics" class="quick-chip">💻 Electronics</a>
                <a href="?category=Fashion" class="quick-chip">👕 Fashion</a>
                <a href="?category=Services" class="quick-chip">🎓 Tutoring</a>
                <a href="?category=Dorm+Essentials" class="quick-chip">🏠 Dorm Essentials</a>
            </div>
        </div>

        <!-- RIGHT: campus photo collage -->
        <div class="hero-right">
            <div class="hero-collage">
                <img class="hero-collage-img" src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=500&h=300&fit=crop" alt="Students studying together" onerror="this.parentElement.style.background='#1a1f30'">
                <img class="hero-collage-img" src="https://images.unsplash.com/photo-1513258496099-48168024aec0?w=500&h=300&fit=crop" alt="Student with laptop" onerror="this.parentElement.style.background='#151a28'">
                <img class="hero-collage-img" src="https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=500&h=300&fit=crop" alt="University campus" onerror="this.parentElement.style.background='#0d1220'">
                <img class="hero-collage-img" src="https://images.unsplash.com/photo-1497633762265-9d179a990aa6?w=500&h=300&fit=crop" alt="Student marketplace books" onerror="this.parentElement.style.background='#1a1f30'">
            </div>
            <div class="hero-collage-overlay"></div>

            <!-- Floating stat cards -->
            <div class="hero-float-card card-a">
                <div class="hero-float-icon">💰</div>
                <div>
                    <div class="hero-float-num">KES 2M+</div>
                    <div class="hero-float-label">traded this month</div>
                </div>
            </div>
            <div class="hero-float-card card-b">
                <div class="hero-float-icon">🎓</div>
                <div>
                    <div class="hero-float-num">14,000+</div>
                    <div class="hero-float-label">verified students</div>
                </div>
            </div>
        </div>

    </div>

    <!-- Stats row -->
    <div class="hero-stats-row">
        <div class="hero-stat">
            <div class="stat-num">14<span>K+</span></div>
            <div class="stat-label">Students on MILELE</div>
        </div>
        <div class="hero-stat">
            <div class="stat-num">3.2<span>K</span></div>
            <div class="stat-label">Active listings</div>
        </div>
        <div class="hero-stat">
            <div class="stat-num"><span>KES</span> 2M+</div>
            <div class="stat-label">Traded this month</div>
        </div>
        <div class="hero-stat">
            <div class="stat-num">98<span>%</span></div>
            <div class="stat-label">Buyers happy</div>
        </div>
    </div>
</section>

<!-- ============================================================
     TRUST BAR
============================================================ -->
<div class="trust-bar">
    <div class="trust-item">
        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Students only — verified campus emails
    </div>
    <div class="trust-item">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Meet in person on campus — stay safe
    </div>
    <div class="trust-item">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Free to list — no commission fees
    </div>
    <div class="trust-item">
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        Direct chat with seller — no middleman
    </div>
    <div class="trust-item">
        <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        Rated sellers — community reviews
    </div>
</div>

<!-- ============================================================
     SOCIAL PROOF — WHAT PEOPLE ARE SELLING
============================================================ -->
<div class="proof-strip">
    <div class="proof-strip-inner">
        <div class="proof-card">
            <img src="https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=600&h=280&fit=crop" alt="Textbooks" onerror="this.style.display='none'">
            <div class="proof-badge">📚 Textbooks</div>
            <div class="proof-card-body">
                <div class="proof-cat">Most bought</div>
                <div class="proof-name">Calculus, Chemistry, Law — all units</div>
                <div class="proof-detail">Save up to 70% vs the campus bookshop</div>
                <div class="proof-price">From KES 150</div>
            </div>
        </div>
        <div class="proof-card">
            <img src="https://images.unsplash.com/photo-1593642632559-0c6d3fc62b89?w=600&h=280&fit=crop" alt="Laptops" onerror="this.style.display='none'">
            <div class="proof-badge">💻 Electronics</div>
            <div class="proof-card-body">
                <div class="proof-cat">Trending now</div>
                <div class="proof-name">Laptops, phones, calculators</div>
                <div class="proof-detail">Student-tested gear at fair prices</div>
                <div class="proof-price">From KES 5,000</div>
            </div>
        </div>
        <div class="proof-card">
            <img src="https://images.unsplash.com/photo-1501504905252-473c47e087f8?w=600&h=280&fit=crop" alt="Notes" onerror="this.style.display='none'">
            <div class="proof-badge">📝 Notes</div>
            <div class="proof-card-body">
                <div class="proof-cat">Fastest growing</div>
                <div class="proof-name">Lecture notes, past papers, summaries</div>
                <div class="proof-detail">Written by students who passed</div>
                <div class="proof-price">From KES 50</div>
            </div>
        </div>
        <div class="proof-card">
            <img src="https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=600&h=280&fit=crop" alt="Rooms" onerror="this.style.display='none'">
            <div class="proof-badge">🏠 Housing</div>
            <div class="proof-card-body">
                <div class="proof-cat">Near campus</div>
                <div class="proof-name">Bedsitters, singles, shared rooms</div>
                <div class="proof-detail">Trusted landlords recommended by students</div>
                <div class="proof-price">From KES 5,000/mo</div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     CAMPUS LIFE PHOTO GRID
============================================================ -->
<div class="campus-life">
    <div class="campus-life-inner">
        <div class="sec-head">
            <div class="sec-head-left">
                <div class="sec-title">Built around your campus life</div>
                <div class="sec-sub">Everything you need — from the first lecture to the last exam</div>
            </div>
        </div>
        <div class="campus-life-grid">
            <div class="campus-photo big">
                <img src="https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=800&h=500&fit=crop" alt="University students on campus" onerror="this.style.background='#1a1f30'">
                <div class="campus-photo-label">🎓 Campus life</div>
            </div>
            <div class="campus-photo">
                <img src="https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=400&h=200&fit=crop" alt="Student studying" onerror="this.style.background='#151a28'">
                <div class="campus-photo-label">📖 Study smarter</div>
            </div>
            <div class="campus-photo">
                <img src="https://images.unsplash.com/photo-1517486808906-6ca8b3f04846?w=400&h=200&fit=crop" alt="Students together" onerror="this.style.background='#0e1520'">
                <div class="campus-photo-label">👥 Your community</div>
            </div>
            <div class="campus-photo">
                <img src="https://images.unsplash.com/photo-1544717305-2782549b5136?w=400&h=200&fit=crop" alt="Books and backpack" onerror="this.style.background='#131928'">
                <div class="campus-photo-label">🎒 Gear up</div>
            </div>
            <div class="campus-photo">
                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&h=200&fit=crop" alt="Student seller" onerror="this.style.background='#1a1f30'">
                <div class="campus-photo-label">💸 Earn on the side</div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     MAIN CONTENT
============================================================ -->
<div class="page-wrap">

    <!-- FEATURED BANNERS -->
    <div class="featured-banners" style="margin-bottom:40px">
        <a href="?category=Textbooks" class="feat-banner feat-a">
            <img src="https://images.unsplash.com/photo-1532012197267-da84d127e765?w=700&h=260&fit=crop" alt="Textbooks">
            <div class="feat-banner-overlay"></div>
            <div class="feat-banner-content">
                <div class="feat-banner-tag">📚 Semester clearance</div>
                <h3>Textbooks at<br>student prices</h3>
                <p>Up to 70% cheaper than the campus bookshop.</p>
                <button class="feat-banner-btn">Browse textbooks →</button>
            </div>
        </a>
        <a href="?category=Electronics" class="feat-banner feat-b">
            <img src="https://images.unsplash.com/photo-1611532736597-de2d4265fba3?w=700&h=260&fit=crop" alt="Electronics">
            <div class="feat-banner-overlay"></div>
            <div class="feat-banner-content">
                <div class="feat-banner-tag">⚡ Tech deals</div>
                <h3>Laptops, phones<br>& gadgets — cheap</h3>
                <p>From students who upgraded. Your gain.</p>
                <button class="feat-banner-btn">Browse electronics →</button>
            </div>
        </a>
    </div>

    <!-- CATEGORY PILLS -->
    <div class="cat-row">
        <?php
        $cats = [
            '' => ['label' => 'All Items', 'icon' => '🏪', 'count' => '3.2K'],
            'Electronics' => ['label' => 'Electronics', 'icon' => '💻', 'count' => '614'],
            'Textbooks' => ['label' => 'Textbooks', 'icon' => '📚', 'count' => '842'],
            'Fashion' => ['label' => 'Fashion', 'icon' => '👕', 'count' => '389'],
            'Dorm Essentials' => ['label' => 'Dorm Essentials', 'icon' => '🏠', 'count' => '208'],
            'Services' => ['label' => 'Services', 'icon' => '🎓', 'count' => '156'],
            'Other' => ['label' => 'Other', 'icon' => '📦', 'count' => '120'],
        ];
        foreach ($cats as $key => $meta):
            $isActive = ($category === $key) || (empty($category) && $key === '');
            $link = 'index.php?category=' . urlencode($key);
            if ($search) $link .= '&search=' . urlencode($search);
        ?>
        <a href="<?php echo $link; ?>" class="cat-pill <?php echo $isActive ? 'active' : ''; ?>">
            <span class="pill-icon"><?php echo $meta['icon']; ?></span>
            <?php echo htmlspecialchars($meta['label']); ?>
            <span class="pill-count"><?php echo $meta['count']; ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- SECTION HEADER FOR LISTINGS -->
    <div class="sec-head">
        <div class="sec-head-left">
            <div class="sec-title">
                <?php if (!empty($search)): ?>
                    Results for "<?php echo htmlspecialchars($search); ?>"
                <?php elseif (!empty($category)): ?>
                    <?php echo htmlspecialchars($category); ?>
                <?php else: ?>
                    🆕 Fresh listings
                <?php endif; ?>
            </div>
            <div class="sec-sub">
                <?php echo count($items); ?> listing<?php echo count($items) !== 1 ? 's' : ''; ?> found
                <?php if (!empty($category)): ?> in <?php echo htmlspecialchars($category); ?><?php endif; ?>
            </div>
        </div>
        <?php if (!empty($search) || !empty($category)): ?>
        <a href="index.php" class="sec-link">
            Clear filters
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </a>
        <?php endif; ?>
    </div>

    <!-- PRODUCTS GRID -->
    <div class="products-grid">
        <?php if (empty($items)): ?>
            <div class="empty-state">
                <div class="empty-icon">🔍</div>
                <h2>Nothing found</h2>
                <p>We couldn't find any listings matching your search. Try different keywords or browse all categories.</p>
                <a href="index.php" class="empty-link">Browse all listings</a>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item):
                $images = [];
                $decoded = json_decode($item['image_path'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && count($decoded) > 0) {
                    $images = $decoded;
                } else {
                    $images[] = !empty($item['image_path']) ? $item['image_path'] : '';
                }
                $uni_short = explode(' ', $item['university_name'])[0];
            ?>
            <a href="item.php?id=<?php echo $item['listing_id']; ?>" class="prod-card">

                <!-- Image gallery -->
                <div class="prod-gallery">
                    <div class="prod-gallery-track">
                        <?php if (!empty($images) && !empty($images[0])): ?>
                            <?php foreach ($images as $img): ?>
                                <img class="prod-gallery-img" src="<?php echo htmlspecialchars($img); ?>" loading="lazy" alt="<?php echo htmlspecialchars($item['title']); ?>">
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="prod-gallery-placeholder">
                                <?php
                                $cat_icons = ['Electronics'=>'💻','Textbooks'=>'📚','Fashion'=>'👕','Dorm Essentials'=>'🏠','Services'=>'🎓','Other'=>'📦'];
                                echo $cat_icons[$item['category']] ?? '📦';
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Floating badges -->
                    <div class="prod-float-top">
                        <span class="prod-cat-badge"><?php echo htmlspecialchars($item['category']); ?></span>
                        <?php if (isset($item['item_type'])): ?>
                            <span class="prod-type-badge"><?php echo $item['item_type'] == 'Digital' ? '📄 Digital' : '📦 Physical'; ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (count($images) > 1): ?>
                        <div class="prod-swipe-hint">↔ Swipe for more photos</div>
                    <?php endif; ?>
                </div>

                <!-- Card body -->
                <div class="prod-body">
                    <div class="prod-title"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div class="prod-price">
                        KES <?php echo number_format($item['price'], 0); ?>
                        <span class="prod-price-sub">negotiable</span>
                    </div>
                    <div class="prod-footer">
                        <div class="prod-uni">
                            <div class="prod-uni-dot"></div>
                            <span class="prod-uni-name"><?php echo htmlspecialchars($uni_short); ?></span>
                        </div>
                        <div class="prod-view-btn">View details</div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- HOW IT WORKS -->
    <div class="how-section">
        <div class="sec-head" style="margin-bottom:0">
            <div class="sec-head-left">
                <div class="sec-title">How MILELE works</div>
                <div class="sec-sub">Three simple steps — from listing to deal done</div>
            </div>
        </div>
        <div class="how-grid">
            <div class="how-step">
                <div class="how-step-num">Step 01</div>
                <div class="how-step-icon" style="background:rgba(45,212,191,0.1)">
                    <svg viewBox="0 0 24 24" style="stroke:var(--teal)"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
                <h4>Post your listing</h4>
                <p>Take a photo, write what it is, set your price in KES. Done. Your listing is live in under 60 seconds — visible to every verified student at your university.</p>
            </div>
            <div class="how-step">
                <div class="how-step-num">Step 02</div>
                <div class="how-step-icon" style="background:rgba(251,191,36,0.1)">
                    <svg viewBox="0 0 24 24" style="stroke:var(--amber)"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                </div>
                <h4>Chat with buyers</h4>
                <p>Buyers message you directly — no phone number needed. Ask questions, agree on a price, and pick a spot on campus to meet. No strangers, just students.</p>
            </div>
            <div class="how-step">
                <div class="how-step-num">Step 03</div>
                <div class="how-step-icon" style="background:rgba(34,197,94,0.1)">
                    <svg viewBox="0 0 24 24" style="stroke:var(--green)"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
                </div>
                <h4>Meet & exchange</h4>
                <p>Hand over the item, collect your cash. Rate each other after. Build your seller score and get the Trusted Seller badge that makes future buyers more confident.</p>
            </div>
        </div>
    </div>

    <!-- SELLER CTA -->
    <div class="seller-cta">
        <div class="seller-cta-left">
            <div class="seller-cta-eyebrow">💸 Earn this semester</div>
            <h2>Got something you don't use anymore?</h2>
            <p>Old textbooks, last semester's notes, a spare calculator, a jacket that doesn't fit — somebody on campus needs exactly what you're not using. Turn clutter into cash in under a minute.</p>
        </div>
        <div class="seller-cta-actions">
            <a href="post_item.php" class="cta-btn-primary">Post a free listing →</a>
            <a href="how-it-works.php" class="cta-btn-ghost">How it works</a>
        </div>
    </div>

    <!-- STUDENT REVIEWS -->
    <div class="sec-head">
        <div class="sec-head-left">
            <div class="sec-title">💬 Real students. Real deals.</div>
            <div class="sec-sub">Here's what the MILELE community is saying</div>
        </div>
    </div>
    <div class="reviews-grid">
        <?php
        $reviews = [
            ['stars'=>'★★★★★','text'=>'Sold my Calculus textbook in 3 hours. Posted at 9am, met the buyer at the library at noon.','name'=>'Amina M.','school'=>'UoN, Actuarial Science','color'=>'#2DD4BF'],
            ['stars'=>'★★★★★','text'=>'Got a laptop for KES 28,000 that retails at 70K. The seller was a final year student who just needed quick money.','name'=>'Brian K.','school'=>'KU, Engineering','color'=>'#fbbf24'],
            ['stars'=>'★★★★☆','text'=>'The BCOM notes I bought were honestly better than my lecturer\'s slides. Passed my CATs because of this.','name'=>'Cynthia O.','school'=>'Strathmore, Business Year 3','color'=>'#a78bfa'],
            ['stars'=>'★★★★★','text'=>'Found a stats tutor through MILELE services and passed my SPSS assignment that had me stuck for a week.','name'=>'David N.','school'=>'UoN, Public Health Masters','color'=>'#22c55e'],
            ['stars'=>'★★★★★','text'=>'I clear my old books and notes every semester and make back around KES 4,000. Pays my data bundles.','name'=>'Elsa W.','school'=>'JKUAT, Year 4 CompSci','color'=>'#f87171'],
            ['stars'=>'★★★★★','text'=>'Found a room 10 minutes from campus for KES 7,000 per month. Couldn\'t believe it. The landlord was very legitimate.','name'=>'George M.','school'=>'KU, Architecture Year 1','color'=>'#60a5fa'],
        ];
        foreach ($reviews as $r):
        ?>
        <div class="review-card">
            <div class="review-stars"><?php echo $r['stars']; ?></div>
            <div class="review-text"><?php echo htmlspecialchars($r['text']); ?></div>
            <div class="reviewer">
                <div class="reviewer-av" style="background:<?php echo $r['color']; ?>">
                    <?php echo strtoupper(substr($r['name'],0,1).substr(strrchr($r['name'],' '),1,1)); ?>
                </div>
                <div>
                    <div class="reviewer-name"><?php echo htmlspecialchars($r['name']); ?></div>
                    <div class="reviewer-school"><?php echo htmlspecialchars($r['school']); ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- APP DOWNLOAD -->
    <div class="app-section">
        <div class="app-text">
            <h3>Get the MILELE app</h3>
            <p>Get notified the moment a laptop, textbook, or room you're looking for gets listed. Browse on the go, message sellers instantly, and never miss a deal again.</p>
            <div class="app-badges">
                <a href="#" class="app-store-btn">
                    <span class="app-store-icon">🍎</span>
                    <div>
                        <span class="app-store-sub">Download on the</span>
                        <span class="app-store-name">App Store</span>
                    </div>
                </a>
                <a href="#" class="app-store-btn">
                    <span class="app-store-icon">▶</span>
                    <div>
                        <span class="app-store-sub">Get it on</span>
                        <span class="app-store-name">Google Play</span>
                    </div>
                </a>
            </div>
        </div>
        <div class="app-phones">📱</div>
    </div>

</div><!-- /page-wrap -->

<!-- ============================================================
     FOOTER
============================================================ -->
<footer>
    <div class="footer-inner">
        <div>
            <div class="footer-brand-name">MILELE</div>
            <p class="footer-desc">The student-only campus marketplace. Built for university students across East Africa — Nairobi, Mombasa, Kampala, Dar es Salaam, and beyond.</p>
            <div class="footer-socials">
                <a href="#" class="soc-btn" title="Twitter/X">𝕏</a>
                <a href="#" class="soc-btn" title="Instagram">📸</a>
                <a href="#" class="soc-btn" title="WhatsApp">💬</a>
                <a href="#" class="soc-btn" title="LinkedIn">in</a>
                <a href="#" class="soc-btn" title="TikTok">♪</a>
            </div>
            <div class="footer-newsletter">
                <p>Get deal alerts for your university. Drop your campus email:</p>
                <div class="newsletter-row">
                    <input type="email" placeholder="you@students.uon.ac.ke">
                    <button>Subscribe</button>
                </div>
            </div>
        </div>
        <div class="footer-col">
            <h4>Marketplace</h4>
            <ul>
                <li><a href="index.php">Browse all listings</a></li>
                <li><a href="post_item.php">Sell something</a></li>
                <li><a href="?category=Textbooks">Textbooks</a></li>
                <li><a href="?category=Electronics">Electronics</a></li>
                <li><a href="?category=Dorm+Essentials">Dorm Essentials</a></li>
                <li><a href="?category=Services">Tutoring Services</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Universities</h4>
            <ul>
                <li><a href="#">Univ. of Nairobi</a></li>
                <li><a href="#">Kenyatta University</a></li>
                <li><a href="#">Strathmore University</a></li>
                <li><a href="#">JKUAT</a></li>
                <li><a href="#">MMU</a></li>
                <li><a href="#">USIU-Africa</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Help & Safety</h4>
            <ul>
                <li><a href="#">Help Centre</a></li>
                <li><a href="#">Safety tips</a></li>
                <li><a href="#">Report a listing</a></li>
                <li><a href="#">Terms of service</a></li>
                <li><a href="#">Privacy policy</a></li>
                <li><a href="#">Contact us</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <span class="footer-copy">© 2026 MILELE Campus Marketplace. All rights reserved. Built with ❤️ across East Africa.</span>
        <div class="footer-bottom-links">
            <a href="#">Terms</a>
            <a href="#">Privacy</a>
            <a href="#">Cookies</a>
        </div>
    </div>
</footer>

<?php include 'footer.php'; ?>

</body>
</html>