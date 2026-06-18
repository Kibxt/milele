<?php
// MILELE - Secure Digital Delivery Gateway

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

// 1. SECURITY CHECK: Did this user actually buy this digital item?
$sql = "SELECT et.*, l.title, l.file_path, u.full_name as seller_name 
        FROM escrow_transactions et 
        JOIN listings l ON et.listing_id = l.listing_id 
        JOIN users u ON et.seller_id = u.user_id 
        WHERE et.transaction_id = :tx 
        AND et.buyer_id = :buyer 
        AND et.transaction_status = 'released' 
        AND l.item_type = 'digital'
        LIMIT 1";
        
$stmt = $pdo->prepare($sql);
$stmt->execute([':tx' => $transaction_id, ':buyer' => $buyer_id]);
$transaction = $stmt->fetch();

// Bouncer: If they didn't buy it, or it's a physical item, kick them to the feed
if (!$transaction) {
    header("Location: index.php");
    exit();
}

// Extract file extension for the UI (e.g., PDF, ZIP)
$file_ext = strtoupper(pathinfo($transaction['file_path'], PATHINFO_EXTENSION)) ?: 'FILE';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Download — MILELE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0A0A0C; color: #F3F4F6; }
        .glass-panel { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); }
        
        /* Cinematic Pulse Animation */
        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 0.5; }
            100% { transform: scale(1.5); opacity: 0; }
        }
        .ring-anim::before {
            content: ''; position: absolute; inset: 0; border-radius: 50%;
            border: 2px solid #2DD4BF; animation: pulse-ring 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col overflow-x-hidden">

    <div class="fixed top-0 w-full z-50 bg-[#0A0A0C]/90 backdrop-blur-xl border-b border-white/5">
        <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="text-xs font-bold uppercase tracking-widest text-gray-500 hover:text-white transition-colors">Close</a>
            <span class="font-bold tracking-widest text-sm text-white flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-teal-400"></span>
                PAYMENT SUCCESS
            </span>
            <div class="w-10"></div>
        </div>
    </div>

    <main class="flex-1 w-full max-w-md mx-auto px-4 pt-24 pb-12 flex flex-col justify-center relative">
        
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[400px] h-[400px] bg-teal-500/10 rounded-full blur-[80px] pointer-events-none"></div>

        <div class="text-center mb-10 relative z-10">
            <div class="relative w-24 h-24 mx-auto mb-6 rounded-full border border-teal-500/30 bg-teal-500/10 flex items-center justify-center shadow-[0_0_40px_rgba(45,212,191,0.2)] ring-anim">
                <span class="text-4xl">⚡</span>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2 tracking-tight">Ready to Download</h1>
            <p class="text-sm text-gray-400">Your payment of KES <?php echo number_format($transaction['total_amount']); ?> was verified successfully.</p>
        </div>

        <div class="glass-panel rounded-[32px] p-6 text-center relative z-10 border-teal-500/20 shadow-2xl">
            
            <div class="bg-black/40 border border-white/5 rounded-2xl p-5 mb-6 flex items-center gap-4 text-left">
                <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-teal-500 to-blue-600 flex items-center justify-center text-white font-bold tracking-widest shadow-lg shrink-0">
                    <?php echo $file_ext; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-bold text-white truncate"><?php echo htmlspecialchars($transaction['title']); ?></h3>
                    <p class="text-[11px] text-gray-400 mt-1 uppercase tracking-wider">Author: <span class="text-gray-200"><?php echo htmlspecialchars($transaction['seller_name']); ?></span></p>
                </div>
            </div>

            <a href="<?php echo htmlspecialchars($transaction['file_path']); ?>" download class="w-full bg-white text-black font-bold text-lg py-4 rounded-xl shadow-[0_0_30px_rgba(255,255,255,0.2)] hover:scale-[1.02] transition-transform active:scale-95 flex items-center justify-center gap-3">
                <span>⬇️</span> Save File to Device
            </a>

            <div class="mt-5 flex items-start gap-3 bg-teal-500/10 border border-teal-500/20 rounded-xl p-4 text-left">
                <span class="text-lg">🛡️</span>
                <div>
                    <h4 class="text-xs font-bold text-teal-400 mb-0.5">Secure Lifetime Access</h4>
                    <p class="text-[10px] text-gray-400 leading-relaxed">This unique download link is permanently tied to your student account. You can access it anytime from your active purchases.</p>
                </div>
            </div>

        </div>

    </main>
</body>
</html>