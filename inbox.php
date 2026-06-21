<?php
// MILELE - Premium Split-Screen Inbox (Diagnostic Engine Active)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';
$my_id = $_SESSION['user_id'];
$active_user_id = filter_input(INPUT_GET, 'user', FILTER_VALIDATE_INT);
$listing_context = filter_input(INPUT_GET, 'listing', FILTER_VALIDATE_INT);

// 🛠️ SILENT DATABASE UPGRADE
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
} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'><strong>Table Creation Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>");
}

// 📨 SEND MESSAGE LOGIC (Now wrapped in a diagnostic firewall)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = filter_input(INPUT_POST, 'receiver_id', FILTER_VALIDATE_INT);
    $msg_text = isset($_POST['message']) ? trim($_POST['message']) : ''; 
    $list_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);

    if ($receiver_id && !empty($msg_text)) {
        try {
            $clean_text = htmlspecialchars($msg_text, ENT_QUOTES, 'UTF-8');
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, listing_id, message_text, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$my_id, $receiver_id, $list_id ? $list_id : null, $clean_text]);
            
            header("Location: inbox.php?user=$receiver_id" . ($list_id ? "&listing=$list_id" : ""));
            exit();
        } catch (PDOException $e) {
            // UNMASKED: This will print the exact reason the send function crashed
            die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'><strong>Send Message Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>");
        }
    }
}

// 👁️ MARK MESSAGES AS READ
if ($active_user_id) {
    try {
        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")->execute([$active_user_id, $my_id]);
    } catch (PDOException $e) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'><strong>Update Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>");
    }
}

// 🗂️ FETCH CONVERSATIONS (SIDEBAR)
try {
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
} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'><strong>Sidebar Load Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>");
}

// 💬 FETCH ACTIVE CHAT
$chat_history = [];
$active_user_name = "Select a conversation";

if ($active_user_id) {
    try {
        $stmt_name = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
        $stmt_name->execute([$active_user_id]);
        $active_user_name = $stmt_name->fetchColumn() ?: "Unknown User";

        $stmt_chat = $pdo->prepare("
            SELECT * FROM messages 
            WHERE (sender_id = :me AND receiver_id = :them) 
               OR (sender_id = :them AND receiver_id = :me)
            ORDER BY created_at ASC
        ");
        $stmt_chat->execute([':me' => $my_id, ':them' => $active_user_id]);
        $chat_history = $stmt_chat->fetchAll();
    } catch (PDOException $e) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'><strong>Chat Load Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>");
    }
}

function formatChatMessage($text) {
    if (preg_match('/^https?:\/\/[^\s]+\.(gif|webp)(\?[^\s]*)?$/i', $text) || strpos($text, 'giphy.com/media') !== false || strpos($text, 'tenor.com') !== false) {
        return '<img src="'.$text.'" class="chat-gif" alt="GIF">';
    }
    return nl2br($text);
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
        
        .nav-bar { display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(5,5,5,0.8); z-index: 100; flex-shrink: 0;}
        .brand { font-size: 1.5rem; font-weight: 800; color: #2DD4BF; text-decoration: none; letter-spacing: -1px;}
        .btn-glass { padding: 8px 16px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; transition: 0.3s; font-size: 0.9rem;}
        .btn-glass:hover { background: rgba(255,255,255,0.1); }

        .inbox-container { display: flex; flex-grow: 1; overflow: hidden; }
        
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

        .chat-area { flex-grow: 1; display: flex; flex-direction: column; background: radial-gradient(circle at center, rgba(45,212,191,0.03), transparent 70%); position: relative;}
        .chat-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 15px; background: rgba(0,0,0,0.5); backdrop-filter: blur(10px);}
        .chat-header-name { font-size: 1.2rem; font-weight: bold; color: #fff; text-decoration: none;}
        .chat-header-name:hover { color: #2DD4BF; }
        
        .messages-box { flex-grow: 1; padding: 30px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; scroll-behavior: smooth;}
        
        .bubble { max-width: 70%; padding: 12px 18px; border-radius: 20px; font-size: 0.95rem; line-height: 1.5; position: relative;}
        .bubble-received { background: rgba(255,255,255,0.08); color: #fff; align-self: flex-start; border-bottom-left-radius: 5px; }
        .bubble-sent { background: #2DD4BF; color: #000; align-self: flex-end; border-bottom-right-radius: 5px; box-shadow: 0 4px 15px rgba(45,212,191,0.2);}
        .bubble-time { font-size: 0.7rem; opacity: 0.6; margin-top: 5px; display: block; text-align: right;}
        .chat-gif { max-width: 250px; border-radius: 12px; margin-top: 5px; display: block;}

        .input-wrapper { padding: 20px; background: rgba(0,0,0,0.8); border-top: 1px solid rgba(255,255,255,0.05); position: relative;}
        .input-bar { display: flex; align-items: flex-end; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 24px; padding: 5px 10px; transition: 0.3s;}
        .input-bar:focus-within { border-color: #2DD4BF; background: rgba(255,255,255,0.08); }
        
        .media-actions { display: flex; gap: 8px; padding: 10px 5px; }
        .media-btn { background: transparent; border: none; color: #888; font-size: 1.2rem; cursor: pointer; transition: 0.2s; border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;}
        .media-btn:hover { color: #2DD4BF; background: rgba(45,212,191,0.1); }
        
        .chat-input { flex-grow: 1; background: transparent; border: none; color: #fff; padding: 15px 10px; font-size: 1rem; outline: none; resize: none; font-family: inherit; max-height: 100px; overflow-y: auto;}
        
        .btn-send { padding: 0 20px; margin: 5px; height: 40px; background: #2DD4BF; color: #000; border: none; border-radius: 20px; font-weight: bold; cursor: pointer; transition: 0.2s;}
        .btn-send:hover { background: #fff; transform: translateY(-2px);}

        .media-panel { position: absolute; bottom: 85px; left: 20px; background: rgba(20,20,20,0.95); backdrop-filter: blur(15px); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; width: 300px; height: 350px; display: none; flex-direction: column; box-shadow: 0 10px 40px rgba(0,0,0,0.5); z-index: 50;}
        .media-panel.active { display: flex; animation: popUp 0.2s ease-out;}
        @keyframes popUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .panel-header { padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;}
        .panel-title { font-size: 0.85rem; font-weight: bold; color: #aaa; text-transform: uppercase; padding-left: 10px;}
        .close-panel { background: transparent; border: none; color: #888; cursor: pointer; font-size: 1.2rem;}
        .close-panel:hover { color: #fff; }

        .emoji-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; padding: 15px; overflow-y: auto;}
        .emoji-item { font-size: 1.5rem; cursor: pointer; text-align: center; transition: 0.2s; user-select: none;}
        .emoji-item:hover { transform: scale(1.3); }

        .gif-search { background: rgba(255,255,255,0.05); border: none; border-bottom: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 12px 15px; outline: none; width: 100%; box-sizing: border-box;}
        .gif-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; padding: 10px; overflow-y: auto; flex-grow: 1;}
        .gif-item { width: 100%; height: 100px; object-fit: cover; border-radius: 8px; cursor: pointer; border: 2px solid transparent;}
        .gif-item:hover { border-color: #2DD4BF; }

        .empty-chat { display: flex; flex-direction: column; align-items: center; justify-content: center; flex-grow: 1; color: #666; }

        @media (max-width: 768px) {
            .inbox-container { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid rgba(255,255,255,0.05); max-height: 35vh; <?php echo $active_user_id ? 'display: none;' : ''; ?> }
            .chat-area { <?php echo !$active_user_id ? 'display: none;' : ''; ?> height: 65vh;}
            .mobile-back { display: <?php echo $active_user_id ? 'block' : 'none'; ?>; margin-right: 15px; color: #2DD4BF; text-decoration: none; font-size: 1.5rem;}
            .media-panel { width: calc(100% - 40px); left: 20px; right: 20px; }
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
                            <div class="convo-preview">
                                <?php 
                                    $preview = htmlspecialchars($convo['last_msg']);
                                    if(strpos($preview, '.gif') !== false || strpos($preview, 'giphy.com') !== false) echo "🖼️ Sent a GIF";
                                    else echo $preview;
                                ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="chat-area">
        <?php if(!$active_user_id): ?>
            <div class="empty-chat">
                <div style="font-size: 4rem; opacity: 0.5;">💬</div>
                <h2>Your Inbox</h2>
                <p>Select a conversation from the left.</p>
            </div>
        <?php else: ?>
            <div class="chat-header">
                <a href="inbox.php" class="mobile-back">←</a>
                <a href="public_profile.php?id=<?php echo $active_user_id; ?>" title="View Profile" style="text-decoration: none;">
                    <div class="avatar" style="cursor:pointer; border-color: #2DD4BF; transition: 0.2s;" onmouseover="this.style.background='rgba(45,212,191,0.2)'" onmouseout="this.style.background='#111'"><?php echo strtoupper(substr($active_user_name, 0, 1)); ?></div>
                </a>
                <a href="public_profile.php?id=<?php echo $active_user_id; ?>" class="chat-header-name"><?php echo htmlspecialchars($active_user_name); ?></a>
                
                <a href="public_profile.php?id=<?php echo $active_user_id; ?>" class="btn-glass" style="margin-left: auto; font-size: 0.8rem; padding: 6px 12px;">View Profile</a>
            </div>

            <div class="messages-box" id="chatBox">
                <?php if(empty($chat_history)): ?>
                    <div style="text-align: center; color: #666; margin-top: 20px;">This is the beginning of your conversation.</div>
                <?php else: ?>
                    <?php foreach($chat_history as $msg): 
                        $is_me = ($msg['sender_id'] == $my_id);
                    ?>
                        <div class="bubble <?php echo $is_me ? 'bubble-sent' : 'bubble-received'; ?>">
                            <?php echo formatChatMessage($msg['message_text']); ?>
                            <span class="bubble-time"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="media-panel" id="emojiPanel">
                <div class="panel-header">
                    <span class="panel-title">Emojis</span>
                    <button type="button" class="close-panel" onclick="togglePanel('emojiPanel')">&times;</button>
                </div>
                <div class="emoji-grid" id="emojiGrid"></div>
            </div>

            <div class="media-panel" id="gifPanel">
                <div class="panel-header">
                    <span class="panel-title">GIFs</span>
                    <button type="button" class="close-panel" onclick="togglePanel('gifPanel')">&times;</button>
                </div>
                <input type="text" class="gif-search" placeholder="Search GIFs..." id="gifSearch">
                <div class="gif-grid" id="gifGrid"></div>
            </div>

            <div class="input-wrapper">
                <form action="inbox.php" method="POST" id="chatForm">
                    <input type="hidden" name="receiver_id" value="<?php echo $active_user_id; ?>">
                    <?php if($listing_context): ?>
                        <input type="hidden" name="listing_id" value="<?php echo htmlspecialchars($listing_context); ?>">
                    <?php endif; ?>
                    
                    <div class="input-bar">
                        <div class="media-actions">
                            <button type="button" class="media-btn" title="Emojis" onclick="togglePanel('emojiPanel')">😀</button>
                            <button type="button" class="media-btn" title="GIFs" onclick="togglePanel('gifPanel')">GIF</button>
                        </div>
                        <textarea name="message" class="chat-input" placeholder="Type a message..." required id="msgInput" rows="1"></textarea>
                        <button type="submit" class="btn-send">Send</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const chatBox = document.getElementById('chatBox');
    if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;

    const msgInput = document.getElementById('msgInput');
    if(msgInput) {
        msgInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('chatForm').submit();
            }
        });
    }

    function togglePanel(panelId) {
        document.querySelectorAll('.media-panel').forEach(p => {
            if(p.id !== panelId) p.classList.remove('active');
        });
        document.getElementById(panelId).classList.toggle('active');
    }

    const emojis = ['😀','😂','🥺','🔥','❤️','👍','✨','💀','😭','👀','💯','🙏','😊','😎','🤔','🙌','🎉','🚀','💸','🤝','❌','✅','📦','🎓'];
    const emojiGrid = document.getElementById('emojiGrid');
    if(emojiGrid) {
        emojis.forEach(emoji => {
            let el = document.createElement('div');
            el.className = 'emoji-item';
            el.innerText = emoji;
            el.onclick = () => {
                msgInput.value += emoji;
                msgInput.focus();
            };
            emojiGrid.appendChild(el);
        });
    }

    const defaultGifs = [
        "https://media.giphy.com/media/11ISwbgCxEzMyY/giphy.gif",
        "https://media.giphy.com/media/3o7TKSjRrfIPjeiVyM/giphy.gif",
        "https://media.giphy.com/media/l0HlOBZcl7mbV4HWE/giphy.gif",
        "https://media.giphy.com/media/26AHONQ79FdWZhAI0/giphy.gif",
        "https://media.giphy.com/media/3o6UB5RrlQuMfZp82Y/giphy.gif",
        "https://media.giphy.com/media/l41YkxvU8c7J7Bba0/giphy.gif"
    ];

    const gifGrid = document.getElementById('gifGrid');
    if(gifGrid) {
        defaultGifs.forEach(url => {
            let img = document.createElement('img');
            img.src = url;
            img.className = 'gif-item';
            img.onclick = () => {
                msgInput.value = url;
                document.getElementById('chatForm').submit();
            };
            gifGrid.appendChild(img);
        });
    }
</script>

</body>
</html>