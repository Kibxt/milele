<?php
// MILELE - Premium Chat Interface

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$current_user = $_SESSION['user_id'];
$other_user = filter_input(INPUT_GET, 'seller', FILTER_VALIDATE_INT); // "seller" acts as the generic ID for the other person
$listing_id = filter_input(INPUT_GET, 'item', FILTER_VALIDATE_INT);

if (!$other_user || !$listing_id) {
    header("Location: inbox.php");
    exit();
}

try {
    // Fetch details of the person you are talking to
    $stmt_user = $pdo->prepare("SELECT full_name, university_name FROM users WHERE user_id = :id");
    $stmt_user->execute([':id' => $other_user]);
    $chat_partner = $stmt_user->fetch();

    // Fetch the item you are talking about
    $stmt_item = $pdo->prepare("SELECT title, price FROM listings WHERE listing_id = :id");
    $stmt_item->execute([':id' => $listing_id]);
    $item = $stmt_item->fetch();

    if (!$chat_partner || !$item) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>Chat details not found.</div>");
    }

    // Mark incoming messages as read
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = :me AND sender_id = :them AND listing_id = :item")
        ->execute([':me' => $current_user, ':them' => $other_user, ':item' => $listing_id]);

    // Fetch chat history
    $stmt_chat = $pdo->prepare("
        SELECT * FROM messages 
        WHERE listing_id = :item 
        AND ((sender_id = :me AND receiver_id = :them) OR (sender_id = :them AND receiver_id = :me))
        ORDER BY created_at ASC
    ");
    $stmt_chat->execute([':item' => $listing_id, ':me' => $current_user, ':them' => $other_user]);
    $messages = $stmt_chat->fetchAll();

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System error loading chat.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat | MILELE</title>
    <style>
        /* Premium Chat Aesthetic */
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; display: flex; flex-direction: column; height: 100vh; }
        
        /* Header */
        .chat-header { background: rgba(255,255,255,0.02); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .btn-back { color: #888; text-decoration: none; font-size: 1.2rem; margin-right: 15px; transition: 0.2s; }
        .btn-back:hover { color: #fff; }
        
        .header-info { display: flex; align-items: center; gap: 15px; flex-grow: 1; }
        .avatar { width: 45px; height: 45px; background: rgba(45,212,191,0.1); color: #2DD4BF; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 1.2rem; border: 1px solid rgba(45,212,191,0.2); }
        .partner-name { font-weight: bold; font-size: 1.1rem; margin: 0 0 3px 0; }
        .item-context { color: #888; font-size: 0.85rem; }
        
        .btn-buy { background: #2DD4BF; color: #000; padding: 8px 16px; border-radius: 12px; font-weight: bold; text-decoration: none; font-size: 0.9rem; transition: 0.2s; }
        .btn-buy:hover { background: #fff; }

        /* Chat Area */
        .chat-container { flex-grow: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 15px; background-image: radial-gradient(circle at 50% 50%, rgba(255,255,255,0.01), transparent 100%); }
        
        .message { max-width: 75%; padding: 12px 18px; border-radius: 20px; font-size: 0.95rem; line-height: 1.4; position: relative; }
        
        .msg-them { background: rgba(255,255,255,0.05); color: #fff; align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid rgba(255,255,255,0.08); }
        .msg-me { background: #2DD4BF; color: #000; align-self: flex-end; border-bottom-right-radius: 4px; }
        
        .time-stamp { font-size: 0.7rem; opacity: 0.7; margin-top: 5px; display: block; text-align: right; }
        .msg-them .time-stamp { text-align: left; color: #888; }
        .msg-me .time-stamp { color: rgba(0,0,0,0.6); }

        .empty-chat { text-align: center; color: #666; margin: auto; padding: 40px; }

        /* Input Area */
        .chat-input-area { background: rgba(255,255,255,0.02); backdrop-filter: blur(24px); border-top: 1px solid rgba(255,255,255,0.05); padding: 15px 20px; }
        .chat-form { display: flex; gap: 10px; max-width: 900px; margin: 0 auto; }
        .chat-input { flex-grow: 1; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 15px 20px; border-radius: 24px; font-size: 1rem; outline: none; transition: 0.2s; }
        .chat-input:focus { border-color: #2DD4BF; background: rgba(255,255,255,0.08); }
        .btn-send { background: #2DD4BF; color: #000; border: none; width: 50px; height: 50px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 1.2rem; cursor: pointer; transition: 0.2s; }
        .btn-send:hover { background: #fff; transform: scale(1.05); }

        @media (max-width: 600px) {
            .btn-buy { display: none; } /* Hide buy button on small mobile headers to save space */
        }
    </style>
</head>
<body>

<div class="chat-header">
    <a href="item.php?id=<?php echo $listing_id; ?>" class="btn-back">←</a>
    <div class="header-info">
        <div class="avatar"><?php echo strtoupper(substr($chat_partner['full_name'], 0, 1)); ?></div>
        <div>
            <div class="partner-name"><?php echo htmlspecialchars(explode(' ', $chat_partner['full_name'])[0]); ?></div>
            <div class="item-context"><?php echo htmlspecialchars($item['title']); ?> • KES <?php echo number_format($item['price']); ?></div>
        </div>
    </div>
    <a href="checkout.php?id=<?php echo $listing_id; ?>" class="btn-buy">Buy Now</a>
</div>

<div class="chat-container" id="chatContainer">
    <?php if (empty($messages)): ?>
        <div class="empty-chat">
            <div style="font-size: 2rem; margin-bottom: 10px;">💬</div>
            Send a message to start the conversation about this item.
        </div>
    <?php else: ?>
        <?php foreach ($messages as $msg): ?>
            <?php if ($msg['sender_id'] == $current_user): ?>
                <div class="message msg-me">
                    <?php echo nl2br(htmlspecialchars($msg['message_text'])); ?>
                    <span class="time-stamp"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></span>
                </div>
            <?php else: ?>
                <div class="message msg-them">
                    <?php echo nl2br(htmlspecialchars($msg['message_text'])); ?>
                    <span class="time-stamp"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></span>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="chat-input-area">
    <form action="process_message.php" method="POST" class="chat-form">
        <input type="hidden" name="receiver_id" value="<?php echo $other_user; ?>">
        <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">
        <input type="text" name="message" class="chat-input" placeholder="Type a message..." required autocomplete="off">
        <button type="submit" class="btn-send">➤</button>
    </form>
</div>

<script>
    const chatContainer = document.getElementById('chatContainer');
    chatContainer.scrollTop = chatContainer.scrollHeight;
</script>

</body>
</html>