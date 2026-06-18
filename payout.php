<?php
// MILELE - Universal Escrow Dashboard

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

// 1. Fetch Deals where I am the SELLER (Awaiting PIN to claim cash)
$sql_seller = "SELECT et.*, l.title, l.file_path, u.full_name as buyer_name 
               FROM escrow_transactions et 
               JOIN listings l ON et.listing_id = l.listing_id 
               JOIN users u ON et.buyer_id = u.user_id 
               WHERE et.seller_id = :uid AND et.transaction_status = 'funded'
               ORDER BY et.created_at DESC";
$stmt_seller = $pdo->prepare($sql_seller);
$stmt_seller->execute([':uid' => $user_id]);
$pending_payouts = $stmt_seller->fetchAll();

// 2. Fetch Deals where I am the BUYER (I hold the PIN)
$sql_buyer = "SELECT et.*, l.title, l.file_path, u.full_name as seller_name 
              FROM escrow_transactions et 
              JOIN listings l ON et.listing_id = l.listing_id 
              JOIN users u ON et.seller_id = u.user_id 
              WHERE et.buyer_id = :uid AND et.transaction_status = 'funded'
              ORDER BY et.created_at DESC";
$stmt_buyer = $pdo->prepare($sql_buyer);
$stmt_buyer->execute([':uid' => $user_id]);
$active_purchases = $stmt_buyer->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Escrow Dashboard — MILELE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0A0A0C; color: #F3F4F6; padding-bottom: 100px; }
        .glass-nav { background: rgba(10, 10, 12, 0.75); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-panel { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.06); }
        .pin-input {
            background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1);
            font-family: 'Space Mono', monospace; letter-spacing: 0.5em; text-transform: uppercase; text-align: center; transition: all 0.3s ease;
        }
        .pin-input:focus { background: rgba(16, 185, 129, 0.05); border-color: #10b981; box-shadow: 0 0 20px rgba(16, 185, 129, 0.2); outline: none; }
    </style>
</head>
<body class="antialiased">

    <nav class="fixed top-0 w-full z-50 glass-nav">
        <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="profile.php" class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-white hover:bg-white/10 transition-colors">←</a>
            <span class="font-bold tracking-[0.2em] text-sm text-white uppercase">Escrow Hub</span>
            <div class="w-10"></div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 pt-24 space-y-10">

        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex gap-3 items-center">
                <span class="text-2xl">💸</span>
                <div>
                    <h4 class="text-sm font-bold text-emerald-400">Funds Released!</h4>
                    <p class="text-xs text-gray-300"><?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-sm text-rose-400 text-center font-medium">
                <?php echo htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($active_purchases)): ?>
        <section>
            <div class="mb-4">
                <h1 class="text-xl font-bold text-white flex items-center gap-2">🔐 My Locked Purchases</h1>
                <p class="text-xs text-gray-400 mt-1">Items you paid for. Give the PIN to the seller when you meet.</p>
            </div>

            <div class="space-y-4">
                <?php foreach ($active_purchases as $deal): 
                    $img = $deal['file_path'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&q=80';
                ?>
                <div class="glass-panel rounded-3xl p-5 border-blue-500/20 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    
                    <div class="flex gap-4 items-center">
                        <div class="w-14 h-14 rounded-xl overflow-hidden shrink-0 bg-neutral-900 border border-white/10">
                            <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1">
                            <h2 class="text-sm font-semibold text-white line-clamp-1"><?php echo htmlspecialchars($deal['title']); ?></h2>
                            <p class="text-[10px] text-gray-400 mt-0.5 uppercase tracking-wider">From: <span class="text-gray-200 font-bold"><?php echo htmlspecialchars($deal['seller_name']); ?></span></p>
                            <div class="text-sm font-bold text-blue-400 mt-1">KES <?php echo number_format($deal['total_amount']); ?> Locked</div>
                        </div>
                    </div>
                    
                    <div class="mt-4 flex gap-2">
                        <a href="chat.php?listing_id=<?php echo $deal['listing_id']; ?>&user=<?php echo $deal['seller_id']; ?>" class="w-12 h-12 flex items-center justify-center bg-white/5 rounded-xl border border-white/10 hover:bg-white/10 transition text-xl">💬</a>
                        <a href="vault.php?tx=<?php echo $deal['transaction_id']; ?>" class="flex-1 bg-white text-black font-bold py-3.5 rounded-xl text-center shadow-[0_0_20px_rgba(255,255,255,0.1)] hover:scale-[1.02] transition-transform active:scale-95 flex items-center justify-center gap-2">
                            <span>🔑</span> View Escrow PIN
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section>
            <div class="mb-4">
                <h1 class="text-xl font-bold text-white flex items-center gap-2">💸 Pending Payouts</h1>
                <p class="text-xs text-gray-400 mt-1">Ask the buyer for their 6-digit code to instantly transfer these funds.</p>
            </div>

            <?php if (empty($pending_payouts)): ?>
                <div class="glass-panel rounded-3xl p-12 text-center border-dashed border-2 border-white/10">
                    <div class="text-4xl mb-3 opacity-50">📭</div>
                    <h3 class="text-sm font-bold text-white mb-1">No locked funds</h3>
                    <p class="text-xs text-gray-400">You don't have any pending payouts right now.</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($pending_payouts as $deal): 
                        $img = $deal['file_path'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&q=80';
                    ?>
                    <div class="glass-panel rounded-3xl p-5 border-emerald-500/20">
                        
                        <div class="flex gap-4 items-center mb-5">
                            <div class="w-16 h-16 rounded-2xl overflow-hidden shrink-0 bg-neutral-900 border border-white/10">
                                <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h2 class="text-sm font-semibold text-white line-clamp-1"><?php echo htmlspecialchars($deal['title']); ?></h2>
                                        <p class="text-[11px] text-gray-400 mt-0.5">Meeting with: <span class="text-gray-200 font-bold"><?php echo htmlspecialchars($deal['buyer_name']); ?></span></p>
                                    </div>
                                    <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-2 py-1 rounded text-[9px] font-bold uppercase tracking-widest">Locked</span>
                                </div>
                                <div class="text-lg font-bold text-white mt-1">KES <?php echo number_format($deal['net_payout']); ?></div>
                            </div>
                        </div>

                        <form action="process_payout.php" method="POST" class="bg-black/30 rounded-2xl p-4 border border-white/5 relative overflow-hidden">
                            <input type="hidden" name="transaction_id" value="<?php echo $deal['transaction_id']; ?>">
                            
                            <label class="block text-xs font-bold uppercase tracking-widest text-emerald-500 mb-3 text-center">Enter Buyer's 6-Digit PIN</label>
                            
                            <input type="text" name="escrow_pin" maxlength="6" required autocomplete="off" placeholder="••••••"
                                   class="w-full py-4 rounded-xl pin-input text-white text-2xl font-bold mb-4">
                            
                            <button type="submit" class="w-full bg-emerald-500 text-white font-bold py-3.5 rounded-xl shadow-[0_0_20px_rgba(16,185,129,0.3)] hover:bg-emerald-400 transition-all active:scale-[0.98]">
                                Verify & Claim KES <?php echo number_format($deal['net_payout']); ?>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        
    </main>

    <div class="fixed bottom-0 w-full z-50 glass-nav border-t border-white/5 pb-safe pt-2">
        <div class="max-w-md mx-auto flex justify-between items-center h-16 px-6">
            <a href="index.php" class="flex flex-col items-center gap-1 text-gray-500 hover:text-white transition-colors"><span class="text-xl">🏪</span></a>
            <a href="saved.php" class="flex flex-col items-center gap-1 text-gray-500 hover:text-white transition-colors"><span class="text-xl">📚</span></a>
            
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