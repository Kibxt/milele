<?php
// MILELE - Premium Login Interface
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// If they are already logged in, send them straight to the feed
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | MILELE</title>
    <style>
        /* Ultra-Premium Glass Aesthetic */
        :root { --accent: #2DD4BF; --bg: #000; --glass: rgba(255,255,255,0.03); --border: rgba(255,255,255,0.08); }
        body { background: var(--bg); color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; min-height: 100vh; margin: 0; display: flex; justify-content: center; align-items: center; background-image: radial-gradient(circle at 15% 50%, rgba(45, 212, 191, 0.08), transparent 25%), radial-gradient(circle at 85% 30%, rgba(255, 255, 255, 0.03), transparent 25%); }
        
        .auth-container { background: var(--glass); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border: 1px solid var(--border); padding: 40px; border-radius: 32px; width: 100%; max-width: 400px; box-shadow: 0 24px 48px rgba(0,0,0,0.4); text-align: center; }
        
        .logo { font-size: 2rem; font-weight: 800; letter-spacing: 2px; margin-bottom: 10px; color: #fff; }
        .logo span { color: var(--accent); }
        .subtitle { color: #888; font-size: 0.9rem; margin-bottom: 30px; }
        
        .error-msg { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.2); color: #F87171; padding: 12px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem; }
        
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; color: #888; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .input-group input { width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: #fff; padding: 14px 16px; border-radius: 16px; font-size: 1rem; transition: 0.3s; outline: none; box-sizing: border-box; }
        .input-group input:focus { border-color: var(--accent); background: rgba(255,255,255,0.08); box-shadow: 0 0 0 4px rgba(45,212,191,0.1); }
        
        .btn-primary { width: 100%; background: var(--accent); color: #000; border: none; padding: 16px; border-radius: 16px; font-weight: bold; font-size: 1.05rem; cursor: pointer; transition: 0.2s; margin-top: 10px; }
        .btn-primary:hover { background: #fff; transform: translateY(-2px); }
        
        .switch-link { display: block; margin-top: 25px; color: #888; font-size: 0.9rem; text-decoration: none; transition: 0.2s; }
        .switch-link span { color: #fff; font-weight: bold; }
        .switch-link:hover span { color: var(--accent); }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="logo">MILE<span>LE</span></div>
    <div class="subtitle">Access the Campus Marketplace</div>

    <?php if(isset($_SESSION['error_msg'])): ?>
        <div class="error-msg"><?php echo htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?></div>
    <?php endif; ?>

    <form action="login_process.php" method="POST">
        <div class="input-group">
            <label>Student Email</label>
            <input type="email" name="email" required placeholder="you@student.ac.ke">
        </div>
        
        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="••••••••">
        </div>

        <button type="submit" class="btn-primary">Sign In</button>
    </form>

    <a href="register.php" class="switch-link">Don't have an account? <span>Create one</span></a>
</div>

</body>
</html>