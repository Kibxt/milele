<?php
// MILELE - Premium User Profile & Command Center

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$db_host = 'localhost'; $db_name = 'milele_escrow'; $db_user = 'root'; $db_pass = ''; 
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("System Offline.");
}

$stmt_user = $pdo->prepare("SELECT full_name, email, university_name, completed_escrows, account_state FROM users WHERE user_id = :uid");
$stmt_user->execute([':uid' => $user_id]);
$user = $stmt_user->fetch();

$stmt_listings = $pdo->prepare("SELECT * FROM listings WHERE seller_id = :uid AND listing_status = 'active' ORDER BY created_at DESC");
$stmt_listings->execute([':uid' => $user_id]);
$my_listings = $stmt_listings->fetchAll();

function getInitials($name) {
    $name = trim($name ?? '');
    if (!$name) return "XX"; 
    $words = explode(" ", $name);
    $initials = "";
    foreach ($words as $w) { if (strlen($w) > 0) { $initials .= strtoupper($w[0]); } }
    return substr($initials, 0, 2);
}

$initials = getInitials($user['full_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Profile — MILELE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0A0A0C; color: #F3F4F6; padding-bottom: 100px; }
        .glass-nav { background: rgba(10, 10, 12, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-panel { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.06); }
        .avatar-glow { box-shadow: 0 0 40px rgba(45, 212, 191, 0.3), inset 0 0 20px rgba(255, 255, 255, 0.2); }
        .action-fab { background: linear-gradient(135deg, #ffffff 0%, #d1d5db 100%); box-shadow: 0 8px 30px rgba(255,255,255,0.2); }
        .menu-item { transition: background 0.2s ease, transform 0.2s ease; }
        .menu-item:hover { background: rgba(255, 255, 255, 0.05); transform: translateX(4px); }
        .grid-card { background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 16px; overflow: hidden; }
    </style>
</head>
<body class="antialiased">

    <div id="delete-modal" class="fixed inset-0 z-[9999] hidden flex items-center justify-center p-4 transition-all duration-300 opacity-0 pointer-events-none">
        <div class="absolute inset-0 bg-[#0A0A0C]/80 backdrop-blur-md" onclick="closeDeleteModal()"></div>
        <div class="relative w-full max-w-sm glass-panel rounded-3xl p-6 transform scale-95 transition-transform duration-300 shadow-[0_20px_50px_rgba(0,0,0,0.5)] border border-white/10" id="delete-modal-content">
            <div class="w-16 h-16 rounded-full bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-3xl mb-5 mx-auto text-rose-500 shadow-[0_0_30px_rgba(244,63,94,0.2)]">
                🗑️
            </div>
            <h3 class="text-xl font-bold text-white text-center mb-2">Remove Listing?</h3>
            <p class="text-sm text-gray-400 text-center mb-6 leading-relaxed">This will permanently hide the item from the campus feed. Are you sure you want to proceed?</p>
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()" class="flex-1 bg-white/5 border border-white/10 text-white font-bold py-3.5 rounded-xl hover:bg-white/10 transition-colors active:scale-95">
                    Cancel
                </button>
                <a id="confirm-delete-btn" href="#" class="flex-1 bg-rose-500 text-white font-bold py-3.5 rounded-xl text-center shadow-[0_0_20px_rgba(244,63,94,0.3)] hover:bg-rose-400 transition-all active:scale-95 block">
                    Yes, Remove
                </a>
            </div>
        </div>
    </div>

    <nav class="fixed top-0 w-full z-50 glass-nav">
        <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="w-10"></div>
            <span class="font-bold tracking-widest text-sm text-white uppercase">Command Center</span>
            <div class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-white cursor-pointer hover:bg-white/10 transition" onclick="window.location.href='logout.php'" title="Log Out">
                <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            </div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 pt-24 space-y-6">
        
        <div class="glass-panel rounded-3xl p-6 relative overflow-hidden">
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-teal-500/20 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="flex items-center gap-5 relative z-10">
                <div class="w-20 h-20 rounded-full bg-gradient-to-tr from-blue-600 to-teal-400 flex items-center justify-center text-2xl font-bold text-white avatar-glow border-2 border-[#0A0A0C] shrink-0">
                    <?php echo $initials; ?>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white leading-tight"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                        🎓 <?php echo htmlspecialchars($user['university_name']); ?>
                    </p>
                    <div class="mt-2 inline-block bg-white/10 px-2 py-1 rounded text-[10px] font-bold text-teal-400 uppercase tracking-widest border border-white/10">
                        Verified Student
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mt-6 pt-6 border-t border-white/5">
                <div class="text-center">
                    <div class="text-xl font-bold text-white"><?php echo count($my_listings); ?></div>
                    <div class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">Active Items</div>
                </div>
                <div class="text-center border-l border-white/5">
                    <div class="text-xl font-bold text-white"><?php echo $user['completed_escrows']; ?></div>
                    <div class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">Deals Done</div>
                </div>
                <div class="text-center border-l border-white/5">
                    <div class="text-xl font-bold text-white flex items-center justify-center gap-1">
                        5.0 <svg class="w-3 h-3 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                    </div>
                    <div class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">Rating</div>
                </div>
            </div>
        </div>

        <div class="glass-panel rounded-3xl overflow-hidden">
            
            <a href="payout.php" class="menu-item flex items-center justify-between p-5 border-b border-white/5 cursor-pointer">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-teal-500/10 border border-teal-500/20 flex items-center justify-center text-xl text-teal-400">💸</div>
                    <div>
                        <h3 class="text-sm font-bold text-white">Escrow Dashboard</h3>
                        <p class="text-xs text-gray-500">Claim funds & view buyer PINs</p>
                    </div>
                </div>
                <span class="text-gray-500">→</span>
            </a>

            <a href="#" class="menu-item flex items-center justify-between p-5 border-b border-white/5 cursor-pointer" onclick="alert('Account settings coming soon!')">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-xl text-blue-400">⚙️</div>
                    <div>
                        <h3 class="text-sm font-bold text-white">Account Settings</h3>
                        <p class="text-xs text-gray-500">Edit M-Pesa number, email, password</p>
                    </div>
                </div>
                <span class="text-gray-500">→</span>
            </a>
            
            <a href="#" class="menu-item flex items-center justify-between p-5 cursor-pointer" onclick="alert('Help center coming soon!')">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-xl text-gray-300">🛡️</div>
                    <div>
                        <h3 class="text-sm font-bold text-white">Trust & Safety</h3>
                        <p class="text-xs text-gray-500">Report an issue, escrow rules</p>
                    </div>
                </div>
                <span class="text-gray-500">→</span>
            </a>

        </div>

        <div>
            <div class="flex items-center justify-between mb-4 mt-2">
                <h2 class="text-xs font-bold text-gray-500 uppercase tracking-widest">My Active Listings</h2>
                <a href="Notesing.php" class="text-[10px] font-bold text-teal-400 uppercase tracking-widest border border-teal-400/30 px-3 py-1 rounded-full bg-teal-400/10 hover:bg-teal-400/20 transition">Add New</a>
            </div>

            <?php if (empty($my_listings)): ?>
                <div class="glass-panel rounded-2xl p-8 text-center border-dashed border-2 border-white/10">
                    <div class="text-3xl mb-2 opacity-50">🛒</div>
                    <p class="text-sm font-bold text-white">You aren't selling anything.</p>
                    <p class="text-xs text-gray-500 mt-1">Turn your old items into cash.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <?php foreach ($my_listings as $item): 
                        $img = $item['file_path'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=200&q=75';
                    ?>
                    <div class="grid-card cursor-pointer group" onclick="window.location.href='item.php?id=<?php echo $item['listing_id']; ?>'">
                        <div class="aspect-square bg-neutral-900 relative">
                            <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            
                            <div class="absolute top-2 right-2 w-6 h-6 bg-black/60 backdrop-blur rounded-full flex items-center justify-center border border-white/10 hover:bg-rose-500 transition z-10 hover:shadow-[0_0_15px_rgba(244,63,94,0.5)]" 
                                 onclick="event.stopPropagation(); openDeleteModal(<?php echo $item['listing_id']; ?>);">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </div>

                        </div>
                        <div class="p-3">
                            <h3 class="text-[11px] font-bold text-white line-clamp-1"><?php echo htmlspecialchars($item['title']); ?></h3>
                            <p class="text-xs font-bold text-teal-400 mt-1">KES <?php echo number_format($item['price']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <div class="fixed bottom-0 w-full z-50 glass-nav pb-safe pt-2">
        <div class="max-w-md mx-auto flex justify-between items-center h-16 px-6">
            <a href="index.php" class="flex flex-col items-center gap-1 text-gray-500 hover:text-white transition-colors"><span class="text-xl">🏪</span></a>
            <a href="saved.php" class="flex flex-col items-center gap-1 text-gray-500 hover:text-white transition-colors"><span class="text-xl">📚</span></a>
            
            <a href="Notesing.php" class="relative -top-6">
                <div class="w-14 h-14 rounded-full action-fab flex items-center justify-center text-black text-2xl shadow-[0_0_20px_rgba(255,255,255,0.2)]">
                    <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                </div>
            </a>
            
            <a href="inbox.php" class="flex flex-col items-center gap-1 text-gray-500 hover:text-white transition-colors"><span class="text-xl">💬</span></a>
            <a href="profile.php" class="flex flex-col items-center gap-1 text-white"><span class="text-xl">👤</span></a>
        </div>
    </div>

    <script>
        function openDeleteModal(listingId) {
            const modal = document.getElementById('delete-modal');
            const content = document.getElementById('delete-modal-content');
            const confirmBtn = document.getElementById('confirm-delete-btn');
            
            confirmBtn.href = 'delete_listing.php?id=' + listingId;
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0', 'pointer-events-none');
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
        }

        function closeDeleteModal() {
            const modal = document.getElementById('delete-modal');
            const content = document.getElementById('delete-modal-content');
            
            modal.classList.add('opacity-0', 'pointer-events-none');
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            
            setTimeout(() => { modal.classList.add('hidden'); }, 300);
        }
    </script>
</body>
</html>