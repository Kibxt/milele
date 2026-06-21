<?php
// MILELE - Premium Item Upload Terminal (ImgBB Cloud Integration)

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
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS);
    $seller_id = $_SESSION['user_id'];
    
    $image_path = '';
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        
        // --- MILELE CLOUD IMAGE TELEPORTER ---
        // Replace this with your actual ImgBB API Key
        $imgbb_api_key = '1006ee1ae706c851943f2918cb115ed8'; 
        
        // Grab the raw image data and convert it to base64 for transmission
        $image_base64 = base64_encode(file_get_contents($_FILES['image']['tmp_name']));
        
        // Open a secure connection to the ImgBB Cloud
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload?key=' . $imgbb_api_key);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => $image_base64]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        // Fire the image to the cloud and catch the response
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['data']['url'])) {
            // SUCCESS! Grab the permanent cloud URL
            $image_path = $result['data']['url']; 
        } else {
            $error = "Cloud upload failed. Please try a different photo.";
        }
    }

    if (empty($error)) {
        if ($title && $price > 0 && $description && $category && $image_path) {
            try {
                // Save the permanent Cloud URL to your database
                $stmt = $pdo->prepare("INSERT INTO listings (seller_id, title, category, description, price, image_path, listing_status, created_at) VALUES (:seller, :title, :category, :desc, :price, :img, 'active', NOW())");
                $stmt->execute([
                    ':seller' => $seller_id,
                    ':title' => $title,
                    ':category' => $category,
                    ':desc' => $description,
                    ':price' => $price,
                    ':img' => $image_path
                ]);
                header("Location: index.php");
                exit();
            } catch (PDOException $e) {
                $error = "Database Error: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $error = "Please fill in all required fields and ensure the image uploads.";
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
        .file-upload-wrapper { position: relative; width: 100%; text-align: center; background: rgba(45,212,191,0.05); border: 2px dashed rgba(45,212,191,0.3); border-radius: 16px; padding: 30px 20px; cursor: pointer; box-sizing: border-box;}
        .file-upload-wrapper input[type="file"] { position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; height: 100%; width: 100%; }
        .btn-submit { width: 100%; padding: 16px; background: #2DD4BF; color: #000; border: none; border-radius: 16px; font-weight: bold; font-size: 1.1rem; cursor: pointer; margin-top: 10px;}
        .btn-cancel { display: block; text-align: center; margin-top: 15px; color: #888; text-decoration: none; }
        
        /* Loading Overlay */
        #loader { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 1000; justify-content: center; align-items: center; flex-direction: column; color: #2DD4BF; font-weight: bold; font-size: 1.2rem;}
        .spinner { border: 4px solid rgba(45,212,191,0.2); border-top: 4px solid #2DD4BF; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin-bottom: 20px;}
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div id="loader">
    <div class="spinner"></div>
    Uploading securely to cloud...
</div>

<div class="upload-box">
    <h1>Post an Item</h1>
    <?php if ($error) echo "<div style='color:#F87171; text-align:center; margin-bottom:15px; background: rgba(248,113,113,0.1); padding: 10px; border-radius: 10px;'>$error</div>"; ?>

    <form action="post_item.php" method="POST" enctype="multipart/form-data" onsubmit="document.getElementById('loader').style.display = 'flex';">
        <div class="input-group">
            <div class="file-upload-wrapper">
                <div style="font-size: 2.5rem; color: #2DD4BF;">📸</div>
                <div id="fileNameDisplay" style="color:#888;">Click to upload item photo</div>
                <input type="file" name="image" id="imageInput" accept="image/*" required>
            </div>
        </div>

        <div class="input-group">
            <label>Item Name</label>
            <div class="input-wrapper"><input type="text" name="title" required></div>
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
            <div class="input-wrapper"><textarea name="description" required></textarea></div>
        </div>

        <button type="submit" class="btn-submit">List Item</button>
    </form>
    <a href="index.php" class="btn-cancel">Cancel</a>
</div>

<script>
    document.getElementById('imageInput').addEventListener('change', function(e) {
        document.getElementById('fileNameDisplay').textContent = e.target.files[0] ? e.target.files[0].name : "Click to upload item photo";
        document.getElementById('fileNameDisplay').style.color = "#2DD4BF";
    });
</script>
</body>
</html>