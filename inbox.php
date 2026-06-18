<?php
// MILELE - Secure Campus Inbox

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

// Fetch the absolute latest message for every distinct conversation the user is part of
$sql = "SELECT m.*, 
        u_other.full_name AS other_user_name, 
        l.title AS listing_title, 
        l.file_path AS listing_image
        FROM messages m
        JOIN users u_other ON (u_other.user_id = CASE WHEN m.sender_id = :uid THEN m.receiver_id ELSE m.sender_id END)
        LEFT JOIN listings l ON m.listing_id = l.listing_id
        WHERE m.message_id IN (
            SELECT MAX(message_id) 
            FROM messages 
            WHERE sender_id = :uid OR receiver_id = :uid
            GROUP BY LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id), listing_id
        )
        ORDER BY m.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $user_id]);
$conversations = $stmt->fetchAll();

// Bulletproof Initials Function
function getInitials($name) {
    $name = trim($name ?? '');
    if (!$name) return "XX"; 
    $words = explode(" ", $name);
    $initials = "";
    foreach ($words as $w) { 
        if (strlen($w) > 0) { $initials .= strtoupper($w[0]); }
    }
    return substr($initials, 0, 2);
}

// Time formatter (e.g., "2m ago", "1h ago", "Yesterday")
function formatTime($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return "Now";
    if ($diff < 3600) return floor($diff / 60) . "m";
    if ($diff < 86400) return floor($diff / 3600) . "h";
    if ($diff < 172800) return "Yesterday";
    return date("M j", $time);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Inbox — MILELE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0A0A0C; color: #F3F4F6; padding-bottom: 100px; }
        .glass-nav { background: rgba(10, 10, 12, 0.75); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .chat-row { transition: background 0.2s ease; border-bottom: 1px solid rgba(255, 255, 255, 0.03); }
        .chat-row:hover { background: rgba(255, 255, 255, 0.02); }
        .action-fab { background: linear-gradient(135deg, #ffffff 0%, #d1d5db 100%); box-shadow: 0 8px 30px rgba(255,255,255,0.2); }
    </style>
</head>
<body class="antialiased">

    <nav class="fixed top-0 w-full z-50 glass-nav">
        <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
            <span class="font-bold tracking-widest text-sm text-white uppercase ml-2">Inbox</span>
            <div class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-white cursor-pointer hover:bg-white/10 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto pt-20">
        
        <?php if (empty($conversations)): ?>
            <div class="flex flex-col items-center justify-center px-4 py-20 text-center">
                <div class="text-6xl mb-4 opacity-30">💬</div>
                <h2 class="text-xl font-bold text-white mb-2">No messages yet</h2>
                <p class="text-sm text-gray-400 max-w-xs mx-auto">When you contact a seller or a buyer asks about your item, the conversation will appear here.</p>
            </div>
        <?php else: ?>
            
            <div class="flex flex-col">
                <?php foreach ($conversations as $chat): 
                    $other_name = htmlspecialchars($chat['other_user_name']);
                    $item_title = htmlspecialchars($chat['listing_title']);
                    $msg_text = htmlspecialchars($chat['message_text']);
                    $time = formatTime($chat['created_at']);
                    
                    // Logic to see if the current user needs to read this
                    $is_unread = ($chat['receiver_id'] == $user_id && $chat['is_read'] == 0);
                    $colors = ['#1d4ed8','#0f766e','#b45309','#7c3aed','#be185d'];
                    $color = $colors[$chat['message_id'] % count($colors)];
                ?>
                
                <div class="chat-row px-4 py-4 cursor-pointer relative" onclick="window.location.href='chat.php?listing_id=<?php echo $chat['listing_id']; ?>&user=<?php echo ($chat['sender_id'] == $user_id) ? $chat['receiver_id'] : $chat['sender_id']; ?>'">
                    <div class="flex items-center gap-4">
                        
                        <div class="relative shrink-0">
                            <div class="w-14 h-14 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-lg border border-white/10" style="background: <?php echo $color; ?>">
                                <?php echo getInitials($other_name); ?>
                            </div>
                            <?php if ($is_unread): ?>
                                <span class="absolute top-0 right-0 w-4 h-4 bg-emerald-500 border-2 border-[#0A0A0C] rounded-full"></span>
                            <?php endif; ?>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-baseline mb-1">
                                <h3 class="text-base font-bold text-white truncate pr-2 <?php echo $is_unread ? 'text-emerald-400' : ''; ?>">
                                    <?php echo explode(' ', $other_name)[0]; ?>
                                </h3>
                                <span class="text-[10px] font-bold tracking-wider text-gray-500 shrink-0"><?php echo $time; ?></span>
                            </div>
                            
                            <p class="text-xs font-semibold text-gray-400 truncate mb-1 bg-white/5 inline-block px-2 py-0.5 rounded border border-white/5">
                                📦 <?php echo $item_title; ?>
                            </p>
                            
                            <p class="text-sm truncate <?php echo $is_unread ? 'text-white font-semibold' : 'text-gray-400'; ?>">
                                <?php 
                                    if ($chat['sender_id'] == $user_id) echo "You: ";
                                    echo $msg_text; 
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>
            </div>
            
        <?php endif; ?>
    </main>

    <div class="fixed bottom-0 w-full z-50 glass-nav pb-safe pt-2">
        <div class="max-w-md mx-auto flex justify-between items-center h-16 px-6">
            <a href="index.php" class="flex flex-col items-center gap-1 text-gray-500 hover:text-white transition-colors"><span class="text-xl">🏪</span></a>
            <a href="#" class="flex flex-col items-center gap-1 text-gray-500 hover:text-white transition-colors"><span class="text-xl">📚</span></a>
            
            <a href="Notesing.php" class="relative -top-6">
                <div class="w-14 h-14 rounded-full action-fab flex items-center justify-center text-black text-2xl shadow-[0_0_20px_rgba(255,255,255,0.2)]">
                    <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                </div>
            </a>
            
            <a href="inbox.php" class="flex flex-col items-center gap-1 text-white"><span class="text-xl">💬</span></a>
            
            <a href="payout.php" class="flex flex-col items-center gap-1 text-gray-500 hover:text-white transition-colors"><span class="text-xl">👤</span></a>
        </div>
    </div>

</body>
</html>