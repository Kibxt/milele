<?php
// MILELE - User Wishlist

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Database Connection
$db_host = 'localhost'; $db_name = 'milele_escrow'; $db_user = 'root'; $db_pass = ''; 
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("System Offline.");
}

// Fetch ONLY the active items this specific user has saved
$sql = "SELECT l.*, u.full_name 
        FROM saved_items s
        JOIN listings l ON s.listing_id = l.listing_id
        JOIN users u ON l.seller_id = u.user_id
        WHERE s.user_id = :uid AND l.listing_status = 'active'
        ORDER BY s.saved_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $user_id]);
$saved_items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Saved Items — MILELE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0A0A0C; color: #F3F4F6; padding-bottom: 100px; }
        .glass-nav { background: rgba(10, 10, 12, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .action-fab { background: linear-gradient(135deg, #ffffff 0%, #d1d5db 100%); box-shadow: 0 8px 30px rgba(255,255,255,0.2); }
        .grid-card { background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 16px; overflow: hidden; }
    </style>
</head>
<body class="antialiased">

    <nav class="fixed top-0 w-full z-50 glass-nav">
        <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-white hover:bg-white/10 transition">←</a>
            <span class="font-bold tracking-widest text-sm text-white uppercase">Wishlist</span>
            <div class="w-10"></div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 pt-24">
        
        <?php if (empty($saved_items)): ?>
            <div class="flex flex-col items-center justify-center py-20 text-center border-dashed border-2 border-white/10 rounded-3xl mt-4">
                <div class="text-6xl mb-4 opacity-50">💔</div>
                <h2 class="text-lg font-bold text-white mb-2">Nothing saved yet</h2>
                <p class="text-xs text-gray-400 max-w-[250px] mx-auto">Tap the heart icon on any listing to save it here for later.</p>
                <a href="index.php" class="mt-6 inline-block bg-white text-black font-bold text-sm px-6 py-3 rounded-xl hover:scale-105 transition">Explore Feed</a>
            </div>
        <?php else: ?>
            
            <div class="mb-6 flex justify-between items-end">
                <h1 class="text-2xl font-bold text-white">Your Watchlist</h1>
                <span class="text-xs font-bold text-gray-500 uppercase tracking-widest"><?php echo count($saved_items); ?> Items</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <?php foreach ($saved_items as $item): 
                    $img = $item['file_path'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=200&q=75';
                ?>
                <div class="grid-card cursor-pointer group relative" onclick="window.location.href='item.php?id=<?php echo $item['listing_id']; ?>'">
                    
                    <button class="absolute top-2 right-2 w-8 h-8 rounded-full bg-black/60 backdrop-blur flex items-center justify-center z-10 border border-white/10 text-rose-500 hover:scale-110 transition" onclick="event.stopPropagation(); window.location.href='index.php'; alert('You must view the feed to unsave right now.');">
                        ❤️
                    </button>

                    <div class="aspect-[4/5] bg-neutral-900 overflow-hidden relative">
                        <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    </div>
                    <div class="p-3">
                        <h3 class="text-xs font-bold text-white line-clamp-1"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <p class="text-sm font-bold text-rose-400 mt-1">KES <?php echo number_format($item['price']); ?></p>
                        <p class="text-[9px] text-gray-500 mt-2 uppercase tracking-widest truncate">From <?php echo htmlspecialchars($item['full_name']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </main>

    <div class="fixed bottom-0 w-full z-50 glass-nav border-t border-white/5 pb-safe pt-2">
        <div class="max-w-md mx-auto flex justify-between items-center h-16 px-6">
            <a href="index.php" class="flex flex-col items-center gap-1 text-gray-500 hover:text-white transition-colors"><span class="text-xl">🏪</span></a>
            
            <a href="saved.php" class="flex flex-col items-center gap-1 text-white"><span class="text-xl">📚</span></a>
            
            <a href="Notesing.php" class="relative -top-6">
                <div class="w-14 h-14 rounded-full action-fab flex items-center justify-center text-black text-2xl shadow-[0_0_20px_rgba(255,255,255,0.2)]">
                    <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                </div>
            </a>
            
            <a href="inbox.php" class="flex flex-col items-center gap-1 text-gray-500 hover:text-white transition-colors"><span class="text-xl">💬</span></a>
            <a href="profile.php" class="flex flex-col items-center gap-1 text-gray-500 hover:text-white transition-colors"><span class="text-xl">👤</span></a>
        </div>
    </div>

</body>
</html>