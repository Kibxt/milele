<?php
// MILELE - Premium Messaging Terminal

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$active_chat_user = filter_input(INPUT_GET, 'user', FILTER_VALIDATE_INT);

try {
    // 1. Fetch all unique users this person has chatted with
    $stmt_contacts = $pdo->prepare("
        SELECT DISTINCT u.user_id, u.full_name 
        FROM users u
        JOIN messages m ON (u.user_id = m.sender_id OR u.user_id = m.receiver_id)
        WHERE (m.sender_id = :my_id OR m.receiver_id = :my_id) AND u.user_id != :my_id
    ");
    $stmt_contacts->execute([':my_id' => $current_user_id]);
    $contacts = $stmt_contacts->fetchAll();

    // 2. If a specific chat is selected, fetch those messages
    $messages = [];
    $chat_partner_name = "Select a conversation";
    
    if ($active_chat_user) {
        // Get partner's name
        $stmt_name = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
        $stmt_name->execute([$active_chat_user]);
        $chat_partner_name = $stmt_name->fetchColumn() ?: "Unknown User";

        // Get message history
        $stmt_msgs = $pdo->prepare("
            SELECT * FROM messages 
            WHERE (sender_id = :me AND receiver_id = :them) 
               OR (sender_id = :them AND receiver_id = :me)
            ORDER BY created_at ASC
        ");
        $stmt_msgs->execute([':me' => $current_user_id, ':them' => $active_chat_user]);
        $messages = $stmt_msgs->fetchAll();
        
        // Mark as read
        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")->execute([$active_chat_user, $current_user_id]);
    }

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System error loading inbox.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox | MILELE</title>
    <style>
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; height: 100vh; box-sizing: border-box; display: flex; flex-direction: column; }
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-shrink: 0;}
        .nav-bar h1 { color: #fff; margin: 0; font-size: 2rem;}
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; transition: 0.3s; font-size: 0.9rem; }
        
        .chat-container { display: flex; gap: 20px; flex-grow: 1; min-height: 0; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; overflow: hidden; }
        
        /* Contacts Sidebar */
        .contacts-sidebar { width: 300px; background: rgba(0,0,0,0.3); border-right: 1px solid rgba(255,255,255,0.05); overflow-y: auto; display: flex; flex-direction: column;}
        .contact-item { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); text-decoration: none; color: #ccc; display: block; transition: 0.2s;}
        .contact-item:hover, .contact-active { background: rgba(45,212,191,0.1); color: #fff; border-left: 4px solid #2DD4BF; }
        .contact-name { font-weight: bold; font-size: 1.1rem; margin-bottom: 5px; }

        /* Active Chat Area */
        .chat-area { flex-grow: 1; display: flex; flex-direction: column; background: transparent; }
        .chat-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); font-weight: bold; font-size: 1.2rem; color: #2DD4BF; background: rgba(0,0,0,0.4);}
        
        .messages-window { flex-grow: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
        
        .msg-bubble { max-width: 70%; padding: 12px 18px; border-radius: 20px; font-size: 0.95rem; line-height: 1.4; position: relative;}
        .msg-mine { background: #2DD4BF; color: #000; align-self: flex-end; border-bottom-right-radius: 4px; }
        .msg-theirs { background: rgba(255,255,255,0.1); color: #fff; align-self: flex-start; border-bottom-left-radius: 4px; }
        .msg-time { font-size: 0.7rem; opacity: 0.6; margin-top: 5px; text-align: right; display: block;}

        .chat-input-area { padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.4); display: flex; gap: 10px;}
        .chat-input { flex-grow: 1; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; color: #fff; font-size: 1rem; outline: none; transition: 0.3s;}
        .chat-input:focus { border-color: #2DD4BF; }
        .btn-send { background: #2DD4BF; color: #000; border: none; padding: 0 25px; border-radius: 12px; font-weight: bold; cursor: pointer; transition: 0.2s;}
        .btn-send:hover { background: #fff; }

        .empty-state { flex-grow: 1; display: flex; justify-content: center; align-items: center; color: #666; font-size: 1.2rem; flex-direction: column; gap: 15px;}
        
        @media (max-width: 768px) {
            .contacts-sidebar { display: <?php echo $active_chat_user ? 'none' : 'flex'; ?>; width: 100%; }
            .chat-area { display: <?php echo $active_chat_user ? 'flex' : 'none'; ?>; width: 100%; }
        }
    </style>
</head>
<body>

    <div class="nav-bar">
        <h1>💬 Inbox</h1>
        <a href="index.php" class="btn-glass">← Back to Market</a>
    </div>

    <div class="chat-container">
        <div class="contacts-sidebar">
            <?php if (empty($contacts)): ?>
                <div style="padding: 20px; color: #666; text-align: center;">No messages yet.</div>
            <?php else: ?>
                <?php foreach ($contacts as $contact): ?>
                    <a href="inbox.php?user=<?php echo $contact['user_id']; ?>" class="contact-item <?php echo ($active_chat_user == $contact['user_id']) ? 'contact-active' : ''; ?>">
                        <div class="contact-name"><?php echo htmlspecialchars($contact['full_name']); ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-area">
            <?php if (!$active_chat_user): ?>
                <div class="empty-state">
                    <div style="font-size: 3rem;">💬</div>
                    Select a conversation to start chatting.
                </div>
            <?php else: ?>
                <div class="chat-header">
                    <?php echo htmlspecialchars($chat_partner_name); ?>
                    <a href="inbox.php" style="float:right; color:#888; text-decoration:none; font-size:0.9rem;" class="mobile-only-back">✖</a>
                </div>
                
                <div class="messages-window" id="msgWindow">
                    <?php foreach ($messages as $msg): ?>
                        <div class="msg-bubble <?php echo ($msg['sender_id'] == $current_user_id) ? 'msg-mine' : 'msg-theirs'; ?>">
                            <?php echo nl2br(htmlspecialchars($msg['message_text'])); ?>
                            <span class="msg-time"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form action="send_message.php" method="POST" class="chat-input-area">
                    <input type="hidden" name="receiver_id" value="<?php echo $active_chat_user; ?>">
                    <input type="text" name="message_text" class="chat-input" placeholder="Type a message..." required autocomplete="off">
                    <button type="submit" class="btn-send">Send</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom of chat
        const msgWindow = document.getElementById('msgWindow');
        if (msgWindow) {
            msgWindow.scrollTop = msgWindow.scrollHeight;
        }
    </script>
</body>
</html>