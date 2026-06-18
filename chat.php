<?php
// MILELE - 1-on-1 Campus Chat Room

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$listing_id = filter_input(INPUT_GET, 'listing_id', FILTER_VALIDATE_INT);
$other_user_id = filter_input(INPUT_GET, 'user', FILTER_VALIDATE_INT);

// Bouncer checks
if (!$listing_id || !$other_user_id || $user_id == $other_user_id) {
    header("Location: inbox.php");
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

// 1. Mark incoming messages as read
$update_read = "UPDATE messages SET is_read = 1 WHERE listing_id = :lid AND sender_id = :oid AND receiver_id = :uid";
$pdo->prepare($update_read)->execute([':lid' => $listing_id, ':oid' => $other_user_id, ':uid' => $user_id]);

// 2. Fetch the Listing context
$stmt_listing = $pdo->prepare("SELECT title, price, file_path FROM listings WHERE listing_id = :lid");
$stmt_listing->execute([':lid' => $listing_id]);
$listing = $stmt_listing->fetch();

// 3. Fetch the other User's details
$stmt_user = $pdo->prepare("SELECT full_name FROM users WHERE user_id = :oid");
$stmt_user->execute([':oid' => $other_user_id]);
$other_user = $stmt_user->fetch();

if (!$listing || !$other_user) {
    header("Location: inbox.php");
    exit();
}

// 4. Fetch the chat history for this specific item between these two users
$sql_msgs = "SELECT * FROM messages 
             WHERE listing_id = :lid 
             AND ((sender_id = :uid AND receiver_id = :oid) OR (sender_id = :oid AND receiver_id = :uid))
             ORDER BY created_at ASC";
$stmt_msgs = $pdo->prepare($sql_msgs);
$stmt_msgs->execute([':lid' => $listing_id, ':uid' => $user_id, ':oid' => $other_user_id]);
$messages = $stmt_msgs->fetchAll();

$other_first_name = explode(' ', $other_user['full_name'])[0];
$img = $listing['file_path'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=200&q=75';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chat with <?php echo htmlspecialchars($other_first_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0A0A0C; color: #F3F4F6; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        .glass-nav { background: rgba(10, 10, 12, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-input-area { background: rgba(10, 10, 12, 0.95); backdrop-filter: blur(24px); border-top: 1px solid rgba(255, 255, 255, 0.05); }
        .msg-bubble { max-width: 80%; word-wrap: break-word; }
        .msg-sent { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-bottom-right-radius: 4px; }
        .msg-received { background: rgba(255, 255, 255, 0.1); color: white; border-bottom-left-radius: 4px; border: 1px solid rgba(255, 255, 255, 0.05); }
        
        /* Hide scrollbar for a cleaner look */
        #chat-container::-webkit-scrollbar { display: none; }
        #chat-container { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="antialiased">

    <div class="glass-nav shrink-0 z-50">
        <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="inbox.php" class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-white hover:bg-white/10 transition-colors">←</a>
            <div class="text-center">
                <span class="font-bold text-sm text-white block"><?php echo htmlspecialchars($other_user['full_name']); ?></span>
                <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest">Active</span>
            </div>
            <div class="w-10"></div>
        </div>
        
        <div class="bg-white/5 border-b border-white/5 px-4 py-2 flex items-center gap-3 cursor-pointer hover:bg-white/10 transition" onclick="window.location.href='item.php?id=<?php echo $listing_id; ?>'">
            <img src="<?php echo htmlspecialchars($img); ?>" class="w-10 h-10 rounded-lg object-cover bg-neutral-900 border border-white/10">
            <div>
                <p class="text-xs font-bold text-white line-clamp-1"><?php echo htmlspecialchars($listing['title']); ?></p>
                <p class="text-[11px] font-semibold text-emerald-400">KES <?php echo number_format($listing['price']); ?></p>
            </div>
        </div>
    </div>

    <main id="chat-container" class="flex-1 overflow-y-auto px-4 py-6 w-full max-w-3xl mx-auto flex flex-col space-y-4">
        
        <div class="text-center mb-4">
            <span class="bg-white/5 text-gray-500 text-[10px] font-bold uppercase tracking-widest px-3 py-1 rounded-full border border-white/5">
                Secure Chat Started
            </span>
        </div>

        <?php if (empty($messages)): ?>
            <div class="text-center text-xs text-gray-500 mt-10">
                Send a message to <?php echo $other_first_name; ?> to negotiate or arrange a meetup!
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg): 
                $is_me = ($msg['sender_id'] == $user_id);
            ?>
                <div class="flex <?php echo $is_me ? 'justify-end' : 'justify-start'; ?>">
                    <div class="msg-bubble px-4 py-3 rounded-2xl text-sm <?php echo $is_me ? 'msg-sent' : 'msg-received'; ?>">
                        <?php echo nl2br(htmlspecialchars($msg['message_text'])); ?>
                        <div class="text-[9px] mt-1 opacity-70 text-right">
                            <?php echo date('h:i A', strtotime($msg['created_at'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="h-4"></div>
    </main>

    <div class="glass-input-area shrink-0 pb-safe">
        <form action="process_message.php" method="POST" class="max-w-3xl mx-auto px-4 py-3 flex items-end gap-2">
            <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">
            <input type="hidden" name="receiver_id" value="<?php echo $other_user_id; ?>">
            
            <textarea name="message_text" required placeholder="Type a message..." rows="1"
                      class="flex-1 bg-white/5 border border-white/10 rounded-2xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-emerald-500/50 resize-none overflow-hidden" 
                      oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'" style="max-height: 120px;"></textarea>
            
            <button type="submit" class="w-12 h-12 rounded-full bg-white text-black flex items-center justify-center shrink-0 hover:scale-105 transition-transform active:scale-95 shadow-[0_0_20px_rgba(255,255,255,0.2)]">
                <svg class="w-5 h-5 ml-1" fill="currentColor" viewBox="0 0 20 20"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"></path></svg>
            </button>
        </form>
    </div>

    <script>
        const chatContainer = document.getElementById('chat-container');
        chatContainer.scrollTop = chatContainer.scrollHeight;
    </script>
</body>
</html>