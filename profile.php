<?php
// MILELE - Private User Dashboard (Profile Pic & Stats)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';
$my_id = $_SESSION['user_id'];
$error = '';
$success = '';

// ==========================================
// 🛠️ SILENT DATABASE UPGRADES (SOCIAL ENGINE)
// ==========================================
try { $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e) {}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS follows (
        follower_id INT NOT NULL,
        followed_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (follower_id, followed_id)
    )");
} catch (PDOException $e) {}

// ==========================================
// 📸 PROFILE PICTURE UPLOAD LOGIC
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    $tmp_name = $_FILES['profile_pic']['tmp_name'];
    $file_type = $_FILES['profile_pic']['type'];
    $file_name = $_FILES['profile_pic']['name'];

    // 1. AI Security Scan (No explicit profile pictures)
    $sightengine_user = '1287637059';     
    $sightengine_secret = 'vVLakzVx9WAHwqvg9o8p9ucggiu5byzJ'; 
    
    $ch_ai = curl_init('https://api.sightengine.com/1.0/check.json');
    curl_setopt($ch_ai, CURLOPT_POST, true);
    curl_setopt($ch_ai, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_ai, CURLOPT_SSL_VERIFYPEER, false);
    $cfile = new CURLFile($tmp_name, $file_type, $file_name);
    curl_setopt($ch_ai, CURLOPT_POSTFIELDS, ['models' => 'nudity-2.0,wad,offensive,gore', 'api_user' => $sightengine_user, 'api_secret' => $sightengine_secret, 'media' => $cfile]);
    
    $ai_result = json_decode(curl_exec($ch_ai), true);
    curl_close($ch_ai);

    $is_safe = true;
    if (isset($ai_result['status']) && $ai_result['status'] === 'success') {
        $weapon_score = $ai_result['weapon'] ?? ($ai_result['wad']['weapon'] ?? 0);
        $safe_score = $ai_result['nudity']['safe'] ?? ($ai_result['nudity']['none'] ?? 1);
        if ($weapon_score > 0.4 || $safe_score < 0.5) {
            $error = "Profile picture rejected by AI Security. Please use an appropriate image.";
            $is_safe = false;
        }
    } else {
        $error = "AI Scan failed. Please try again.";
        $is_safe = false;
    }

    // 2. Cloud Upload
    if ($is_safe) {
        $imgbb_api_key = '1006ee1ae706c851943f2918cb115ed8'; 
        $image_base64 = base64_encode(file_get_contents($tmp_name));
        
        $ch_cloud = curl_init();
        curl_setopt($ch_cloud, CURLOPT_URL, 'https://api.imgbb.com/1/upload?key=' . $imgbb_api_key);
        curl_setopt($ch_cloud, CURLOPT_POST, 1);
        curl_setopt($ch_cloud, CURLOPT_POSTFIELDS, ['image' => $image_base64]);
        curl_setopt($ch_cloud, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_cloud, CURLOPT_SSL_VERIFYPEER, false);
        
        $cloud_result = json_decode(curl_exec($ch_cloud), true);
        curl_close($ch_cloud);
        
        if (isset($cloud_result['data']['url'])) {
            $new_pic_url = $cloud_result['data']['url'];
            $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?")->execute([$new_pic_url, $my_id]);
            $success = "Profile picture updated successfully!";
        } else {
            $error = "Cloud upload failed.";
        }
    }
}

// ==========================================
// 📊 FETCH USER DATA & STATS
// ==========================================
$stmt = $pdo->prepare("SELECT full_name, university_name, profile_picture FROM users WHERE user_id = ?");
$stmt->execute([$my_id]);
$user = $stmt->fetch();

$followers_count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ?");
$followers_count->execute([$my_id]);
$f_count = $followers_count->fetchColumn();

$following_count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
$following_count->execute([$my_id]);
$fw_count = $following_count->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | MILELE</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; min-height: 100vh;}
        .nav-bar { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(5,5,5,0.8); backdrop-filter: blur(20px);}
        .brand { font-size: 1.8rem; font-weight: 800; color: #2DD4BF; text-decoration: none;}
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; font-weight: bold;}
        
        .container { max-width: 800px; margin: 50px auto; padding: 0 20px; text-align: center; }
        
        .avatar-wrapper { position: relative; width: 120px; height: 120px; margin: 0 auto 20px; }
        .big-avatar { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 3px solid #2DD4BF; background: #111; display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: bold; color: #2DD4BF;}
        .edit-btn { position: absolute; bottom: 0; right: 0; background: #2DD4BF; color: #000; border: none; border-radius: 50%; width: 35px; height: 35px; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.5);}
        
        .stats-row { display: flex; justify-content: center; gap: 40px; margin: 30px 0; background: rgba(255,255,255,0.03); padding: 20px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05);}
        .stat-item { display: flex; flex-direction: column; }
        .stat-value { font-size: 1.5rem; font-weight: bold; color: #fff;}
        .stat-label { font-size: 0.85rem; color: #888; text-transform: uppercase; letter-spacing: 1px;}
        
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; }
        .alert-error { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); }
        .alert-success { background: rgba(45,212,191,0.1); color: #2DD4BF; border: 1px solid rgba(45,212,191,0.3); }
        
        #picForm { display: none; margin-top: 20px; }
    </style>
</head>
<body>

<nav class="nav-bar">
    <a href="index.php" class="brand">MILELE</a>
    <a href="login.php" class="btn-glass" onclick="<?php session_destroy(); ?>">Logout</a>
</nav>

<div class="container">
    <?php if($error) echo "<div class='alert alert-error'>$error</div>"; ?>
    <?php if($success) echo "<div class='alert alert-success'>$success</div>"; ?>

    <div class="avatar-wrapper">
        <?php if($user['profile_picture']): ?>
            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="big-avatar" alt="Profile">
        <?php else: ?>
            <div class="big-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
        <?php endif; ?>
        <button class="edit-btn" onclick="document.getElementById('picInput').click()">📷</button>
    </div>

    <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
    <p style="color: #888;">🎓 <?php echo htmlspecialchars($user['university_name']); ?></p>

    <div class="stats-row">
        <div class="stat-item">
            <span class="stat-value"><?php echo $f_count; ?></span>
            <span class="stat-label">Followers</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo $fw_count; ?></span>
            <span class="stat-label">Following</span>
        </div>
    </div>

    <form id="picForm" method="POST" enctype="multipart/form-data">
        <input type="file" name="profile_pic" id="picInput" accept="image/*" onchange="document.getElementById('picForm').submit();" style="display:none;">
    </form>
</div>

</body>
</html>