<?php
// MILELE - Premium Split-Screen Inbox (With Profile Pictures & Light UI)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';
$my_id = $_SESSION['user_id'];
$active_user_id = filter_input(INPUT_GET, 'user', FILTER_VALIDATE_INT);
$listing_context = filter_input(INPUT_GET, 'listing', FILTER_VALIDATE_INT);

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

try { $pdo->exec("ALTER TABLE messages MODIFY listing_id INT NULL DEFAULT NULL"); } catch (PDOException $e) {}


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
            die("<div style='background:#1A1040; color:#FF6B6B; padding:50px; text-align:center; font-family:sans-serif;'><strong>Send Message Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>");
        }
    }
}

if ($active_user_id) {
    try {
        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")->execute([$active_user_id, $my_id]);
    } catch (PDOException $e) {}
}

// ==========================================
// 🗂️ FETCH CONVERSATIONS (Now with Profile Pics)
// ==========================================
try {
    // UPGRADED SQL: Pulls u.profile_picture
    $stmt_convos = $pdo->prepare("
        SELECT u.user_id, u.full_name, u.profile_picture,
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
    die("<div style='background:#1A1040; color:#FF6B6B; padding:50px; text-align:center; font-family:sans-serif;'><strong>Sidebar Load Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>");
}

// ==========================================
// 💬 FETCH ACTIVE CHAT (Now with Profile Pics)
// ==========================================
$chat_history = [];
$active_user_name = "Select a conversation";
$active_user_pic = null;

if ($active_user_id) {
    try {
        // UPGRADED SQL: Pulls profile_picture
        $stmt_name = $pdo->prepare("SELECT full_name, profile_picture FROM users WHERE user_id = ?");
        $stmt_name->execute([$active_user_id]);
        $active_user_data = $stmt_name->fetch();
        
        if($active_user_data) {
            $active_user_name = $active_user_data['full_name'];
            $active_user_pic = $active_user_data['profile_picture'];
        } else {
            $active_user_name = "Unknown User";
        }

        $stmt_chat = $pdo->prepare("
            SELECT * FROM messages 
            WHERE (sender_id = :me AND receiver_id = :them) 
               OR (sender_id = :them AND receiver_id = :me)
            ORDER BY created_at ASC
        ");
        $stmt_chat->execute([':me' => $my_id, ':them' => $active_user_id]);
        $chat_history = $stmt_chat->fetchAll();
    } catch (PDOException $e) {
        die("<div style='background:#1A1040; color:#FF6B6B; padding:50px; text-align:center; font-family:sans-serif;'><strong>Chat Load Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>");
    }
}

function formatChatMessage($text) {
    if (preg_match('/^https?:\/\/[^\s]+\.(gif|webp)(\?[^\s]*)?$/i', $text) || strpos($text, 'giphy.com/media') !== false || strpos($text, 'tenor.com') !== false) {
        return '<img src="'.$text.'" class="chat-gif" alt="GIF">';
    }
    return nl2br($text);
}

function get_initials($name) {
    $words = explode(' ', $name);
    if (count($words) >= 2) return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    return strtoupper(substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox | MILELE</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --indigo: #1A1040;
            --amber: #F5A623;
            --coral: #FF6B6B;
            --mint: #00D4AA;
            --chalk: #F7F5FF;
            --slate: #8B7FA8;
            --white: #ffffff;
            --card-border: rgba(26,16,64,0.10);
        }
        body { background: var(--chalk); color: var(--indigo); font-family: 'Inter', sans-serif; margin: 0; padding: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        
        /* Navigation */
        .nav-bar { display: flex; justify-content: space-between; align-items: center; padding: 0 5%; border-bottom: 1px solid var(--card-border); background: rgba(247,245,255,0.94); backdrop-filter: blur(14px); z-index: 100; flex-shrink: 0; height: 70px;}
        .brand { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; color: var(--indigo); text-decoration: none; display: flex; align-items: center; gap: 8px;}
        .logo-dot { width: 10px; height: 10px; background: var(--amber); border-radius: 50%; display: inline-block; }
        .nav-actions { display: flex; gap: 12px; align-items: center;}
        .btn-glass { padding: 9px 20px; background: transparent; color: var(--indigo); text-decoration: none; border: 1.5px solid var(--indigo); border-radius: 50px; font-weight: 700; font-size: 13px; transition: 0.2s;}
        .btn-glass:hover { background: var(--indigo); color: var(--white); }

        .inbox-container { display: flex; flex-grow: 1; overflow: hidden; }
        
        /* Sidebar (Conversation List) */
        .sidebar { width: 350px; background: var(--white); border-right: 1px solid var(--card-border); display: flex; flex-direction: column; flex-shrink: 0;}
        .sidebar-header { padding: 24px 20px; font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; border-bottom: 1px solid var(--card-border); color: var(--indigo);}
        .convo-list { overflow-y: auto; flex-grow: 1; }
        
        .convo-item { padding: 20px; border-bottom: 1px solid var(--card-border); display: flex; align-items: center; gap: 15px; cursor: pointer; text-decoration: none; color: var(--indigo); transition: 0.2s; border-left: 4px solid transparent;}
        .convo-item:hover { background: var(--chalk); }
        .convo-item.active { background: var(--chalk); border-left: 4px solid var(--amber); }
        
        .avatar { width: 48px; height: 48px; border-radius: 50%; background: var(--indigo); border: 2px solid var(--white); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: var(--white); flex-shrink: 0; object-fit: cover; box-shadow: 0 4px 10px rgba(26,16,64,0.1);}
        
        .convo-details { flex-grow: 1; overflow: hidden; }
        .convo-name { font-weight: 700; font-size: 15px; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: center; color: var(--indigo);}
        .convo-preview { font-size: 13px; color: var(--slate); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500;}
        .unread-badge { background: var(--coral); color: var(--white); font-size: 10px; padding: 3px 8px; border-radius: 50px; font-weight: 800; }

        /* Chat Area */
        .chat-area { flex-grow: 1; display: flex; flex-direction: column; background: var(--chalk); position: relative;}
        
        .chat-header { padding: 20px 30px; border-bottom: 1px solid var(--card-border); display: flex; align-items: center; gap: 15px; background: rgba(255,255,255,0.9); backdrop-filter: blur(10px);}
        .chat-header-name { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 800; color: var(--indigo); text-decoration: none; transition: 0.2s;}
        .chat-header-name:hover { color: var(--amber); }
        
        .messages-box { flex-grow: 1; padding: 30px; overflow-y: auto; display: flex; flex-direction: column; gap: 16px; scroll-behavior: smooth;}
        
        .bubble { max-width: 70%; padding: 14px 20px; border-radius: 20px; font-size: 15px; line-height: 1.5; position: relative;}
        .bubble-received { background: var(--white); color: var(--indigo); align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid var(--card-border); box-shadow: 0 4px 12px rgba(26,16,64,0.03);}
        .bubble-sent { background: var(--indigo); color: var(--white); align-self: flex-end; border-bottom-right-radius: 4px; box-shadow: 0 6px 16px rgba(26,16,64,0.1);}
        .bubble-time { font-size: 11px; opacity: 0.7; margin-top: 6px; display: block; text-align: right; font-weight: 500;}
        .chat-gif { max-width: 250px; border-radius: 12px; margin-top: 5px; display: block;}

        /* Input Area */
        .input-wrapper { padding: 24px 30px; background: var(--white); border-top: 1px solid var(--card-border); position: relative;}
        .input-bar { display: flex; align-items: flex-end; background: var(--chalk); border: 2px solid var(--card-border); border-radius: 24px; padding: 6px 12px; transition: 0.3s;}
        .input-bar:focus-within { border-color: var(--amber); box-shadow: 0 0 0 4px rgba(245,166,35,0.1); background: var(--white);}
        
        .media-actions { display: flex; gap: 8px; padding: 10px 5px; }
        .media-btn { background: transparent; border: none; color: var(--slate); font-size: 20px; cursor: pointer; transition: 0.2s; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;}
        .media-btn:hover { color: var(--indigo); background: rgba(26,16,64,0.05); }
        
        .chat-input { flex-grow: 1; background: transparent; border: none; color: var(--indigo); padding: 16px 12px; font-size: 15px; outline: none; resize: none; font-family: 'Inter', sans-serif; max-height: 120px; overflow-y: auto; font-weight: 500;}
        .chat-input::placeholder { color: var(--slate); }
        
        .btn-send { padding: 0 24px; margin: 6px; height: 44px; background: var(--amber); color: var(--indigo); border: none; border-radius: 50px; font-weight: 800; cursor: pointer; transition: 0.2s; font-family: 'Inter', sans-serif;}
        .btn-send:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(245,166,35,0.3);}

        /* Media Panels (Emojis & GIFs) */
        .media-panel { position: absolute; bottom: 100px; left: 30px; background: var(--white); border: 1px solid var(--card-border); border-radius: 20px; width: 320px; height: 380px; display: none; flex-direction: column; box-shadow: 0 16px 40px rgba(26,16,64,0.12); z-index: 50; overflow: hidden;}
        .media-panel.active { display: flex; animation: popUp 0.2s ease-out;}
        @keyframes popUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--card-border); display: flex; justify-content: space-between; align-items: center; background: var(--chalk);}
        .panel-title { font-size: 12px; font-weight: 800; color: var(--slate); text-transform: uppercase; letter-spacing: 0.05em;}
        .close-panel { background: transparent; border: none; color: var(--slate); cursor: pointer; font-size: 20px; font-weight: bold; transition: 0.2s;}
        .close-panel:hover { color: var(--coral); }

        .emoji-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; padding: 20px; overflow-y: auto;}
        .emoji-item { font-size: 24px; cursor: pointer; text-align: center; transition: 0.2s; user-select: none;}
        .emoji-item:hover { transform: scale(1.3); }

        .gif-search { background: var(--white); border: none; border-bottom: 1px solid var(--card-border); color: var(--indigo); padding: 16px 20px; outline: none; width: 100%; box-sizing: border-box; font-family: 'Inter', sans-serif; font-size: 14px;}
        .gif-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 12px; overflow-y: auto; flex-grow: 1; background: var(--chalk);}
        .gif-item { width: 100%; height: 110px; object-fit: cover; border-radius: 12px; cursor: pointer; border: 2px solid transparent; transition: 0.2s;}
        .gif-item:hover { border-color: var(--amber); transform: scale(1.02);}

        /* Empty States */
        .empty-chat { display: flex; flex-direction: column; align-items: center; justify-content: center; flex-grow: 1; color: var(--slate); text-align: center;}
        .empty-chat-icon { font-size: 64px; margin-bottom: 16px; opacity: 0.5;}
        .empty-chat h2 { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; color: var(--indigo); margin-bottom: 8px;}
        .empty-chat p { font-size: 15px; font-weight: 500;}

        @media (max-width: 768px) {
            .inbox-container { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid var(--card-border); max-height: 35vh; <?php echo $active_user_id ? 'display: none;' : ''; ?> }
            .chat-area { <?php echo !$active_user_id ? 'display: none;' : ''; ?> height: 65vh;}
            .mobile-back { display: <?php echo $active_user_id ? 'block' : 'none'; ?>; margin-right: 15px; color: var(--indigo); text-decoration: none; font-size: 24px; font-weight: bold;}
            .media-panel { width: calc(100% - 40px); left: 20px; right: 20px; }
            .input-wrapper { padding: 16px 20px; }
        }
        @media (min-width: 769px) { .mobile-back { display: none; } }
    </style>
</head>
<body>

<nav class="nav-bar">
    <a href="index.php" class="nav-logo"><span class="logo-dot"></span>MILELE</a>
    <div class="nav-actions">
        <a href="profile.php" class="btn-ghost" style="border: none; color: var(--slate);">Dashboard</a>
        <a href="index.php" class="btn-ghost">← Market Feed</a>
    </div>
</nav>

<div class="inbox-container">
    
    <div class="sidebar">
        <div class="sidebar-header">Messages</div>
        <div class="convo-list">
            <?php if(empty($conversations)): ?>
                <div style="padding: 40px 30px; text-align: center; color: var(--slate); font-size: 14px; font-weight: 500; line-height: 1.6;">No active conversations. Reach out to a seller to start chatting!</div>
            <?php else: ?>
                <?php foreach($conversations as $convo): ?>
                    <a href="inbox.php?user=<?php echo $convo['user_id']; ?>" class="convo-item <?php echo ($active_user_id == $convo['user_id']) ? 'active' : ''; ?>">
                        
                        <?php if(!empty($convo['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($convo['profile_picture']); ?>" class="avatar" alt="Avatar">
                        <?php else: ?>
                            <div class="avatar"><?php echo get_initials($convo['full_name']); ?></div>
                        <?php endif; ?>

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
                <div class="empty-chat-icon">💬</div>
                <h2>Your Inbox</h2>
                <p>Select a conversation from the sidebar to start messaging.</p>
            </div>
        <?php else: ?>
            <div class="chat-header">
                <a href="inbox.php" class="mobile-back">←</a>
                <a href="public_profile.php?id=<?php echo $active_user_id; ?>" title="View Profile" style="text-decoration: none;">
                    <?php if($active_user_pic): ?>
                        <img src="<?php echo htmlspecialchars($active_user_pic); ?>" class="avatar" style="transition: 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'" alt="Avatar">
                    <?php else: ?>
                        <div class="avatar" style="transition: 0.2s;" onmouseover="this.style.background='var(--indigo-mid)'" onmouseout="this.style.background='var(--indigo)'"><?php echo get_initials($active_user_name); ?></div>
                    <?php endif; ?>
                </a>
                <a href="public_profile.php?id=<?php echo $active_user_id; ?>" class="chat-header-name"><?php echo htmlspecialchars($active_user_name); ?></a>
                
                <a href="public_profile.php?id=<?php echo $active_user_id; ?>" class="btn-ghost" style="margin-left: auto; font-size: 11px; padding: 6px 14px; text-transform: uppercase; letter-spacing: 0.05em;">View Profile</a>
            </div>

            <div class="messages-box" id="chatBox">
                <?php if(empty($chat_history)): ?>
                    <div style="text-align: center; color: var(--slate); font-weight: 500; font-size: 14px; margin-top: 30px; background: var(--white); padding: 12px 20px; border-radius: 50px; align-self: center; border: 1px solid var(--card-border);">This is the beginning of your conversation.</div>
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
                            <button type="button" class="media-btn" title="GIFs" onclick="togglePanel('gifPanel')" style="font-weight: 800; font-size: 12px; font-family: 'Syne', sans-serif;">GIF</button>
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
    // Keep scroll at bottom of chat
    const chatBox = document.getElementById('chatBox');
    if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;

    // Shift+Enter for new line, Enter to send
    const msgInput = document.getElementById('msgInput');
    if(msgInput) {
        msgInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.value.trim() !== '') {
                    document.getElementById('chatForm').submit();
                }
            }
        });
    }

    // Toggle Media Panels
    function togglePanel(panelId) {
        document.querySelectorAll('.media-panel').forEach(p => {
            if(p.id !== panelId) p.classList.remove('active');
        });
        document.getElementById(panelId).classList.toggle('active');
    }

    // Populate Emojis
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

    // Populate Default GIFs
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