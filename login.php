<?php
// MILELE - Login Gateway (With Auto-Patching & Ban Enforcement)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require 'db.php';

// ==========================================
// 🛠️ SILENT DATABASE UPGRADES
// ==========================================
try { $pdo->exec("ALTER TABLE users ADD COLUMN banned_until DATETIME DEFAULT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN strike_count INT DEFAULT 0"); } catch (PDOException $e) {}

$error = '';

if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        try {
            // PATCHED: Now requesting 'password_hash' to match your database exactly
            $stmt = $pdo->prepare("SELECT user_id, password_hash, banned_until FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            // PATCHED: Verifying against 'password_hash'
            if ($user && password_verify($password, $user['password_hash'])) {
                
                // ==========================================
                // 🛑 THE BAN WALL
                // ==========================================
                if (!empty($user['banned_until']) && strtotime($user['banned_until']) > time()) {
                    $ban_end_date = date("F j, Y, g:i A", strtotime($user['banned_until']));
                    $error = "🚨 <strong>ACCOUNT SUSPENDED</strong><br>Your account has been banned for repeatedly violating our security rules. This ban will automatically lift on:<br><span style='color:#fff;'>$ban_end_date</span>";
                } else {
                    // THE FORGIVENESS PROTOCOL
                    if (!empty($user['banned_until'])) {
                        $pdo->prepare("UPDATE users SET banned_until = NULL, strike_count = 0 WHERE user_id = ?")->execute([$user['user_id']]);
                    }
                    
                    $_SESSION['user_id'] = $user['user_id'];
                    header("Location: index.php");
                    exit();
                }
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "System Error: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error = "Please fill in both fields.";
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
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; overflow: hidden; background-image: radial-gradient(circle at 50% -20%, rgba(45, 212, 191, 0.15), transparent 60%); }
        .login-card { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); padding: 50px 40px; border-radius: 32px; width: 100%; max-width: 400px; box-shadow: 0 24px 48px rgba(0,0,0,0.5); text-align: center; }
        .brand { font-size: 2.5rem; font-weight: 900; color: #2DD4BF; margin: 0 0 10px 0; letter-spacing: -1px; }
        .subtitle { color: #888; font-size: 0.95rem; margin-bottom: 40px; }
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; font-size: 0.85rem; color: #888; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .input-wrapper { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 15px; transition: 0.3s; }
        .input-wrapper:focus-within { border-color: #2DD4BF; background: rgba(255, 255, 255, 0.08); }
        .input-wrapper input { width: 100%; background: transparent; border: none; color: #fff; font-size: 1rem; outline: none; font-family: inherit; }
        .btn-submit { width: 100%; padding: 18px; background: #2DD4BF; color: #000; border: none; border-radius: 16px; font-weight: bold; font-size: 1.1rem; cursor: pointer; margin-top: 10px; transition: 0.2s; }
        .btn-submit:hover { background: #fff; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(45, 212, 191, 0.2); }
        .bottom-link { display: block; margin-top: 25px; color: #888; text-decoration: none; font-size: 0.9rem; transition: 0.2s; }
        .bottom-link:hover { color: #2DD4BF; }
        .error-box { background: rgba(248, 113, 113, 0.1); border: 1px solid rgba(248, 113, 113, 0.3); color: #F87171; padding: 15px; border-radius: 12px; margin-bottom: 25px; font-size: 0.9rem; line-height: 1.5; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="brand">MILELE</div>
    <div class="subtitle">Secure Escrow Gateway</div>

    <?php if ($error) echo "<div class='error-box'>$error</div>"; ?>

    <form method="POST" action="login.php">
        <div class="input-group">
            <label>Student Email</label>
            <div class="input-wrapper">
                <input type="email" name="email" required autocomplete="email">
            </div>
        </div>

        <div class="input-group">
            <label>Password</label>
            <div class="input-wrapper">
                <input type="password" name="password" required autocomplete="current-password">
            </div>
        </div>

        <button type="submit" class="btn-submit">Sign In</button>
    </form>

    <a href="register.php" class="bottom-link">Don't have an account? <strong>Register</strong></a>
</div>

</body>
</html>