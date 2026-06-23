<?php
// MILELE - Strict Session Registration Engine

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php'; 
require 'db.php';

try { $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN oauth_provider VARCHAR(50) DEFAULT NULL"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN oauth_uid VARCHAR(255) DEFAULT NULL"); } catch(PDOException $e) {}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $email = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));
    $university = trim(filter_input(INPUT_POST, 'university', FILTER_SANITIZE_SPECIAL_CHARS));
    $password = $_POST['password'];

    if (!strpos($email, '.edu') && !strpos($email, '.ac.ke') && !strpos($email, '.com')) {
        $error = "Please use a valid email address (.edu, .ac.ke, or .com) to join.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "An account with this email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                
                $api_key = getenv('BREVO_API_KEY'); 
                
                $email_data = [
                    'sender' => ['name' => 'MILELE Security', 'email' => 'kibeta425@gmail.com'], 
                    'to' => [['email' => $email, 'name' => $full_name]],
                    'subject' => 'Your Verification Code',
                    'htmlContent' => "
                        <div style='font-family: -apple-system, sans-serif; max-width: 500px; margin: 0 auto; background: #F7F5FF; color: #1A1040; padding: 40px; border-radius: 16px; border: 1px solid rgba(26,16,64,0.10);'>
                            <h1 style='color: #F5A623; margin-bottom: 10px; text-align: center;'>MILELE</h1>
                            <h2 style='text-align: center; margin-top: 0;'>Verify Your Email</h2>
                            <p style='color: #1A1040; font-size: 16px;'>Hello <strong>$full_name</strong>,</p>
                            <p style='color: #8B7FA8; font-size: 16px;'>Welcome to the campus marketplace. To complete your registration, please enter the following 6-digit verification code:</p>
                            
                            <div style='background: #ffffff; padding: 20px; text-align: center; border-radius: 12px; margin: 30px 0; border: 1px solid rgba(26,16,64,0.10);'>
                                <span style='font-size: 32px; font-family: monospace; letter-spacing: 10px; color: #1A1040; font-weight: bold;'>$otp</span>
                            </div>
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

                $_SESSION['pending_reg'] = [
                    'full_name' => $full_name,
                    'email' => $email,
                    'university' => $university,
                    'password_hash' => $hashed_password,
                    'otp' => $otp
                ];

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
    <title>Join MILELE | Campus Marketplace</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --indigo: #1A1040;
            --amber: #F5A623;
            --coral: #FF6B6B;
            --mint: #00D4AA;
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
    </style>
</head>
<body>

<div class="auth-card">
    <div class="brand">MILELE<span class="accent">.</span></div>
    <div class="subtitle">The secure campus marketplace.</div>

    <?php if($error) echo "<div class='alert-error'>$error</div>"; ?>

    <a href="<?php echo htmlspecialchars($google_login_url ?? '#'); ?>" class="btn-social">
        <svg style="width:18px;height:18px;" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
        Continue with Google
    </a>

    <div class="divider">Or register with email</div>

    <form method="POST">
        <div class="input-group">
            <input type="text" name="full_name" class="input-field" placeholder="Full Name" required>
        </div>
        <div class="input-group">
            <input type="email" name="email" class="input-field" placeholder="Campus Email (.edu, .ac.ke)" required>
        </div>
        <div class="input-group">
            <select name="university" class="input-field" required style="appearance: none; cursor: pointer; padding-right: 40px; background-image: url('data:image/svg+xml;utf8,<svg fill=\"%238B7FA8\" height=\"24\" viewBox=\"0 0 24 24\" width=\"24\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M7 10l5 5 5-5z\"/></svg>'); background-repeat: no-repeat; background-position: right 16px center;">
                <option value="" disabled selected>Select University</option>
                <option value="Strathmore University">Strathmore University</option>
                <option value="CUEA">CUEA</option>
                <option value="UoN">University of Nairobi</option>
                <option value="KU">Kenyatta University</option>
            </select>
        </div>
        <div class="input-group">
            <input type="password" name="password" class="input-field" placeholder="Create Password (Min 8 chars)" required minlength="8">
        </div>
        <button type="submit" class="btn-primary">Create Account</button>
    </form>

    <div class="footer-text">
        Already have an account? <a href="login.php">Log In</a>
    </div>
</div>

</body>
</html>