<?php
// MILELE - Secure Password Reset Requester

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

// SILENT DATABASE SECURITY UPGRADE
try { $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_expires DATETIME DEFAULT NULL"); } catch(PDOException $e) {}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));

    try {
        $stmt = $pdo->prepare("SELECT user_id, full_name, oauth_provider FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['oauth_provider'] === 'google') {
                $error = "This email is registered via Google. Please use 'Continue with Google' to log in.";
            } else {
                // 1. Generate Secure Token & Expiry (15 minutes from now)
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 900); 

                // 2. Save Token to Database
                $update_stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
                $update_stmt->execute([$token, $expires, $email]);

                // 3. Build the Dynamic Reset Link (Works for both XAMPP and Heroku)
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                // Get the base directory path (e.g., http://localhost/MILELE or https://milele.herokuapp.com)
                $base_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $reset_link = $protocol . $_SERVER['HTTP_HOST'] . $base_dir . "/reset_password.php?token=" . $token;

                // 4. Send Brevo Email
                $api_key = getenv('BREVO_API_KEY'); 
                $full_name = $user['full_name'];
                
                $email_data = [
                    'sender' => ['name' => 'MILELE Security', 'email' => 'kibeta425@gmail.com'], 
                    'to' => [['email' => $email, 'name' => $full_name]],
                    'subject' => 'MILELE Password Reset Request',
                    'htmlContent' => "
                        <div style='font-family: -apple-system, sans-serif; max-width: 500px; margin: 0 auto; background: #050505; color: #fff; padding: 40px; border-radius: 16px; border: 1px solid #333;'>
                            <h1 style='color: #2DD4BF; margin-bottom: 10px; text-align: center;'>MILELE</h1>
                            <h2 style='text-align: center; margin-top: 0;'>Password Reset</h2>
                            <p style='color: #ccc; font-size: 16px;'>Hello <strong>$full_name</strong>,</p>
                            <p style='color: #ccc; font-size: 16px;'>We received a request to reset your MILELE password. This link is valid for 15 minutes.</p>
                            
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='$reset_link' style='background: #2DD4BF; color: #000; padding: 15px 30px; text-decoration: none; font-weight: bold; border-radius: 8px; display: inline-block;'>Reset My Password</a>
                            </div>
                            
                            <p style='color: #666; font-size: 12px; text-align: center;'>If you did not request this, you can safely ignore this email. Your password will remain unchanged.</p>
                        </div>"
                ];

                $ch = curl_init('https://api.brevo.com/v3/smtp/email');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($email_data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'accept: application/json',
                    'api-key: ' . $api_key,
                    'content-type: application/json'
                ]);
                curl_exec($ch);
                curl_close($ch);
            }
        }
        
        // Security best practice: Always show the same success message even if email doesn't exist to prevent hackers from guessing user emails.
        if (empty($error)) {
            $message = "If an account with that email exists, a reset link has been sent.";
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
    <title>Forgot Password | MILELE</title>
    <style>
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
    <div class="subtitle">Reset your password</div>

    <?php if($error) echo "<div class='alert-error'>$error</div>"; ?>
    <?php if($message) echo "<div class='alert-success'>$message</div>"; ?>

    <form method="POST">
        <div class="input-group">
            <input type="email" name="email" class="input-field" placeholder="Enter your registered email" required>
        </div>
        <button type="submit" class="btn-primary">Send Reset Link</button>
    </form>

    <div class="footer-text">
        Remembered your password? <a href="login.php">Log In</a>
    </div>
</div>

</body>
</html>