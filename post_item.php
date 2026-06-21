<?php
// MILELE - Premium Item Upload Terminal

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS);
    $seller_id = $_SESSION['user_id'];
    
    // Handle the Image Upload
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        
        // Create the uploads directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate a unique, safe file name
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid('milele_') . '.' . $file_extension;
        $target_file = $upload_dir . $new_filename;

        // Ensure it's actually an image
        $check = getimagesize($_FILES['image']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = $target_file;
            } else {
                $error = "Failed to save the image to the server.";
            }
        } else {
            $error = "File is not a valid image.";
        }
    }

    // Insert into database if there are no errors
    if (empty($error)) {
        if ($title && $price > 0 && $description) {
            try {
                $stmt = $pdo->prepare("INSERT INTO listings (seller_id, title, description, price, image_path, listing_status, created_at) VALUES (:seller, :title, :desc, :price, :img, 'active', NOW())");
                $stmt->execute([
                    ':seller' => $seller_id,
                    ':title' => $title,
                    ':desc' => $description,
                    ':price' => $price,
                    ':img' => $image_path
                ]);
                
                $_SESSION['upload_success'] = "Item successfully posted to the marketplace!";
                header("Location: profile.php");
                exit();
                
            } catch (PDOException $e) {
                $error = "Database Error: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $error = "Please fill in all required fields correctly.";
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
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; background-image: radial-gradient(circle at 50% 0%, rgba(45, 212, 191, 0.05), transparent 60%); display: flex; justify-content: center; align-items: center; min-height: 100vh;}
        
        .upload-box { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.08); padding: 40px; border-radius: 32px; max-width: 500px; width: 100%; box-shadow: 0 24px 48px rgba(0,0,0,0.5); }
        
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { margin: 0 0 10px 0; font-size: 2rem; color: #fff;}
        .header p { color: #888; font-size: 0.95rem; margin: 0; }

        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; font-size: 0.85rem; color: #888; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
        
        .input-wrapper { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 12px 15px; transition: 0.3s; display: flex; align-items: center;}
        .input-wrapper:focus-within { border-color: #2DD4BF; background: rgba(255, 255, 255, 0.08); }
        
        input[type="text"], input[type="number"], textarea { flex-grow: 1; background: transparent; border: none; color: #fff; font-size: 1rem; outline: none; font-family: inherit;}
        textarea { resize: vertical; min-height: 100px; }
        
        .prefix { color: #2DD4BF; font-weight: bold; margin-right: 10px; font-size: 1.1rem; }

        /* Custom File Upload Styling */
        .file-upload-wrapper { position: relative; overflow: hidden; display: inline-block; width: 100%; text-align: center; background: rgba(45,212,191,0.05); border: 2px dashed rgba(45,212,191,0.3); border-radius: 16px; padding: 30px 20px; transition: 0.3s; box-sizing: border-box; cursor: pointer;}
        .file-upload-wrapper:hover { background: rgba(45,212,191,0.1); border-color: #2DD4BF; }
        .file-upload-wrapper input[type="file"] { font-size: 100px; position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; height: 100%; }
        .file-icon { font-size: 2.5rem; margin-bottom: 10px; color: #2DD4BF; }
        .file-text { color: #888; font-size: 0.9rem; }

        .btn-submit { width: 100%; padding: 16px; background: #2DD4BF; color: #000; border: none; border-radius: 16px; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: 0.2s; margin-top: 10px;}
        .btn-submit:hover { background: #fff; transform: translateY(-2px); }
        
        .btn-cancel { display: block; text-align: center; margin-top: 15px; color: #888; text-decoration: none; font-size: 0.9rem; transition: 0.2s; }
        .btn-cancel:hover { color: #fff; }

        .msg-error { padding: 15px; background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.2); border-radius: 12px; margin-bottom: 20px; text-align: center; font-size: 0.9rem;}
    </style>
</head>
<body>

<div class="upload-box">
    <div class="header">
        <h1>Post an Item</h1>
        <p>List your item securely on the campus marketplace.</p>
    </div>

    <?php if ($error): ?>
        <div class="msg-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="post_item.php" method="POST" enctype="multipart/form-data">
        
        <div class="input-group" style="margin-bottom: 30px;">
            <div class="file-upload-wrapper">
                <div class="file-icon">📸</div>
                <div class="file-text" id="fileNameDisplay">Click to upload a clear photo of your item</div>
                <input type="file" name="image" id="imageInput" accept="image/jpeg, image/png, image/webp" required>
            </div>
        </div>

        <div class="input-group">
            <label>Item Name</label>
            <div class="input-wrapper">
                <input type="text" name="title" placeholder="e.g. MacBook Pro M1" required autocomplete="off">
            </div>
        </div>

        <div class="input-group">
            <label>Selling Price</label>
            <div class="input-wrapper">
                <span class="prefix">KES</span>
                <input type="number" name="price" placeholder="0.00" min="1" step="0.01" required>
            </div>
        </div>

        <div class="input-group">
            <label>Description</label>
            <div class="input-wrapper">
                <textarea name="description" placeholder="Describe the condition, any flaws, and details buyers should know..." required></textarea>
            </div>
        </div>

        <button type="submit" class="btn-submit">List Item on Market</button>
    </form>
    
    <a href="index.php" class="btn-cancel">Cancel</a>
</div>

<script>
    // Simple script to show the user the name of the file they selected
    document.getElementById('imageInput').addEventListener('change', function(e) {
        var fileName = e.target.files[0] ? e.target.files[0].name : "Click to upload a clear photo of your item";
        document.getElementById('fileNameDisplay').textContent = fileName;
        document.getElementById('fileNameDisplay').style.color = "#2DD4BF";
    });
</script>

</body>
</html>