<?php
// MILELE - Dynamic Feed V6 (Live Cloud Connected)

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if (isset($_SESSION['account_state']) && $_SESSION['account_state'] === 'registered') { header("Location: verification_center.php"); exit(); }

// ⚡ THE MASTER CONNECTION
require 'db.php';

try {
    $sql = "SELECT l.*, u.full_name, u.university_name
            FROM listings l
            JOIN users u ON l.seller_id = u.user_id
            WHERE l.listing_status = 'active'
            ORDER BY l.created_at DESC LIMIT 50";
    $live_listings = $pdo->query($sql)->fetchAll();
} catch (Exception $e) {
    $live_listings = [];
}

function getInitials($name) {
    $words = explode(" ", trim($name));
    $i = "";
    foreach ($words as $w) { if($w) $i .= strtoupper($w[0]); }
    return substr($i, 0, 2);
}

$user_name = $_SESSION['full_name'] ?? 'Student';
$user_initials = getInitials($user_name);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
<meta name="theme-color" content="#0A0A0C"/>
<title>MILELE — Campus Feed</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0A0A0C;
  --surface:rgba(255,255,255,0.025);
  --surface-2:rgba(255,255,255,0.04);
  --border:rgba(255,255,255,0.07);
  --border-hover:rgba(255,255,255,0.16);
  --text:#F3F4F6;
  --text-2:#9CA3AF;
  --text-3:#4B5563;
  --teal:#2DD4BF;
  --blue:#60A5FA;
  --mono:'SF Mono','JetBrains Mono',monospace;
}
html{height:100%;-webkit-tap-highlight-color:transparent}
body{
  font-family:'Plus Jakarta Sans',sans-serif;
  background:var(--bg);color:var(--text);
  min-height:100vh;padding-bottom:90px;
  -webkit-font-smoothing:antialiased;
  overflow-x:hidden;
}

#prog{position:fixed;top:0;left:0;height:2px;width:0%;z-index:9999;background:linear-gradient(90deg,var(--teal),var(--blue));border-radius:0 2px 2px 0;transition:width .08s linear}

.glass-nav{
  position:fixed;top:0;width:100%;z-index:50;
  background:rgba(10,10,12,0.82);
  backdrop-filter:blur(28px);-webkit-backdrop-filter:blur(28px);
  border-bottom:1px solid var(--border);
}
.nav-inner{
  max-width:900px;margin:0 auto;
  padding:0 16px;height:60px;
  display:flex;align-items:center;justify-content:space-between;
}
.nav-brand{display:flex;align-items:center;gap:9px}
.nav-logo-ring{
  width:34px;height:34px;border-radius:10px;
  background:linear-gradient(135deg,rgba(255,255,255,0.1),rgba(255,255,255,0.04));
  border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;font-size:16px;
}
.nav-brand-text{font-size:13px;font-weight:800;letter-spacing:.22em;color:#fff;text-transform:uppercase}
.nav-right{display:flex;align-items:center;gap:8px}
.nav-btn{
  width:36px;height:36px;border-radius:50%;
  background:var(--surface);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  font-size:15px;cursor:pointer;
  transition:background .2s,border-color .2s;
  position:relative;
}
.nav-btn:hover{background:var(--surface-2);border-color:var(--border-hover)}
.nav-badge{
  position:absolute;top:5px;right:5px;
  width:7px;height:7px;border-radius:50%;
  background:#F87171;border:1.5px solid var(--bg);
}
.nav-avatar{
  width:34px;height:34px;border-radius:50%;
  background:linear-gradient(135deg,#1d4ed8,var(--teal));
  border:1.5px solid rgba(45,212,191,0.3);
  display:flex;align-items:center;justify-content:center;
  font-size:11px;font-weight:800;color:#fff;cursor:pointer;
  box-shadow:0 0 12px rgba(45,212,191,0.2);
  transition: transform 0.2s;
}
.nav-avatar:hover { transform: scale(1.05); }

.search-wrap{padding:10px 16px;max-width:900px;margin:0 auto;}
.search-bar{display:flex;align-items:center;gap:10px;background:var(--surface-2);border:1px solid var(--border);border-radius:14px;padding:10px 14px;}
.search-input{flex:1;background:none;border:none;outline:none;color:var(--text);font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;}
.stories-wrap{padding:0 16px 0;max-width:900px;margin:0 auto}
.stories-label{font-size:10px;font-weight:700;color:var(--text-3);letter-spacing:.14em;text-transform:uppercase;margin-bottom:12px}
.stories-scroll{display:flex;gap:14px;overflow-x:auto;padding-bottom:2px;scrollbar-width:none}
.stories-scroll::-webkit-scrollbar{display:none}
.story-item{display:flex;flex-direction:column;align-items:center;gap:6px;flex-shrink:0;cursor:pointer}
.story-ring-wrap{padding:2px;border-radius:50%;background:linear-gradient(45deg,var(--teal),var(--blue));transition:transform .2s;}
.story-ring-wrap.seen{background:var(--text-3)}
.story-img{width:58px;height:58px;border-radius:50%;border:2px solid var(--bg);object-fit:cover;background:var(--surface-2);display:block;}
.story-label{font-size:10px;font-weight:600;color:var(--text-2);max-width:64px;text-align:center;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.story-add-ring{width:62px;height:62px;border-radius:50%;border:1.5px dashed var(--text-3);display:flex;align-items:center;justify-content:center;font-size:22px;}

.filter-bar{position:sticky;top:60px;z-index:40;background:rgba(10,10,12,0.9);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);padding:10px 16px;}
.filter-scroll{display:flex;gap:8px;overflow-x:auto;scrollbar-width:none;max-width:900px;margin:0 auto}
.filter-scroll::-webkit-scrollbar{display:none}
.filter-chip{padding:7px 16px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap;border:1px solid var(--border);background:var(--surface);color:var(--text-2);cursor:pointer;}
.filter-chip.active{background:#fff;color:#0A0A0C;border-color:#fff;}

.feed-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:0 16px;max-width:900px;margin:0 auto;}
@media(min-width:600px){.feed-grid{grid-template-columns:repeat(3,1fr)}}
@media(min-width:900px){.feed-grid{grid-template-columns:repeat(4,1fr)}}
.item-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;overflow:hidden;position:relative;cursor:pointer;}
.card-img-wrap{aspect-ratio:4/5;position:relative;overflow:hidden;background:var(--surface-2);}
.card-img{width:100%;height:100%;object-fit:cover;}
.wish-btn{position:absolute;top:10px;right:10px;z-index:10;width:32px;height:32px;border-radius:50%;background:rgba(0,0,0,0.5);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;font-size:14px;}
.wish-btn.liked{background:rgba(244,63,94,0.2);border-color:rgba(244,63,94,0.3)}
.card-body{padding:12px 12px 14px}
.card-title{font-size:13px;font-weight:700;color:var(--text);line-height:1.35;margin-bottom:5px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.card-price{font-size:17px;font-weight:800;color:#fff;}
.card-type-badge{position:absolute;bottom:10px;left:10px;z-index:10;display:flex;align-items:center;gap:5px;background:rgba(0,0,0,0.65);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:4px 9px;}
.badge-dot{width:5px;height:5px;border-radius:50%;animation:dotpulse 2s ease-in-out infinite;flex-shrink:0}
.badge-dot.digital{background:var(--green)}
.badge-dot.escrow{background:var(--blue)}
@keyframes dotpulse{0%,100%{opacity:.5}50%{opacity:1}}
.badge-label{font-size:8px;font-weight:700;color:#fff;letter-spacing:.1em;text-transform:uppercase}

.bottom-nav{position:fixed;bottom:0;width:100%;z-index:50;background:rgba(10,10,12,0.9);backdrop-filter:blur(28px);border-top:1px solid var(--border);padding-bottom:env(safe-area-inset-bottom,0px);}
.bn-inner{max-width:500px;margin:0 auto;display:flex;align-items:center;justify-content:space-around;height:60px;padding:0 8px;}
.bn-item{display:flex;flex-direction:column;align-items:center;gap:3px;font-size:9px;font-weight:700;color:var(--text-3);text-transform:uppercase;padding:6px 10px;border-radius:12px;cursor:pointer;text-decoration:none;}
.bn-item.active{color:#fff}
.bn-icon{font-size:19px;}
.bn-fab-wrap{position:relative;top:-14px}
.bn-fab{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#ffffff,#d1d5db);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 0 20px rgba(255,255,255,0.2),0 4px 12px rgba(0,0,0,0.4);}

/* ── STORY VIEWER CSS ── */
#story-viewer { z-index: 99999; }
.story-progress-bg { background: rgba(255, 255, 255, 0.3); }
.story-progress-fill { background: #ffffff; width: 0%; transform-origin: left; }

#toast{
  position:fixed;bottom:100px;left:50%;
  transform:translateX(-50%) translateY(16px);
  background:rgba(30,30,36,0.95);
  backdrop-filter:blur(20px);
  border:1px solid var(--border-hover);
  color:var(--text);
  padding:11px 22px;border-radius:14px;
  font-size:12px;font-weight:600;
  opacity:0;pointer-events:none;
  transition:all .35s;
  z-index:9000;white-space:nowrap;
  box-shadow:0 8px 32px rgba(0,0,0,0.4);
}
#toast.show{transform:translateX(-50%) translateY(0);opacity:1}
</style>
</head>
<body>

<div id="prog"></div>

<div id="story-viewer" class="fixed inset-0 bg-[#0A0A0C] hidden flex-col transition-opacity duration-300 opacity-0 select-none">
    <div class="absolute top-0 w-full pt-4 z-50 px-3 flex gap-1" id="story-bars-container"></div>
    
    <div class="absolute top-6 w-full z-50 px-4 pt-2 flex justify-between items-center pointer-events-none">
        <div class="flex items-center gap-3">
            <div id="story-avatar" class="w-9 h-9 rounded-full border border-white/20 bg-cover bg-center"></div>
            <div>
                <span id="story-title" class="text-white text-sm font-bold tracking-wide drop-shadow-md block"></span>
                <span class="text-[10px] text-white/70 font-semibold uppercase tracking-wider">MILELE Highlight</span>
            </div>
        </div>
        <button onclick="closeStory()" class="w-10 h-10 rounded-full bg-black/40 backdrop-blur-md border border-white/10 flex items-center justify-center text-white hover:bg-white/10 transition pointer-events-auto">✕</button>
    </div>

    <div class="flex-1 relative flex items-center justify-center bg-black">
        <div class="absolute inset-0 bg-gradient-to-b from-black/80 via-transparent to-black/80 pointer-events-none z-10"></div>
        <img id="story-image" src="" class="w-full h-full object-cover sm:object-contain absolute inset-0">
        
        <div class="absolute left-0 top-16 bottom-0 w-1/3 z-40 cursor-pointer" onclick="prevStory()"></div>
        <div class="absolute right-0 top-16 bottom-0 w-2/3 z-40 cursor-pointer" onclick="nextStory()"></div>
    </div>
</div>

<nav class="glass-nav">
  <div class="nav-inner">
    <div class="nav-brand">
      <div class="nav-logo-ring">🛍️</div>
      <span class="nav-brand-text">MILELE</span>
    </div>
    <div class="nav-right">
      <div class="nav-btn" onclick="toggleSearch()">
        <span id="search-icon-nav">🔍</span>
      </div>
      <div class="nav-btn" onclick="window.location.href='notifications.php'">
        📣
        <div class="nav-badge"></div>
      </div>
      <div class="nav-avatar" title="View Profile" onclick="window.location.href='profile.php'">
        <?php echo $user_initials; ?>
      </div>
    </div>
  </div>
</nav>

<div id="search-panel" style="max-height:0;overflow:hidden;transition:max-height .35s;margin-top:60px">
  <div class="search-wrap" style="padding-top:10px">
    <div class="search-bar">
      <span class="search-icon">🔍</span>
      <input class="search-input" id="search-input" type="text" placeholder="Search..." autocomplete="off"/>
    </div>
  </div>
</div>

<main style="padding-top:60px" id="main">

  <div style="padding-top:16px">
    <div class="stories-wrap">
      <div class="stories-label">Campus Highlights</div>
      <div class="stories-scroll no-scrollbar">
        <div class="story-item" onclick="showToast('📸 Add your highlight')">
          <div class="story-add-ring">＋</div>
          <span class="story-label">Add Yours</span>
        </div>
        
        <?php
        $stories = [
          ['img'=>'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=800&q=80','label'=>'Clearance'],
          ['img'=>'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=800&q=80','label'=>'PDF Notes'],
          ['img'=>'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=800&q=80','label'=>'Graduates'],
          ['img'=>'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&q=80','label'=>'Fashion'],
          ['img'=>'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=800&q=80','label'=>'Rooms']
        ];
        foreach($stories as $index => $s): ?>
        <div class="story-item" onclick="openStory(<?php echo $index; ?>)">
          <div class="story-ring-wrap" id="ring-<?php echo $index; ?>">
            <img class="story-img" src="<?php echo $s['img'] ?>" alt="<?php echo $s['label'] ?>" loading="lazy"/>
          </div>
          <span class="story-label"><?php echo $s['label'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="filter-bar">
    <div class="filter-scroll no-scrollbar">
      <button class="filter-chip active" data-cat="all">All Items</button>
      <button class="filter-chip" data-cat="electronics">💻 Electronics</button>
      <button class="filter-chip" data-cat="books">📚 Textbooks</button>
      <button class="filter-chip" data-cat="digital">⚡ Digital</button>
    </div>
  </div>

  <div class="feed-grid" id="feed-grid">
    <?php if (empty($live_listings)): ?>
    <div class="col-span-full py-10 text-center text-gray-500">No items available.</div>
    <?php else:
      $colors = ['#1d4ed8','#0f766e','#15803d','#b45309'];
      foreach ($live_listings as $idx => $item):
        $img = $item['file_path'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&q=75';
        $is_mine = ($item['seller_id'] == $_SESSION['user_id']);
        $is_digital = ($item['item_type'] === 'digital');
    ?>
    <div class="item-card" data-cat="<?php echo htmlspecialchars($item['category'] ?? 'other'); ?>" onclick="window.location.href='item.php?id=<?php echo $item['listing_id'] ?>'">
      
      <button class="wish-btn" onclick="event.stopPropagation(); toggleHeart(this, <?php echo $item['listing_id']; ?>)">🤍</button>
      
      <?php if ($is_mine): ?>
          <div class="absolute top-10 left-2 z-10 font-bold text-[8px] bg-teal-500/20 text-teal-400 border border-teal-500/30 px-2 py-0.5 rounded uppercase tracking-widest backdrop-blur">Yours</div>
      <?php endif; ?>
      
      <div class="card-img-wrap">
          <img class="card-img" src="<?php echo htmlspecialchars($img) ?>" loading="lazy"/>
          <div class="card-type-badge">
            <div class="badge-dot <?php echo $is_digital ? 'digital' : 'escrow' ?>"></div>
            <span class="badge-label"><?php echo $is_digital ? 'Instant Access' : 'Escrow Protected' ?></span>
          </div>
      </div>
      
      <div class="card-body">
        <div class="card-title"><?php echo htmlspecialchars($item['title']) ?></div>
        <div class="card-price">KES <?php echo number_format($item['price']) ?></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

</main>

<div id="toast"><span id="toast-msg"></span></div>

<div class="bottom-nav">
  <div class="bn-inner">
    <a href="index.php" class="bn-item active"><span class="bn-icon">🏪</span><span>Feed</span></a>
    <a href="saved.php" class="bn-item"><span class="bn-icon">📚</span><span>Saved</span></a>
    <div class="bn-fab-wrap">
      <button class="bn-fab" onclick="window.location.href='Notesing.php'">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
      </button>
    </div>
    <a href="inbox.php" class="bn-item"><span class="bn-icon">💬</span><span>Chats</span></a>
    <a href="profile.php" class="bn-item"><span class="bn-icon">👤</span><span>Profile</span></a>
  </div>
</div>

<script>
// --- AJAX WISHLIST ENGINE ---
function toggleHeart(btn, listingId) {
  btn.classList.toggle('liked');
  const isLiked = btn.classList.contains('liked');
  
  if (isLiked) { btn.innerHTML = '❤️'; showToast('❤️ Saving to wishlist...'); } 
  else { btn.innerHTML = '🤍'; }

  fetch('toggle_save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'listing_id=' + listingId
  })
  .then(response => response.json())
  .then(data => {
      if (data.status === 'saved') { showToast('❤️ Saved to wishlist'); } 
      else if (data.status === 'removed') { showToast('💔 Removed from saved'); } 
      else {
          showToast('⚠️ Error');
          btn.classList.toggle('liked');
          btn.innerHTML = isLiked ? '🤍' : '❤️';
      }
  }).catch(() => showToast('⚠️ Network error'));
}

// --- INTERACTIVE STORY ENGINE ---
const storiesData = <?php echo json_encode($stories); ?>;
let currentStoryIndex = 0;
let storyTimeout;

function openStory(index) {
    if (index < 0 || index >= storiesData.length) {
        closeStory();
        return;
    }
    
    currentStoryIndex = index;
    const s = storiesData[index];
    const viewer = document.getElementById('story-viewer');
    const barsContainer = document.getElementById('story-bars-container');
    
    document.getElementById('ring-' + index).classList.add('seen');

    barsContainer.innerHTML = '';
    for(let i=0; i<storiesData.length; i++) {
        let bgClass = (i < index) ? 'bg-white' : 'story-progress-bg';
        let html = `<div class="h-[3px] ${bgClass} rounded-full flex-1 overflow-hidden relative">`;
        if (i === index) { html += `<div id="active-progress" class="h-full story-progress-fill absolute left-0 top-0"></div>`; }
        html += `</div>`;
        barsContainer.innerHTML += html;
    }

    document.getElementById('story-image').src = s.img;
    document.getElementById('story-title').textContent = s.label;
    document.getElementById('story-avatar').style.backgroundImage = `url('${s.img}')`;

    viewer.classList.remove('hidden');
    viewer.classList.add('flex');
    setTimeout(() => viewer.classList.remove('opacity-0'), 10);

    const activeBar = document.getElementById('active-progress');
    void activeBar.offsetWidth; 
    activeBar.style.transition = 'width 5s linear';
    activeBar.style.width = '100%';

    clearTimeout(storyTimeout);
    storyTimeout = setTimeout(() => { nextStory(); }, 5000);
}

function nextStory() { clearTimeout(storyTimeout); openStory(currentStoryIndex + 1); }
function prevStory() { clearTimeout(storyTimeout); openStory(currentStoryIndex - 1); }
function closeStory() {
    clearTimeout(storyTimeout);
    const viewer = document.getElementById('story-viewer');
    viewer.classList.add('opacity-0');
    setTimeout(() => { viewer.classList.add('hidden'); viewer.classList.remove('flex'); }, 300);
}

// --- SEARCH & FILTER LOGIC ---
let searchOpen = false;
function toggleSearch() {
  searchOpen = !searchOpen;
  const p = document.getElementById('search-panel');
  p.style.maxHeight = searchOpen ? '80px' : '0';
  if (searchOpen) setTimeout(() => document.getElementById('search-input').focus(), 200);
}

document.querySelectorAll('.filter-chip').forEach(chip => {
  chip.addEventListener('click', () => {
    document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    // Implement filter logic here based on data-cat
  });
});

let toastTimer;
function showToast(msg) {
  const t = document.getElementById('toast');
  document.getElementById('toast-msg').textContent = msg;
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 2400);
}

window.addEventListener('scroll', () => {
  const p = scrollY / (document.documentElement.scrollHeight - innerHeight) * 100;
  document.getElementById('prog').style.width = p + '%';
}, {passive:true});
</script>
</body>
</html>