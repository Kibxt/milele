<?php
// MILELE - Premium Item Details Page

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$listing_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$listing_id) {
    header("Location: index.php");
    exit();
}

$db_host = 'localhost'; $db_name = 'milele_escrow'; $db_user = 'root'; $db_pass = ''; 
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("System Offline.");
}

$sql = "SELECT l.*, u.full_name, u.university_name, u.completed_escrows 
        FROM listings l 
        JOIN users u ON l.seller_id = u.user_id 
        WHERE l.listing_id = :listing_id AND l.listing_status = 'active' 
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':listing_id' => $listing_id]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: index.php");
    exit();
}

function getInitials($name) {
    $name = trim($name ?? '');
    if (!$name) return "XX"; 
    $words = explode(" ", $name);
    $initials = "";
    foreach ($words as $w) { if (strlen($w) > 0) { $initials .= strtoupper($w[0]); } }
    return substr($initials, 0, 2);
}

// THE FIX: Changed === to == so PHP ignores strict type differences
$is_owner = ($_SESSION['user_id'] == $item['seller_id']);

$img = $item['file_path'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=800&q=80';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($item['title']); ?> — MILELE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0A0A0C; color: #F3F4F6; padding-bottom: 120px; }
        .glass-nav { background: rgba(10, 10, 12, 0.75); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-card { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.06); }
        .img-gradient { background: linear-gradient(to top, #0A0A0C 0%, transparent 40%); }
    </style>
</head>
<body class="antialiased selection:bg-white/20">

    <nav class="fixed top-0 w-full z-50 bg-gradient-to-b from-black/80 to-transparent pb-4 pt-4">
        <div class="max-w-3xl mx-auto px-4 flex items-center justify-between">
            <a href="index.php" class="w-10 h-10 rounded-full bg-black/40 backdrop-blur-md border border-white/10 flex items-center justify-center text-white hover:bg-white/10 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <div class="flex gap-3">
                <button class="w-10 h-10 rounded-full bg-black/40 backdrop-blur-md border border-white/10 flex items-center justify-center text-white hover:bg-white/10 transition-colors">🤍</button>
                <button class="w-10 h-10 rounded-full bg-black/40 backdrop-blur-md border border-white/10 flex items-center justify-center text-white hover:bg-white/10 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                </button>
            </div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto">
        
        <div class="relative w-full aspect-[4/5] sm:aspect-video bg-neutral-900">
            <img src="<?php echo htmlspecialchars($img); ?>" alt="Item Image" class="w-full h-full object-cover">
            <div class="absolute inset-0 img-gradient pointer-events-none"></div>
            
            <div class="absolute bottom-6 left-4 flex gap-2">
                <span class="bg-white/10 backdrop-blur-md border border-white/10 px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-wider text-white">
                    <?php echo htmlspecialchars($item['category']); ?>
                </span>
                <span class="bg-black/60 backdrop-blur-md border border-white/10 px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-wider <?php echo $item['item_type'] === 'digital' ? 'text-emerald-400' : 'text-blue-400'; ?>">
                    <?php echo $item['item_type'] === 'digital' ? '⚡ Instant File' : '🤝 Campus Meetup'; ?>
                </span>
            </div>
        </div>

        <div class="px-5 pt-6 space-y-6">
            
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white leading-tight mb-2"><?php echo htmlspecialchars($item['title']); ?></h1>
                <p class="text-3xl font-bold text-white tracking-tight">KES <?php echo number_format($item['price']); ?></p>
            </div>

            <div class="glass-card rounded-2xl p-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-emerald-600 to-blue-600 flex items-center justify-center text-sm font-bold text-white shadow-lg border border-white/20">
                        <?php echo getInitials($item['full_name']); ?>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                            <?php echo htmlspecialchars($item['full_name']); ?>
                            <?php if ($is_owner): ?> <span class="text-[10px] text-teal-400 font-bold tracking-widest uppercase">(You)</span> <?php endif; ?>
                        </h3>
                        <p class="text-xs text-gray-400"><?php echo htmlspecialchars($item['university_name']); ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-bold text-white"><?php echo $item['completed_escrows']; ?></div>
                    <div class="text-[10px] text-gray-500 uppercase tracking-widest">Deals</div>
                </div>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-5">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">Item Details</h3>
                <div class="text-sm text-gray-300 leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($item['description']); ?></div>
            </div>

            <div class="border border-emerald-500/20 bg-emerald-500/5 rounded-2xl p-4 flex gap-3 items-start">
                <span class="text-xl">🛡️</span>
                <div>
                    <h4 class="text-sm font-bold text-emerald-400 mb-1">MILELE Escrow Protection</h4>
                    <p class="text-xs text-gray-400 leading-relaxed">Your money is held safely in our vault until you meet the seller and verify the item. Only then do you release the payment.</p>
                </div>
            </div>

        </div>
    </main>

    <div class="fixed bottom-0 w-full z-50 glass-nav border-t border-white/5 p-4 pb-safe">
        <div class="max-w-3xl mx-auto flex gap-3">
            
            <?php if ($is_owner): ?>
                <button onclick="window.location.href='profile.php'" class="w-full bg-white/10 text-white font-bold py-4 rounded-xl border border-white/20 hover:bg-white/20 transition-colors">
                    Manage My Listing
                </button>
            <?php else: ?>
                <button onclick="window.location.href='chat.php?listing_id=<?php echo $item['listing_id']; ?>&user=<?php echo $item['seller_id']; ?>'" 
                        class="w-14 shrink-0 rounded-xl glass-card flex items-center justify-center text-xl hover:bg-white/10 transition-colors shadow-[0_0_20px_rgba(255,255,255,0.05)]">
                    💬
                </button>
                
                <button onclick="window.location.href='checkout.php?id=<?php echo $item['listing_id']; ?>'" 
                        class="flex-1 bg-white text-black font-bold text-base py-4 rounded-xl shadow-[0_0_30px_rgba(255,255,255,0.2)] hover:scale-[1.02] transition-transform active:scale-95 flex items-center justify-center gap-2">
                    <?php echo $item['item_type'] === 'digital' ? 'Pay & Download Instantly' : 'Buy Securely via Escrow'; ?>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </button>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>