<?php
// MILELE - Premium Notification Center

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

// Mark all as read if requested
if (isset($_GET['action']) && $_GET['action'] === 'read_all') {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid")->execute([':uid' => $user_id]);
    header("Location: notifications.php");
    exit();
}

// Fetch all notifications for this user
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 50");
$stmt->execute([':uid' => $user_id]);
$notifications = $stmt->fetchAll();

// Time formatter
function formatTime($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff / 60) . "m ago";
    if ($diff < 86400) return floor($diff / 3600) . "h ago";
    if ($diff < 172800) return "Yesterday";
    return date("M j", $time);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Notifications — MILELE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0A0A0C; color: #F3F4F6; padding-bottom: 100px; }
        .glass-nav { background: rgba(10, 10, 12, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .notif-card { background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); transition: background 0.2s; }
        .notif-card:hover { background: rgba(255, 255, 255, 0.04); }
        .unread { border-left: 3px solid #34D399; background: rgba(52, 211, 153, 0.05); }
    </style>
</head>
<body class="antialiased">

    <nav class="fixed top-0 w-full z-50 glass-nav">
        <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-white hover:bg-white/10 transition">←</a>
            <span class="font-bold tracking-widest text-sm text-white uppercase">Notifications</span>
            
            <?php if (!empty($notifications)): ?>
                <a href="notifications.php?action=read_all" class="text-[10px] font-bold text-teal-400 uppercase tracking-widest hover:text-teal-300 transition">Read All</a>
            <?php else: ?>
                <div class="w-10"></div>
            <?php endif; ?>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 pt-24 space-y-3">
        
        <?php if (empty($notifications)): ?>
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="w-20 h-20 rounded-full bg-white/5 flex items-center justify-center text-4xl mb-6 shadow-[0_0_30px_rgba(255,255,255,0.05)]">
                    🔕
                </div>
                <h2 class="text-xl font-bold text-white mb-2">You're all caught up</h2>
                <p class="text-sm text-gray-400 max-w-[250px] mx-auto leading-relaxed">When someone buys your item or sends you a message, it will show up here.</p>
            </div>
        <?php else: ?>
            
            <?php foreach ($notifications as $notif): 
                $is_unread = ($notif['is_read'] == 0);
            ?>
            <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="block notif-card rounded-2xl p-4 flex gap-4 items-start <?php echo $is_unread ? 'unread' : ''; ?>">
                <div class="w-12 h-12 rounded-full bg-black/40 border border-white/10 flex items-center justify-center text-xl shrink-0">
                    <?php echo htmlspecialchars($notif['icon']); ?>
                </div>
                <div class="flex-1 min-w-0 pt-0.5">
                    <div class="flex justify-between items-start mb-1">
                        <h3 class="text-sm font-bold <?php echo $is_unread ? 'text-white' : 'text-gray-300'; ?> truncate pr-2">
                            <?php echo htmlspecialchars($notif['title']); ?>
                        </h3>
                        <span class="text-[10px] font-semibold text-gray-500 whitespace-nowrap shrink-0"><?php echo formatTime($notif['created_at']); ?></span>
                    </div>
                    <p class="text-xs text-gray-400 leading-relaxed line-clamp-2">
                        <?php echo htmlspecialchars($notif['message']); ?>
                    </p>
                </div>
            </a>
            <?php endforeach; ?>

        <?php endif; ?>
    </main>

</body>
</html>