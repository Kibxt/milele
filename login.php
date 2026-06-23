<?php
// MILELE - Secure Login Gateway

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['is_verified'] == 0) {
                $error = "Please verify your email address before logging in.";
            } else {
                $_SESSION['user_id'] = $user['user_id'];
                header("Location: profile.php");
                exit();
            }
        } else {
            $error = "Invalid email address or password.";
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
    <title>Log In | MILELE</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --indigo: #1A1040;
            --amber: #F5A623;
            --coral: #FF6B6B;
            --chalk: #F7F5FF;
            --slate: #8B7FA8;
            --white: #ffffff;
            --card-border: rgba(26,16,64,0.10);
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { background: var(--chalk); color: var(--indigo); font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .auth-card { background: var(--white); border: 1px solid var(--card-border); padding: 40px; border-radius: 24px; width: 100%; max-width: 450px; box-shadow: 0 20px 60px rgba(26,16,64,0.06); }
        .brand { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; color: var(--indigo); text-align: center; margin-bottom: 5px; }
        .brand .accent { color: var(--amber); }
        .subtitle { text-align: center; color: var(--slate); margin-bottom: 30px; font-size: 14px; }
        
        .input-group { margin-bottom: 16px; }
        .input-field { width: 100%; height: 52px; border: 2px solid var(--card-border); border-radius: 50px; padding: 0 24px; font-size: 14px; font-family: 'Inter', sans-serif; color: var(--indigo); background: var(--chalk); outline: none; transition: all 0.2s; }
        .input-field:focus { border-color: var(--amber); box-shadow: 0 0 0 4px rgba(245,166,35,0.12); }
        .input-field::placeholder { color: var(--slate); }
        
        .btn-primary { width: 100%; background: var(--amber); border: none; color: var(--indigo); padding: 15px; border-radius: 50px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; box-shadow: 0 4px 20px rgba(245,166,35,0.3); margin-top: 8px; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(245,166,35,0.45); }
        
        .btn-social { width: 100%; height: 52px; background: var(--white); color: var(--indigo); border: 2px solid var(--card-border); border-radius: 50px; font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 15px; transition: 0.2s; font-family: 'Inter', sans-serif; text-decoration: none; }
        .btn-social:hover { border-color: var(--indigo); background: var(--chalk); }
        
        .divider { display: flex; align-items: center; text-align: center; color: var(--slate); margin: 24px 0; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid var(--card-border); }
        .divider:not(:empty)::before { margin-right: 1em; }
        .divider:not(:empty)::after { margin-left: 1em; }
        
        .alert-error { background: rgba(255,107,107,0.1); color: var(--coral); border: 1px solid rgba(255,107,107,0.2); padding: 14px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; text-align: center; font-weight: 600; }
        
        .footer-text { text-align: center; margin-top: 24px; color: var(--slate); font-size: 14px; }
        .footer-text a { color: var(--indigo); text-decoration: none; font-weight: 700; transition: color 0.2s; }
        .footer-text a:hover { color: var(--amber); }

        .forgot-password { display: block; text-align: right; color: var(--slate); font-size: 13px; margin-top: -6px; margin-bottom: 20px; text-decoration: none; font-weight: 500; transition: color 0.2s; }
        .forgot-password:hover { color: var(--indigo); }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="brand">MILELE<span class="accent">.</span></div>
    <div class="subtitle">Welcome back to the marketplace.</div>

    <?php if($error) echo "<div class='alert-error'>$error</div>"; ?>

    <a href="<?php echo htmlspecialchars($google_login_url ?? '#'); ?>" class="btn-social">
        <svg style="width:18px;height:18px;" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
        Continue with Google
    </a>

    <div class="divider">Or log in with email</div>

    <form method="POST">
        <div class="input-group">
            <input type="email" name="email" class="input-field" placeholder="Email Address" required>
        </div>
        <div class="input-group" style="margin-bottom: 10px;">
            <input type="password" name="password" class="input-field" placeholder="Password" required>
        </div>
        
        <a href="forgot_password.php" class="forgot-password">Forgot password?</a>

        <button type="submit" class="btn-primary">Log In</button>
    </form>

    <div class="footer-text">
        Don't have an account? <a href="register.php">Join MILELE</a>
    </div>
</div>

</body>
</html>