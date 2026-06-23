<?php
// MILELE - Secure Password Update Receiver

require 'db.php';

$error = '';
$message = '';
$token_valid = false;
$user_id = null;

// 1. Check if there is a token in the URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // Look up the token in the database AND ensure it hasn't expired
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $token_valid = true;
            $user_id = $user['user_id'];
        } else {
            $error = "This password reset link is invalid or has expired. Please request a new one.";
        }
    } catch (PDOException $e) {
        $error = "System Error: " . htmlspecialchars($e->getMessage());
    }
} else {
    $error = "No reset token provided.";
}

// 2. Process the New Password Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update the password AND wipe the reset token so it can't be used again
            $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE user_id = ?");
            $update_stmt->execute([$hashed_password, $user_id]);

            $message = "Your password has been successfully reset!";
            $token_valid = false; // Hide the form
        } catch (PDOException $e) {
            $error = "System Error: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Password | MILELE</title>
    <style>
        /* Exact same styles as forgot_password.php */
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box;}
        .auth-card { background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0) 100%); border: 1px solid rgba(255,255,255,0.08); padding: 40px; border-radius: 24px; width: 100%; max-width: 450px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); position: relative; overflow: hidden;}
        .brand { font-size: 2.2rem; font-weight: 900; color: #2DD4BF; text-align: center; margin-bottom: 5px; letter-spacing: -1px;}
        .subtitle { text-align: center; color: #888; margin-bottom: 30px; font-size: 0.95rem;}
        .input-group { margin-bottom: 20px;}
        .input-field { width: 100%; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); padding: 16px 20px; border-radius: 12px; color: #fff; font-size: 1rem; outline: none; box-sizing: border-box; transition: 0.3s;}
        .input-field:focus { border-color: #2DD4BF;}
        .btn-primary { width: 100%; padding: 18px; background: #2DD4BF; color: #000; border: none; border-radius: 12px; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: 0.3s; margin-top: 10px;}
        .btn-primary:hover { background: #fff;}
        .alert-error { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); padding: 15px; border-radius: 12px; margin-bottom: 25px; font-size: 0.95rem; text-align: center; font-weight: bold;}
        .alert-success { background: rgba(45,212,191,0.1); color: #2DD4BF; border: 1px solid rgba(45,212,191,0.3); padding: 15px; border-radius: 12px; margin-bottom: 25px; font-size: 0.95rem; text-align: center; font-weight: bold;}
        .footer-text { text-align: center; margin-top: 25px; color: #888; font-size: 0.95rem;}
        .footer-text a { color: #2DD4BF; text-decoration: none; font-weight: bold;}
    </style>
</head>
<body>

<div class="auth-card">
    <div class="brand">MILELE</div>
    <div class="subtitle">Secure Password Reset</div>

    <?php if($error) echo "<div class='alert-error'>$error</div>"; ?>
    <?php if($message) echo "<div class='alert-success'>$message</div>"; ?>

    <?php if($token_valid): ?>
        <form method="POST">
            <div class="input-group">
                <input type="password" name="password" class="input-field" placeholder="New Password (Min 8 characters)" required minlength="8">
            </div>
            <div class="input-group">
                <input type="password" name="confirm_password" class="input-field" placeholder="Confirm New Password" required minlength="8">
            </div>
            <button type="submit" class="btn-primary">Update Password</button>
        </form>
    <?php endif; ?>

    <?php if(!$token_valid && !$message): ?>
        <div class="footer-text" style="margin-top: 0;">
            <a href="forgot_password.php">Request a new reset link</a>
        </div>
    <?php endif; ?>

    <?php if($message): ?>
        <div class="footer-text">
            <a href="login.php" style="font-size: 1.1rem;">&rarr; Proceed to Login</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>