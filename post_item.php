<?php
// MILELE - Premium Item Upload Terminal (With 3-Strike System)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS);
    $item_type = filter_input(INPUT_POST, 'item_type', FILTER_SANITIZE_SPECIAL_CHARS);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS);
    $seller_id = $_SESSION['user_id'];
    
    // ==========================================
    // 🛠️ SILENT DATABASE UPGRADES
    // ==========================================
    try { $pdo->exec("ALTER TABLE listings ADD COLUMN item_type VARCHAR(50) DEFAULT 'Physical'"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE listings MODIFY image_path TEXT"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN banned_until DATETIME DEFAULT NULL"); } catch (PDOException $e) {} 
    try { $pdo->exec("ALTER TABLE users ADD COLUMN strike_count INT DEFAULT 0"); } catch (PDOException $e) {} // NEW: Strike Tracker

    $uploaded_urls = [];
    $is_safe = true;

    if (isset($_FILES['images']) && is_array($_FILES['images']['tmp_name'])) {
        $file_count = count($_FILES['images']['tmp_name']);
        
        if ($file_count > 15) {
            $error = "Maximum of 15 images allowed. You selected $file_count.";
            $is_safe = false;
        } else {
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    
                    $tmp_name = $_FILES['images']['tmp_name'][$i];
                    $file_type = $_FILES['images']['type'][$i];
                    $file_name = $_FILES['images']['name'][$i];
                    $image_number = $i + 1;

                    // ==========================================
                    // 🚨 CHECKPOINT 1: SIGHTENGINE FULL SPECTRUM AI
                    // ==========================================
                    $sightengine_user = '1287637059';     
                    $sightengine_secret = 'vVLakzVx9WAHwqvg9o8p9ucggiu5byzJ'; 
                    
                    $ch_ai = curl_init('https://api.sightengine.com/1.0/check.json');
                    curl_setopt($ch_ai, CURLOPT_POST, true);
                    curl_setopt($ch_ai, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch_ai, CURLOPT_SSL_VERIFYPEER, false);
                    
                    $cfile = new CURLFile($tmp_name, $file_type, $file_name);
                    
                    curl_setopt($ch_ai, CURLOPT_POSTFIELDS, array(
                        'models' => 'nudity-2.0,wad,offensive,gore', 
                        'api_user' => $sightengine_user,
                        'api_secret' => $sightengine_secret,
                        'media' => $cfile
                    ));
                    
                    $ai_response_raw = curl_exec($ch_ai);
                    $curl_err = curl_error($ch_ai);
                    curl_close($ch_ai);
                    
                    $ai_result = json_decode($ai_response_raw, true);
                    
                    if ($ai_response_raw === false) {
                        $error = "API Connection Failed on Image #$image_number: " . htmlspecialchars($curl_err);
                        $is_safe = false;
                        break; 
                    } elseif (isset($ai_result['status']) && $ai_result['status'] === 'success') {
                        
                        $weapon_score = isset($ai_result['weapon']) ? $ai_result['weapon'] : (isset($ai_result['wad']['weapon']) ? $ai_result['wad']['weapon'] : 0);
                        $alcohol_score = isset($ai_result['alcohol']) ? $ai_result['alcohol'] : (isset($ai_result['wad']['alcohol']) ? $ai_result['wad']['alcohol'] : 0);
                        $drugs_score = isset($ai_result['drugs']) ? $ai_result['drugs'] : (isset($ai_result['wad']['drugs']) ? $ai_result['wad']['drugs'] : 0);
                        
                        $offensive_score = isset($ai_result['offensive']['prob']) ? $ai_result['offensive']['prob'] : 0;
                        $gore_score = isset($ai_result['gore']['prob']) ? $ai_result['gore']['prob'] : 0;
                        $safe_score = isset($ai_result['nudity']['safe']) ? $ai_result['nudity']['safe'] : (isset($ai_result['nudity']['none']) ? $ai_result['nudity']['none'] : 1);

                        if ($weapon_score > 0.4 || $alcohol_score > 0.4 || $drugs_score > 0.4 || $offensive_score > 0.4 || $gore_score > 0.4 || $safe_score < 0.5) {
                            
                            // ==========================================
                            // 🔨 THE 3-STRIKE LOGIC ENGINE
                            // ==========================================
                            $stmt_strikes = $pdo->prepare("SELECT strike_count FROM users WHERE user_id = :id");
                            $stmt_strikes->execute([':id' => $seller_id]);
                            $user_data = $stmt_strikes->fetch();
                            $current_strikes = $user_data ? (int)$user_data['strike_count'] : 0;
                            $new_strikes = $current_strikes + 1;

                            if ($new_strikes >= 3) {
                                // Strike 3: Ban them and kick them out
                                $pdo->prepare("UPDATE users SET strike_count = :strikes, banned_until = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE user_id = :id")
                                    ->execute([':strikes' => $new_strikes, ':id' => $seller_id]);
                                session_destroy();
                                session_start();
                                $_SESSION['login_error'] = "🚨 ACCOUNT SUSPENDED: You have reached 3 security strikes for prohibited content. Your account is banned for 30 days.";
                                header("Location: login.php");
                                exit();
                            } else {
                                // Strike 1 & 2: Issue a warning but let them stay logged in
                                $pdo->prepare("UPDATE users SET strike_count = :strikes WHERE user_id = :id")
                                    ->execute([':strikes' => $new_strikes, ':id' => $seller_id]);
                                
                                $w_pct = round($weapon_score * 100);
                                $a_pct = round($alcohol_score * 100);
                                $d_pct = round($drugs_score * 100);
                                
                                $error = "<strong style='color:#FCA5A5;'>⚠️ UPLOAD REJECTED (STRIKE $new_strikes/3)</strong><br><br>Image #$image_number triggered our AI security shield:<br>";
                                if ($w_pct > 40) $error .= "🔫 Weapons: {$w_pct}% confidence<br>";
                                if ($a_pct > 40) $error .= "🍺 Alcohol: {$a_pct}% confidence<br>";
                                if ($d_pct > 40) $error .= "💊 Drugs: {$d_pct}% confidence<br>";
                                if ($safe_score < 0.5) $error .= "🔞 Explicit Content detected.<br>";
                                
                                $error .= "<br><strong style='color:#fff;'>If you reach 3 strikes, your account will be suspended.</strong> Please remove the prohibited items from the frame and try again.";
                                $is_safe = false;
                                break; 
                            }
                        }
                    } else {
                        $error = "SIGHTENGINE ERROR on Image #$image_number. Upload aborted.";
                        $is_safe = false;
                        break;
                    }

                    // ==========================================
                    // ☁️ CHECKPOINT 2: IMGBB CLOUD TELEPORTER
                    // ==========================================
                    if ($is_safe) {
                        $imgbb_api_key = '1006ee1ae706c851943f2918cb115ed8'; 
                        $image_base64 = base64_encode(file_get_contents($tmp_name));
                        
                        $ch_cloud = curl_init();
                        curl_setopt($ch_cloud, CURLOPT_URL, 'https://api.imgbb.com/1/upload?key=' . $imgbb_api_key);
                        curl_setopt($ch_cloud, CURLOPT_POST, 1);
                        curl_setopt($ch_cloud, CURLOPT_POSTFIELDS, ['image' => $image_base64]);
                        curl_setopt($ch_cloud, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch_cloud, CURLOPT_SSL_VERIFYPEER, false);
                        
                        $cloud_response = curl_exec($ch_cloud);
                        curl_close($ch_cloud);
                        $cloud_result = json_decode($cloud_response, true);
                        
                        if (isset($cloud_result['data']['url'])) {
                            $uploaded_urls[] = $cloud_result['data']['url']; 
                        } else {
                            $error = "Cloud upload failed on Image #$image_number.";
                            $is_safe = false;
                            break;
                        }
                    }
                }
            }
        }
    } else {
        $error = "Please upload at least one image.";
        $is_safe = false;
    }

    // ==========================================
    // 💾 CHECKPOINT 3: DATABASE SAVE 
    // ==========================================
    if (empty($error) && $is_safe && count($uploaded_urls) > 0) {
        if ($title && $price > 0 && $description && $category && $item_type) {
            try {
                $json_image_path = json_encode($uploaded_urls);

                $stmt = $pdo->prepare("INSERT INTO listings (seller_id, title, category, item_type, description, price, image_path, listing_status, created_at) VALUES (:seller, :title, :category, :type, :desc, :price, :img, 'active', NOW())");
                $stmt->execute([
                    ':seller' => $seller_id,
                    ':title' => $title,
                    ':category' => $category,
                    ':type' => $item_type,
                    ':desc' => $description,
                    ':price' => $price,
                    ':img' => $json_image_path
                ]);
                header("Location: index.php");
                exit();
            } catch (PDOException $e) {
                $error = "Database Error: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $error = "Please fill in all required fields.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell an Item | MILELE</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh;}
        .upload-box { background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.08); padding: 40px; border-radius: 32px; max-width: 500px; width: 100%; box-shadow: 0 24px 48px rgba(0,0,0,0.5); }
        h1 { margin: 0 0 10px 0; font-size: 2rem; text-align: center; color: #fff;}
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; font-size: 0.85rem; color: #888; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .input-wrapper { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 12px 15px; display: flex; align-items: center;}
        .input-wrapper:focus-within { border-color: #2DD4BF; }
        input[type="text"], input[type="number"], select, textarea { flex-grow: 1; background: transparent; border: none; color: #fff; font-size: 1rem; outline: none; font-family: inherit;}
        select option { background: #111; color: #fff; }
        textarea { resize: vertical; min-height: 100px; }
        
        .file-upload-wrapper { position: relative; width: 100%; text-align: center; background: rgba(45,212,191,0.05); border: 2px dashed rgba(45,212,191,0.3); border-radius: 16px; padding: 30px 20px; cursor: pointer; box-sizing: border-box; transition: 0.3s;}
        .file-upload-wrapper:hover { background: rgba(45,212,191,0.1); border-color: #2DD4BF; }
        .file-upload-wrapper input[type="file"] { position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; height: 100%; width: 100%; }
        
        .btn-submit { width: 100%; padding: 16px; background: #2DD4BF; color: #000; border: none; border-radius: 16px; font-weight: bold; font-size: 1.1rem; cursor: pointer; margin-top: 10px; transition: 0.2s;}
        .btn-submit:hover { background: #fff; }
        .btn-cancel { display: block; text-align: center; margin-top: 15px; color: #888; text-decoration: none; }
        
        #loader { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 1000; justify-content: center; align-items: center; flex-direction: column; color: #2DD4BF; font-weight: bold; font-size: 1.2rem; text-align: center;}
        .spinner { border: 4px solid rgba(45,212,191,0.2); border-top: 4px solid #2DD4BF; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin-bottom: 20px;}
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .ai-shield { text-align: center; margin-top: 20px; font-size: 0.8rem; color: #666; display: flex; align-items: center; justify-content: center; gap: 5px;}
    </style>
</head>
<body>

<div id="loader">
    <div class="spinner"></div>
    Processing Batch Upload...<br>
    <span style="font-size: 0.9rem; color: #888; margin-top: 10px; font-weight: normal;">AI Security Scan & Cloud Sync active. Please wait.</span>
</div>

<div class="upload-box">
    <h1>Post an Item</h1>
    
    <?php if ($error) echo "<div style='color:#F87171; text-align:left; margin-bottom:15px; background: rgba(248,113,113,0.1); padding: 20px; border-radius: 12px; border: 1px solid rgba(248,113,113,0.3); font-size: 0.95rem; line-height: 1.5;'>$error</div>"; ?>

    <form action="post_item.php" method="POST" enctype="multipart/form-data" onsubmit="document.getElementById('loader').style.display = 'flex';">
        <div class="input-group">
            <div class="file-upload-wrapper">
                <div style="font-size: 2.5rem; color: #2DD4BF;">📸</div>
                <div id="fileNameDisplay" style="color:#fff; font-weight: bold; margin-top: 10px;">Select up to 15 photos</div>
                <div style="color:#888; font-size: 0.85rem; margin-top: 5px;">Click to browse</div>
                <input type="file" name="images[]" id="imageInput" accept="image/*" multiple required>
            </div>
        </div>

        <div class="input-group">
            <label>Item Name</label>
            <div class="input-wrapper"><input type="text" name="title" required autocomplete="off"></div>
        </div>

        <div class="input-group">
            <label>Item Format</label>
            <div class="input-wrapper">
                <select name="item_type" required>
                    <option value="" disabled selected>Select delivery format...</option>
                    <option value="Physical">📦 Physical Goods (Requires Campus Meetup)</option>
                    <option value="Digital">📄 Digital Goods (Documents / Notes)</option>
                </select>
            </div>
        </div>

        <div class="input-group">
            <label>Category</label>
            <div class="input-wrapper">
                <select name="category" required>
                    <option value="" disabled selected>Select a category...</option>
                    <option value="Electronics">Electronics</option>
                    <option value="Textbooks">Textbooks</option>
                    <option value="Fashion">Fashion & Shoes</option>
                    <option value="Dorm Essentials">Dorm Essentials</option>
                    <option value="Services">Services</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>

        <div class="input-group">
            <label>Selling Price</label>
            <div class="input-wrapper"><span style="color:#2DD4BF; margin-right:10px;">KES</span><input type="number" name="price" required></div>
        </div>

        <div class="input-group">
            <label>Description</label>
            <div class="input-wrapper"><textarea name="description" required placeholder="Describe the item condition or contents..."></textarea></div>
        </div>

        <button type="submit" class="btn-submit">List Item</button>
    </form>
    
    <div class="ai-shield">
        🛡️ Guided by Full-Spectrum AI Vision Security
    </div>
    
    <a href="index.php" class="btn-cancel">Cancel</a>
</div>

<script>
    document.getElementById('imageInput').addEventListener('change', function(e) {
        const fileCount = e.target.files.length;
        const display = document.getElementById('fileNameDisplay');
        
        if (fileCount > 15) {
            alert("You can only upload a maximum of 15 images.");
            e.target.value = '';
            display.textContent = "Select up to 15 photos";
            display.style.color = "#fff";
        } else if (fileCount > 0) {
            display.textContent = fileCount + (fileCount === 1 ? " photo selected" : " photos selected");
            display.style.color = "#2DD4BF";
        } else {
            display.textContent = "Select up to 15 photos";
            display.style.color = "#fff";
        }
    });
</script>
</body>
</html>