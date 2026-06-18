<?php
// MILELE - Secure Escrow Checkout

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$listing_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$listing_id) {
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

// Fetch the item
$sql = "SELECT l.*, u.full_name FROM listings l JOIN users u ON l.seller_id = u.user_id WHERE l.listing_id = :id AND l.listing_status = 'active'";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $listing_id]);
$item = $stmt->fetch();

// Security checks
if (!$item) {
    header("Location: index.php");
    exit();
}
// You cannot buy your own item
if ($item['seller_id'] === $_SESSION['user_id']) {
    header("Location: item.php?id=" . $listing_id);
    exit();
}

// Calculate Financials (e.g., 3% platform fee)
$price = (float)$item['price'];
$platform_fee = $price * 0.03;
$total_amount = $price + $platform_fee;

$img = $item['file_path'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=400&q=80';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Secure Checkout — MILELE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0A0A0C; color: #F3F4F6; }
        .glass-panel { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); }
        .glass-input { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); transition: all 0.3s ease; }
        .glass-input:focus { background: rgba(255,255,255,0.06); border-color: #10b981; outline: none; box-shadow: 0 0 15px rgba(16, 185, 129, 0.2); }
    </style>
</head>
<body class="antialiased pb-20">

    <div class="fixed top-0 w-full z-50 bg-[#0A0A0C]/90 backdrop-blur-xl border-b border-white/5">
        <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="item.php?id=<?php echo $listing_id; ?>" class="w-10 h-10 flex items-center justify-center rounded-full bg-white/5 hover:bg-white/10 text-white font-bold transition-colors">←</a>
            <span class="font-bold tracking-widest text-sm text-white uppercase">Escrow Vault</span>
            <div class="w-10"></div>
        </div>
    </div>

    <main class="max-w-3xl mx-auto px-4 pt-24 space-y-6">
        
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-sm text-rose-400 text-center font-medium">
                <?php echo htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="glass-panel rounded-3xl p-5 flex gap-4 items-center">
            <div class="w-20 h-20 rounded-2xl overflow-hidden shrink-0 bg-neutral-900">
                <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-full object-cover">
            </div>
            <div class="flex-1">
                <h2 class="text-sm font-semibold text-white mb-1 line-clamp-1"><?php echo htmlspecialchars($item['title']); ?></h2>
                <p class="text-xs text-gray-400 mb-2">Seller: <?php echo htmlspecialchars($item['full_name']); ?></p>
                <div class="text-lg font-bold text-white">KES <?php echo number_format($price); ?></div>
            </div>
        </div>

        <div class="glass-panel rounded-3xl p-6 space-y-4">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-4">Transaction Summary</h3>
            <div class="flex justify-between text-sm text-gray-300">
                <span>Item Price</span>
                <span class="font-medium text-white">KES <?php echo number_format($price); ?></span>
            </div>
            <div class="flex justify-between text-sm text-gray-300">
                <span>MILELE Escrow Fee (3%)</span>
                <span class="font-medium text-white">KES <?php echo number_format($platform_fee); ?></span>
            </div>
            <div class="pt-4 border-t border-white/10 flex justify-between items-center">
                <span class="text-base font-bold text-white">Total Required</span>
                <span class="text-2xl font-bold text-emerald-400">KES <?php echo number_format($total_amount); ?></span>
            </div>
        </div>

        <form action="process_checkout.php" method="POST" class="space-y-6">
            <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">
            
            <div class="glass-panel rounded-3xl p-6 border-emerald-500/30 bg-emerald-500/5">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400">📱</div>
                    <h3 class="text-sm font-bold text-white">M-Pesa Payment</h3>
                </div>
                <p class="text-xs text-gray-400 mb-4 leading-relaxed">Your funds will be locked securely. The seller will not receive payment until you meet them, inspect the item, and give them the 6-digit Escrow PIN.</p>
                
                <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">M-Pesa Number</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-5 flex items-center text-gray-400 font-bold">+254</span>
                    <input type="tel" name="phone_number" required placeholder="712 345 678" pattern="[0-9]{9}" maxlength="9"
                           class="w-full px-5 py-4 pl-16 rounded-2xl glass-input text-lg font-bold text-white placeholder-gray-600 focus:ring-0">
                </div>
                <p class="text-[10px] text-gray-500 mt-2">Enter the last 9 digits. An STK prompt will be sent to your phone.</p>
            </div>

            <div class="fixed bottom-0 left-0 w-full bg-[#0A0A0C]/90 backdrop-blur-xl border-t border-white/5 p-4 z-40">
                <div class="max-w-3xl mx-auto">
                    <button type="submit" class="w-full bg-emerald-500 text-black font-bold text-base py-4 rounded-xl shadow-[0_0_30px_rgba(16,185,129,0.3)] hover:scale-[1.02] transition-transform active:scale-95">
                        Lock KES <?php echo number_format($total_amount); ?> in Vault
                    </button>
                </div>
            </div>
        </form>
    </main>

</body>
</html>