<?php
// MILELE - Premium Registration & Live API OTP Engine

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

// 🛠️ SILENT DATABASE SECURITY UPGRADES
try { $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN otp_code VARCHAR(6) DEFAULT NULL"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN oauth_provider VARCHAR(50) DEFAULT NULL"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN oauth_uid VARCHAR(255) DEFAULT NULL"); } catch(PDOException $e) {}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $email = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));
    $university = trim(filter_input(INPUT_POST, 'university', FILTER_SANITIZE_SPECIAL_CHARS));
    $password = $_POST['password'];

    // Enforce Campus Emails
    if (!strpos($email, '.edu') && !strpos($email, '.ac.ke')) {
        $error = "You must use a valid university email address (.edu or .ac.ke) to join MILELE.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "An account with this email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                
                $insert = $pdo->prepare("INSERT INTO users (full_name, email, university_name, password_hash, is_verified, otp_code) VALUES (?, ?, ?, ?, 0, ?)");
                $insert->execute([$full_name, $email, $university, $hashed_password, $otp]);
                
                // ==========================================
                // 📧 LIVE BREVO EMAIL API INTEGRATION
                // ==========================================
               $api_key = getenv('BREVO_API_KEY');
                
                $email_data = [
                    'sender' => ['name' => 'MILELE Security', 'email' => 'security@milelecampus.com'],
                    'to' => [['email' => $email, 'name' => $full_name]],
                    'subject' => 'Your MILELE Verification Code',
                    'htmlContent' => "
                        <div style='font-family: -apple-system, sans-serif; max-width: 500px; margin: 0 auto; background: #050505; color: #fff; padding: 40px; border-radius: 16px; border: 1px solid #333;'>
                            <h1 style='color: #2DD4BF; margin-bottom: 10px; text-align: center;'>MILELE</h1>
                            <h2 style='text-align: center; margin-top: 0;'>Verify Your Email</h2>
                            <p style='color: #ccc; font-size: 16px;'>Hello <strong>$full_name</strong>,</p>
                            <p style='color: #ccc; font-size: 16px;'>Welcome to the most secure campus marketplace. To complete your registration, please enter the following 6-digit verification code:</p>
                            
                            <div style='background: #111; padding: 20px; text-align: center; border-radius: 12px; margin: 30px 0; border: 1px solid #222;'>
                                <span style='font-size: 32px; font-family: monospace; letter-spacing: 10px; color: #2DD4BF; font-weight: bold;'>$otp</span>
                            </div>
                            
                            <p style='color: #666; font-size: 12px; text-align: center;'>If you did not request this code, please ignore this email.</p>
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
                
                $response = curl_exec($ch);
                curl_close($ch);
                // ==========================================

                $_SESSION['pending_email'] = $email;
                header("Location: verify_email.php");
                exit();
            }
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
    <title>Join MILELE | Secure Campus Marketplace</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box;}
        .auth-card { background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0) 100%); border: 1px solid rgba(255,255,255,0.08); padding: 40px; border-radius: 24px; width: 100%; max-width: 450px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); position: relative; overflow: hidden;}
        .auth-card::before { content: ''; position: absolute; top: -50px; left: -50px; width: 150px; height: 150px; background: rgba(45,212,191,0.1); filter: blur(50px); pointer-events: none;}
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
    <div class="subtitle">The secure campus marketplace.</div>

    <?php if($error) echo "<div class='alert-error'>$error</div>"; ?>

    <a href="#" class="btn-social" onclick="alert('Google OAuth setup required in backend.'); return false;" style="text-decoration:none;">
        <span style="font-size: 1.2rem;">G</span> Continue with Google
    </a>

    <div class="divider">OR</div>

    <form method="POST">
        <div class="input-group">
            <input type="text" name="full_name" class="input-field" placeholder="Full Name" required>
        </div>
        <div class="input-group">
            <input type="email" name="email" class="input-field" placeholder="University Email (.edu or .ac.ke)" required>
        </div>
        <div class="input-group">
            <select name="university" class="input-field" required style="appearance: none; cursor: pointer;">
                <option value="" disabled selected>Select University</option>
                <option value="Strathmore University">Strathmore University</option>
                <option value="CUEA">CUEA</option>
                <option value="UoN">University of Nairobi</option>
                <option value="KU">Kenyatta University</option>
            </select>
        </div>
        <div class="input-group">
            <input type="password" name="password" class="input-field" placeholder="Create Password (Min 8 characters)" required minlength="8">
        </div>
        <button type="submit" class="btn-primary">Create Account</button>
    </form>

    <div class="footer-text">
        Already have an account? <a href="login.php">Log In</a>
    </div>
</div>

</body>
</html>