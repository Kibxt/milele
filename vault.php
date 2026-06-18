<?php
// MILELE - Secure Escrow Vault

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$transaction_id = filter_input(INPUT_GET, 'tx', FILTER_VALIDATE_INT);
$buyer_id = $_SESSION['user_id'];

if (!$transaction_id) {
    header("Location: index.php");
    exit();
}

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

// Verify this transaction belongs to this buyer and the funds are locked ('funded')
$sql = "SELECT et.*, l.title, l.file_path, l.listing_id, u.full_name as seller_name, u.user_id as seller_id
        FROM escrow_transactions et 
        JOIN listings l ON et.listing_id = l.listing_id 
        JOIN users u ON et.seller_id = u.user_id 
        WHERE et.transaction_id = :tx AND et.buyer_id = :buyer AND et.transaction_status = 'funded'
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':tx' => $transaction_id, ':buyer' => $buyer_id]);
$transaction = $stmt->fetch();

// If it's invalid or already completed, bounce them
if (!$transaction) {
    header("Location: index.php");
    exit();
}

$seller_first_name = explode(' ', $transaction['seller_name'])[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Secure Vault — MILELE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0A0A0C; color: #F3F4F6; }
        .glass-panel { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .pin-display { font-family: 'Space Mono', monospace; letter-spacing: 0.2em; text-shadow: 0 0 20px rgba(52, 211, 153, 0.4); }
        
        /* Radar sweep animation for the security feel */
        @keyframes radar {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .radar-sweep {
            background: conic-gradient(from 0deg, transparent 70%, rgba(52, 211, 153, 0.2) 100%);
            animation: radar 4s linear infinite;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-emerald-500/30 overflow-x-hidden">

    <div class="fixed top-0 w-full z-50 bg-[#0A0A0C]/90 backdrop-blur-xl border-b border-white/5">
        <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="text-xs font-bold uppercase tracking-widest text-gray-500 hover:text-white transition-colors">Close</a>
            <span class="font-bold tracking-widest text-sm text-white flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse shadow-[0_0_10px_rgba(52,211,153,0.8)]"></span>
                ESCROW ACTIVE
            </span>
            <div class="w-10"></div>
        </div>
    </div>

    <main class="flex-1 w-full max-w-md mx-auto px-4 pt-24 pb-12 flex flex-col justify-center relative">
        
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[400px] h-[400px] bg-emerald-500/5 rounded-full blur-[80px] pointer-events-none"></div>

        <div class="text-center mb-8 relative z-10">
            <div class="relative w-24 h-24 mx-auto mb-4 rounded-full border border-emerald-500/30 bg-emerald-500/10 flex items-center justify-center overflow-hidden shadow-[0_0_40px_rgba(52,211,153,0.15)]">
                <div class="absolute inset-0 radar-sweep"></div>
                <div class="absolute inset-1 bg-[#0A0A0C] rounded-full flex items-center justify-center">
                    <span class="text-3xl">🛡️</span>
                </div>
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">Funds Locked Safely</h1>
            <p class="text-sm text-gray-400">Your KES <?php echo number_format($transaction['total_amount']); ?> is held in the MILELE Vault.</p>
        </div>

        <div class="glass-panel rounded-[32px] p-8 text-center relative z-10 border-emerald-500/20">
            <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-500 mb-3">Your Release PIN</p>
            
            <div class="bg-black/50 border border-white/5 rounded-2xl py-6 mb-6 shadow-inner">
                <div class="text-5xl font-bold text-white pin-display tracking-[0.25em] pl-[0.25em]">
                    <?php echo htmlspecialchars($transaction['escrow_pin']); ?>
                </div>
            </div>

            <div class="bg-rose-500/10 border border-rose-500/20 rounded-xl p-4 flex items-start gap-3 text-left">
                <span class="text-xl">⚠️</span>
                <div>
                    <h4 class="text-sm font-bold text-rose-400 mb-1">Never share this code early</h4>
                    <p class="text-xs text-gray-300 leading-relaxed">Only give this PIN to <?php echo $seller_first_name; ?> <strong>after</strong> you have inspected the item and are satisfied.</p>
                </div>
            </div>
        </div>

        <div class="mt-8 space-y-4 relative z-10">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-widest pl-2">Next Steps</h3>
            
            <div class="bg-white/5 border border-white/5 rounded-2xl p-4">
                <h4 class="text-sm font-bold text-white mb-1">1. Message the Seller</h4>
                <p class="text-xs text-gray-400 mb-3">Coordinate a time to meet and inspect the <?php echo htmlspecialchars($transaction['title']); ?>.</p>
                
                <a href="chat.php?listing_id=<?php echo $transaction['listing_id']; ?>&user=<?php echo $transaction['seller_id']; ?>" 
                   class="w-full bg-white/10 border border-white/10 text-white font-bold text-sm py-3 rounded-xl hover:bg-white/20 transition-colors flex items-center justify-center gap-2">
                    <span>💬</span> Chat with <?php echo $seller_first_name; ?>
                </a>
            </div>

            <div class="bg-white/5 border border-white/5 rounded-2xl p-4">
                <h4 class="text-sm font-bold text-white mb-1">2. Meet in Public</h4>
                <p class="text-xs text-gray-400">Arrange to meet at a secure, public location like the CUEA library or cafeteria.</p>
            </div>
        </div>

        <div class="mt-8 text-center relative z-10 pb-8">
            <a href="index.php" class="text-sm font-bold text-gray-500 hover:text-white transition-colors">Return to Feed</a>
        </div>

    </main>
</body>
</html>