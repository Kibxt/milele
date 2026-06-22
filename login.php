<?php
// MILELE - Secure Login Engine

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT user_id, password_hash, account_state, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['account_state'] === 'frozen') {
                $error = "This account has been frozen by administration.";
            } elseif ($user['is_verified'] == 0) {
                $_SESSION['pending_email'] = $email;
                header("Location: verify_email.php");
                exit();
            } else {
                $_SESSION['user_id'] = $user['user_id'];
                
                // Redirect logic
                $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
                header("Location: " . htmlspecialchars($redirect));
                exit();
            }
        } else {
            $error = "Invalid email or password.";
        }
    } catch (PDOException $e) {
        $error = "System Error: " . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | MILELE</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box;}
        
        .auth-card { background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0) 100%); border: 1px solid rgba(255,255,255,0.08); padding: 40px; border-radius: 24px; width: 100%; max-width: 450px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); position: relative; overflow: hidden;}
        .auth-card::before { content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(45,212,191,0.1); filter: blur(50px); pointer-events: none;}
        
        .brand { font-size: 2.2rem; font-weight: 900; color: #2DD4BF; text-align: center; margin-bottom: 5px; letter-spacing: -1px;}
        .subtitle { text-align: center; color: #888; margin-bottom: 30px; font-size: 0.95rem;}
        
        .input-group { margin-bottom: 20px;}
        .input-field { width: 100%; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); padding: 16px 20px; border-radius: 12px; color: #fff; font-size: 1rem; outline: none; box-sizing: border-box; transition: 0.3s;}
        .input-field:focus { border-color: #2DD4BF; background: rgba(45,212,191,0.02);}
        
        .btn-primary { width: 100%; padding: 18px; background: #2DD4BF; color: #000; border: none; border-radius: 12px; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: 0.3s; margin-top: 10px;}
        .btn-primary:hover { background: #fff; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(45,212,191,0.2);}
        
        .divider { display: flex; align-items: center; text-align: center; color: #666; margin: 25px 0; font-size: 0.85rem; font-weight: bold;}
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .divider:not(:empty)::before { margin-right: 1em; }
        .divider:not(:empty)::after { margin-left: 1em; }

        .btn-social { width: 100%; padding: 16px; background: rgba(255,255,255,0.03); color: #fff; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; font-weight: bold; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 15px; transition: 0.2s;}
        .btn-social:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2);}
        
        .alert-error { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); padding: 15px; border-radius: 12px; margin-bottom: 25px; font-size: 0.95rem; text-align: center; font-weight: bold;}
        .footer-text { text-align: center; margin-top: 25px; color: #888; font-size: 0.95rem;}
        .footer-text a { color: #2DD4BF; text-decoration: none; font-weight: bold; transition: 0.2s;}
        .footer-text a:hover { color: #fff;}
    </style>
</head>
<body>

<div class="auth-card">
    <div class="brand">MILELE</div>
    <div class="subtitle">Welcome back.</div>

    <?php if($error) echo "<div class='alert-error'>$error</div>"; ?>

    <a href="#" class="btn-social" onclick="alert('Google OAuth setup required in backend.'); return false;" style="text-decoration:none;">
        <span style="font-size: 1.2rem;">G</span> Sign in with Google
    </a>

    <div class="divider">OR</div>

    <form method="POST">
        <div class="input-group">
            <input type="email" name="email" class="input-field" placeholder="University Email" required>
        </div>
        <div class="input-group">
            <input type="password" name="password" class="input-field" placeholder="Password" required>
        </div>
        <button type="submit" class="btn-primary">Log In</button>
    </form>

    <div class="footer-text">
        Don't have an account? <a href="register.php">Sign Up</a>
    </div>
</div>

</body>
</html>