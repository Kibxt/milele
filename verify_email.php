<?php
// MILELE - Strict Verification & Database Insertion

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

// Check if they came from the registration page
if (!isset($_SESSION['pending_reg'])) {
    header("Location: register.php");
    exit();
}

$pending_data = $_SESSION['pending_reg'];
$email = $pending_data['email'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_code = trim($_POST['otp']);

    if ($entered_code === $pending_data['otp']) {
        try {
            // THE CODE IS CORRECT. NOW WE OFFICIALLY CREATE THE ACCOUNT.
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, university_name, password_hash, is_verified) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([
                $pending_data['full_name'], 
                $pending_data['email'], 
                $pending_data['university'], 
                $pending_data['password_hash']
            ]);
            
            // Log them in
            $_SESSION['user_id'] = $pdo->lastInsertId();
            
            // Clear the temporary session data
            unset($_SESSION['pending_reg']);
            
            header("Location: profile.php");
            exit();
            
        } catch (PDOException $e) {
            $error = "Database Error: It's possible this email was verified in another session.";
        }
    } else {
        $error = "Incorrect verification code. Please check your email and try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email | MILELE</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px;}
        .auth-card { background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0) 100%); border: 1px solid rgba(255,255,255,0.08); padding: 40px; border-radius: 24px; width: 100%; max-width: 450px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.5);}
        
        .icon-box { font-size: 3.5rem; margin-bottom: 15px; color: #2DD4BF;}
        .title { font-size: 1.8rem; margin: 0 0 10px 0; color: #fff;}
        .subtitle { color: #888; font-size: 1rem; margin-bottom: 30px; line-height: 1.5;}
        .highlight-email { color: #2DD4BF; font-weight: bold;}

        .input-field { width: 100%; background: rgba(0,0,0,0.5); border: 2px solid rgba(255,255,255,0.1); padding: 20px; border-radius: 16px; color: #fff; font-size: 2.5rem; outline: none; box-sizing: border-box; text-align: center; letter-spacing: 15px; font-family: monospace; transition: 0.3s;}
        .input-field:focus { border-color: #2DD4BF; background: rgba(45,212,191,0.02);}
        
        .btn-primary { width: 100%; padding: 18px; background: #2DD4BF; color: #000; border: none; border-radius: 12px; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: 0.3s; margin-top: 25px;}
        .btn-primary:hover { background: #fff; transform: translateY(-2px);}
        
        .alert-error { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); padding: 15px; border-radius: 12px; margin-bottom: 25px; font-size: 0.95rem; font-weight: bold;}
    </style>
</head>
<body>
<div class="auth-card">
    <div class="icon-box">✉️</div>
    <h2 class="title">Verify Your Email</h2>
    <p class="subtitle">We sent a secure 6-digit code to <br><span class="highlight-email"><?php echo htmlspecialchars($email); ?></span></p>

    <?php if($error) echo "<div class='alert-error'>$error</div>"; ?>

    <form method="POST">
        <input type="text" name="otp" class="input-field" maxlength="6" placeholder="------" required autocomplete="off">
        <button type="submit" class="btn-primary">Verify & Secure Login</button>
    </form>
</div>
</body>
</html>