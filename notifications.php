<?php
// MILELE - Premium Notifications Hub

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ⚡ THE MASTER CONNECTION
require 'db.php';

$user_id = $_SESSION['user_id'];

try {
    // 1. Fetch all notifications for this user
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :id ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([':id' => $user_id]);
    $notifications = $stmt->fetchAll();

    // 2. Mark them as read once they open the page
    $update = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :id AND is_read = 0");
    $update->execute([':id' => $user_id]);

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System error loading notifications.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | MILELE</title>
    <style>
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 1.8rem; }
        .btn-back { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border-radius: 12px; font-size: 0.9rem; border: 1px solid rgba(255,255,255,0.1); transition: 0.2s; }
        .btn-back:hover { background: rgba(255,255,255,0.1); }

        .notif-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 20px; border-radius: 16px; margin-bottom: 15px; display: flex; gap: 15px; transition: 0.2s; text-decoration: none; color: inherit; display: block;}
        .notif-card:hover { background: rgba(255,255,255,0.06); border-color: rgba(45,212,191,0.3); }
        .unread { border-left: 4px solid #2DD4BF; }
        
        .notif-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .notif-icon { font-size: 1.5rem; }
        .notif-title { font-weight: bold; font-size: 1.05rem; }
        .notif-time { font-size: 0.8rem; color: #666; margin-left: auto; }
        .notif-message { color: #aaa; font-size: 0.95rem; line-height: 1.4; }
        
        .empty-state { text-align: center; padding: 50px; color: #666; background: rgba(255,255,255,0.02); border-radius: 16px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Notifications</h1>
        <a href="index.php" class="btn-back">← Back to Feed</a>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <div style="font-size: 3rem; margin-bottom: 15px;">📭</div>
            <p>You're all caught up. No new notifications.</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $notif): ?>
            <a href="<?php echo htmlspecialchars($notif['link'] ?? '#'); ?>" class="notif-card <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?>">
                <div class="notif-header">
                    <span class="notif-icon"><?php echo htmlspecialchars($notif['icon'] ?? '🔔'); ?></span>
                    <span class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></span>
                    <span class="notif-time"><?php echo date('M d, g:i A', strtotime($notif['created_at'])); ?></span>
                </div>
                <div class="notif-message">
                    <?php echo htmlspecialchars($notif['message']); ?>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>