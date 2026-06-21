<?php
// MILELE - Premium Split-Screen Inbox

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';
$my_id = $_SESSION['user_id'];
$active_user_id = filter_input(INPUT_GET, 'user', FILTER_VALIDATE_INT);
$listing_context = filter_input(INPUT_GET, 'listing', FILTER_VALIDATE_INT);

// ==========================================
// 🛠️ SILENT DATABASE UPGRADE
// ==========================================
// Automatically creates the messages table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        message_id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        listing_id INT DEFAULT NULL,
        message_text TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// ==========================================
// 📨 SEND MESSAGE LOGIC
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = filter_input(INPUT_POST, 'receiver_id', FILTER_VALIDATE_INT);
    $msg_text = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS));
    $list_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);

    if ($receiver_id && !empty($msg_text)) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, listing_id, message_text, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$my_id, $receiver_id, $list_id ? $list_id : null, $msg_text]);
        
        // Refresh to prevent double-posting on reload
        header("Location: inbox.php?user=$receiver_id" . ($list_id ? "&listing=$list_id" : ""));
        exit();
    }
}

// ==========================================
// 👁️ MARK MESSAGES AS READ
// ==========================================
if ($active_user_id) {
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")->execute([$active_user_id, $my_id]);
}

// ==========================================
// 🗂️ FETCH CONVERSATIONS (SIDEBAR)
// ==========================================
// Gets everyone you've messaged, their last message, and unread count
$stmt_convos = $pdo->prepare("
    SELECT u.user_id, u.full_name,
        (SELECT message_text FROM messages WHERE (sender_id = u.user_id AND receiver_id = :my_id) OR (sender_id = :my_id AND receiver_id = u.user_id) ORDER BY created_at DESC LIMIT 1) as last_msg,
        (SELECT COUNT(*) FROM messages WHERE sender_id = u.user_id AND receiver_id = :my_id AND is_read = 0) as unread
    FROM users u
    WHERE u.user_id IN (
        SELECT sender_id FROM messages WHERE receiver_id = :my_id
        UNION
        SELECT receiver_id FROM messages WHERE sender_id = :my_id
    )
    ORDER BY (SELECT MAX(created_at) FROM messages WHERE (sender_id = u.user_id AND receiver_id = :my_id) OR (sender_id = :my_id AND receiver_id = u.user_id)) DESC
");
$stmt_convos->execute([':my_id' => $my_id]);
$conversations = $stmt_convos->fetchAll();

// ==========================================
// 💬 FETCH ACTIVE CHAT (MAIN WINDOW)
// ==========================================
$chat_history = [];
$active_user_name = "Select a conversation";

if ($active_user_id) {
    // Get the name of the person we are chatting with
    $stmt_name = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmt_name->execute([$active_user_id]);
    $active_user_name = $stmt_name->fetchColumn() ?: "Unknown User";

    // Get the chat history
    $stmt_chat = $pdo->prepare("
        SELECT * FROM messages 
        WHERE (sender_id = :me AND receiver_id = :them) 
           OR (sender_id = :them AND receiver_id = :me)
        ORDER BY created_at ASC
    ");
    $stmt_chat->execute([':me' => $my_id, ':them' => $active_user_id]);
    $chat_history = $stmt_chat->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox | MILELE</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        
        /* Navigation */
        .nav-bar { display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(5,5,5,0.8); z-index: 100; flex-shrink: 0;}
        .brand { font-size: 1.5rem; font-weight: 800; color: #2DD4BF; text-decoration: none; letter-spacing: -1px;}
        .btn-glass { padding: 8px 16px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; transition: 0.3s; font-size: 0.9rem;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }

        /* Inbox Layout */
        .inbox-container { display: flex; flex-grow: 1; overflow: hidden; }
        
        /* Sidebar (Conversations) */
        .sidebar { width: 350px; background: rgba(255,255,255,0.02); border-right: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; flex-shrink: 0;}
        .sidebar-header { padding: 20px; font-size: 1.2rem; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.05); color: #fff;}
        .convo-list { overflow-y: auto; flex-grow: 1; }
        
        .convo-item { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.02); display: flex; align-items: center; gap: 15px; cursor: pointer; text-decoration: none; color: #fff; transition: 0.2s;}
        .convo-item:hover, .convo-item.active { background: rgba(45,212,191,0.05); border-left: 4px solid #2DD4BF; }
        .avatar { width: 45px; height: 45px; border-radius: 50%; background: #111; border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; font-weight: bold; color: #2DD4BF; flex-shrink: 0;}
        .convo-details { flex-grow: 1; overflow: hidden; }
        .convo-name { font-weight: bold; margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center;}
        .convo-preview { font-size: 0.85rem; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .unread-badge { background: #EF4444; color: #fff; font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; font-weight: bold; }

        /* Chat Window */
        .chat-area { flex-grow: 1; display: flex; flex-direction: column; background: radial-gradient(circle at center, rgba(45,212,191,0.03), transparent 70%);}
        .chat-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 15px; background: rgba(0,0,0,0.5); backdrop-filter: blur(10px);}
        .chat-header-name { font-size: 1.2rem; font-weight: bold; color: #fff;}
        
        .messages-box { flex-grow: 1; padding: 30px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; }
        
        /* Chat Bubbles */
        .bubble { max-width: 70%; padding: 12px 18px; border-radius: 20px; font-size: 0.95rem; line-height: 1.5; position: relative;}
        .bubble-received { background: rgba(255,255,255,0.08); color: #fff; align-self: flex-start; border-bottom-left-radius: 5px; }
        .bubble-sent { background: #2DD4BF; color: #000; align-self: flex-end; border-bottom-right-radius: 5px; box-shadow: 0 4px 15px rgba(45,212,191,0.2);}
        .bubble-time { font-size: 0.7rem; opacity: 0.6; margin-top: 5px; display: block; text-align: right;}

        /* Input Area */
        .input-area { padding: 20px; background: rgba(0,0,0,0.8); border-top: 1px solid rgba(255,255,255,0.05); display: flex; gap: 15px; align-items: flex-end;}
        .chat-input { flex-grow: 1; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; color: #fff; padding: 15px 20px; font-size: 1rem; outline: none; resize: none; font-family: inherit; transition: 0.3s; max-height: 100px; overflow-y: auto;}
        .chat-input:focus { border-color: #2DD4BF; background: rgba(255,255,255,0.08);}
        .btn-send { padding: 15px 30px; background: #2DD4BF; color: #000; border: none; border-radius: 20px; font-weight: bold; font-size: 1rem; cursor: pointer; transition: 0.2s; height: 50px;}
        .btn-send:hover { background: #fff; transform: translateY(-2px);}

        /* Empty States */
        .empty-chat { display: flex; flex-direction: column; align-items: center; justify-content: center; flex-grow: 1; color: #666; }
        .empty-icon { font-size: 4rem; margin-bottom: 20px; opacity: 0.5;}

        /* Responsive Mobile Layout */
        @media (max-width: 768px) {
            .inbox-container { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid rgba(255,255,255,0.05); max-height: 35vh; <?php echo $active_user_id ? 'display: none;' : ''; ?> }
            .chat-area { <?php echo !$active_user_id ? 'display: none;' : ''; ?> height: 65vh;}
            .mobile-back { display: <?php echo $active_user_id ? 'block' : 'none'; ?>; margin-right: 15px; color: #2DD4BF; text-decoration: none; font-size: 1.5rem;}
        }
        @media (min-width: 769px) { .mobile-back { display: none; } }
    </style>
</head>
<body>

<nav class="nav-bar">
    <a href="index.php" class="brand">MILELE</a>
    <a href="index.php" class="btn-glass">← Market Feed</a>
</nav>

<div class="inbox-container">
    
    <div class="sidebar">
        <div class="sidebar-header">Messages</div>
        <div class="convo-list">
            <?php if(empty($conversations)): ?>
                <div style="padding: 30px; text-align: center; color: #666; font-size: 0.9rem;">No active conversations. Reach out to a seller to start chatting!</div>
            <?php else: ?>
                <?php foreach($conversations as $convo): ?>
                    <a href="inbox.php?user=<?php echo $convo['user_id']; ?>" class="convo-item <?php echo ($active_user_id == $convo['user_id']) ? 'active' : ''; ?>">
                        <div class="avatar"><?php echo strtoupper(substr($convo['full_name'], 0, 1)); ?></div>
                        <div class="convo-details">
                            <div class="convo-name">
                                <?php echo htmlspecialchars($convo['full_name']); ?>
                                <?php if($convo['unread'] > 0): ?>
                                    <span class="unread-badge"><?php echo $convo['unread']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="convo-preview"><?php echo htmlspecialchars($convo['last_msg']); ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="chat-area">
        <?php if(!$active_user_id): ?>
            <div class="empty-chat">
                <div class="empty-icon">💬</div>
                <h2>Your Inbox</h2>
                <p>Select a conversation from the left to start messaging.</p>
            </div>
        <?php else: ?>
            <div class="chat-header">
                <a href="inbox.php" class="mobile-back">←</a>
                <div class="avatar"><?php echo strtoupper(substr($active_user_name, 0, 1)); ?></div>
                <div class="chat-header-name"><?php echo htmlspecialchars($active_user_name); ?></div>
            </div>

            <div class="messages-box" id="chatBox">
                <?php if(empty($chat_history)): ?>
                    <div style="text-align: center; color: #666; margin-top: 20px;">This is the beginning of your conversation. Stay safe and never share your PIN until the physical handover!</div>
                <?php else: ?>
                    <?php foreach($chat_history as $msg): 
                        $is_me = ($msg['sender_id'] == $my_id);
                    ?>
                        <div class="bubble <?php echo $is_me ? 'bubble-sent' : 'bubble-received'; ?>">
                            <?php echo nl2br(htmlspecialchars($msg['message_text'])); ?>
                            <span class="bubble-time"><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <form action="inbox.php" method="POST" class="input-area">
                <input type="hidden" name="receiver_id" value="<?php echo $active_user_id; ?>">
                <?php if($listing_context): ?>
                    <input type="hidden" name="listing_id" value="<?php echo htmlspecialchars($listing_context); ?>">
                <?php endif; ?>
                
                <textarea name="message" class="chat-input" placeholder="Type a message..." required id="msgInput" rows="1" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>
                <button type="submit" class="btn-send">Send</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    // Automatically scroll chat to the bottom so users see the newest messages instantly
    const chatBox = document.getElementById('chatBox');
    if(chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    // Submit form on Enter key (Shift+Enter for new line)
    const msgInput = document.getElementById('msgInput');
    if(msgInput) {
        msgInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
    }
</script>

</body>
</html>