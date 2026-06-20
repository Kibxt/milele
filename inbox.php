<?php
// MILELE - Premium Inbox Hub

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$user_id = $_SESSION['user_id'];

try {
    // Advanced Cloud SQL to fetch active chat threads, latest messages, and unread counts
    $sql = "
        SELECT 
            t.listing_id, 
            t.partner_id, 
            u.full_name as partner_name, 
            l.title as item_title, 
            m.message_text as last_message, 
            m.created_at as last_message_time,
            m.sender_id as last_sender,
            (SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND sender_id = t.partner_id AND listing_id = t.listing_id AND is_read = 0) as unread_count
        FROM (
            SELECT 
                listing_id, 
                CASE WHEN sender_id = :uid THEN receiver_id ELSE sender_id END as partner_id,
                MAX(message_id) as latest_msg_id
            FROM messages 
            WHERE sender_id = :uid OR receiver_id = :uid
            GROUP BY listing_id, CASE WHEN sender_id = :uid THEN receiver_id ELSE sender_id END
        ) t
        JOIN messages m ON m.message_id = t.latest_msg_id
        JOIN users u ON u.user_id = t.partner_id
        JOIN listings l ON l.listing_id = t.listing_id
        ORDER BY m.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $user_id]);
    $threads = $stmt->fetchAll();

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
        /* Shared Premium Glass Aesthetic */
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; min-height: 100vh; }
        
        .navbar { background: rgba(255,255,255,0.02); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .brand { font-size: 1.5rem; font-weight: 800; letter-spacing: 2px; color: #fff; text-decoration: none; }
        .brand span { color: #2DD4BF; }
        .btn-back { color: #ccc; text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: 0.2s; border: 1px solid rgba(255,255,255,0.1); padding: 8px 16px; border-radius: 12px; }
        .btn-back:hover { background: rgba(255,255,255,0.1); color: #fff; }

        .container { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        
        .header h1 { font-size: 2.5rem; margin: 0 0 10px 0; color: #fff; }
        .header p { color: #888; font-size: 1.1rem; margin-bottom: 40px; }

        /* Inbox List */
        .thread-list { display: flex; flex-direction: column; gap: 15px; }
        
        .thread-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 20px; display: flex; align-items: center; gap: 20px; transition: 0.2s; text-decoration: none; color: inherit; position: relative; }
        .thread-card:hover { background: rgba(255,255,255,0.04); border-color: rgba(45,212,191,0.3); transform: translateY(-2px); }
        .thread-unread { border-left: 4px solid #2DD4BF; background: rgba(45,212,191,0.02); }

        .avatar { width: 50px; height: 50px; background: rgba(45,212,191,0.1); color: #2DD4BF; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 1.2rem; flex-shrink: 0; border: 1px solid rgba(45,212,191,0.2); }
        
        .thread-details { flex-grow: 1; overflow: hidden; }
        .thread-header { display: flex; justify-content: space-between; margin-bottom: 5px; align-items: baseline; }
        .partner-name { font-weight: bold; font-size: 1.1rem; }
        .time { font-size: 0.8rem; color: #666; }
        
        .item-context { font-size: 0.85rem; color: #2DD4BF; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .last-message { color: #888; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .last-message strong { color: #ccc; }

        .unread-badge { background: #2DD4BF; color: #000; font-size: 0.75rem; font-weight: bold; padding: 4px 10px; border-radius: 12px; margin-left: 10px; }

        .empty-state { text-align: center; padding: 80px 20px; color: #666; background: rgba(255,255,255,0.02); border-radius: 24px; border: 1px dashed rgba(255,255,255,0.1); }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="brand">MILE<span>LE</span></a>
    <a href="index.php" class="btn-back">← Back to Feed</a>
</nav>

<div class="container">
    <div class="header">
        <h1>Messages</h1>
        <p>Coordinate pickups and negotiate prices.</p>
    </div>

    <div class="thread-list">
        <?php if (empty($threads)): ?>
            <div class="empty-state">
                <div style="font-size: 3rem; margin-bottom: 15px;">💬</div>
                <h2>Your inbox is empty.</h2>
                <p>Message a seller from an item listing to start a conversation.</p>
            </div>
        <?php else: ?>
            <?php foreach ($threads as $thread): ?>
                <?php $is_unread = $thread['unread_count'] > 0; ?>
                
                <a href="chat.php?seller=<?php echo $thread['partner_id']; ?>&item=<?php echo $thread['listing_id']; ?>" 
                   class="thread-card <?php echo $is_unread ? 'thread-unread' : ''; ?>">
                    
                    <div class="avatar">
                        <?php echo strtoupper(substr($thread['partner_name'], 0, 1)); ?>
                    </div>
                    
                    <div class="thread-details">
                        <div class="thread-header">
                            <span class="partner-name"><?php echo htmlspecialchars(explode(' ', $thread['partner_name'])[0]); ?></span>
                            <span class="time"><?php echo date('M d, g:i A', strtotime($thread['last_message_time'])); ?></span>
                        </div>
                        <div class="item-context"><?php echo htmlspecialchars($thread['item_title']); ?></div>
                        <div class="last-message">
                            <?php if ($thread['last_sender'] == $user_id): ?>
                                <strong>You:</strong> 
                            <?php endif; ?>
                            <?php echo htmlspecialchars($thread['last_message']); ?>
                        </div>
                    </div>

                    <?php if ($is_unread): ?>
                        <div class="unread-badge"><?php echo $thread['unread_count']; ?> New</div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>