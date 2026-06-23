<?php
// MILELE - Secure Item Listing Engine

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

// 1. THE BOUNCER: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim(filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS));
    $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS));
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $category = trim(filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS));
    
    // 2. THE INSPECTOR: Handle Image Upload Safely
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_name = $_FILES['image']['name'];
        $file_size = $_FILES['image']['size'];
        
        // Extract extension and check if it's an image
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (!in_array($file_ext, $allowed_exts)) {
            $error = "Invalid file type. Only JPG, PNG, and WEBP are allowed.";
        } elseif ($file_size > 5000000) { // 5MB limit
            $error = "Image is too large. Maximum size is 5MB.";
        } else {
            // Generate a secure, unique filename to prevent overwrites
            $new_file_name = uniqid('milele_') . '.' . $file_ext;
            $upload_path = 'uploads/' . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // 3. THE WRITER: Save to Database
                try {
                    $stmt = $pdo->prepare("INSERT INTO listings (seller_id, title, description, price, category, image_path) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$seller_id, $title, $description, $price, $category, $upload_path]);
                    
                    $message = "Listing created successfully!";
                } catch (PDOException $e) {
                    $error = "Database Error: " . htmlspecialchars($e->getMessage());
                }
            } else {
                $error = "Failed to upload image to the server.";
            }
        }
    } else {
        $error = "Please upload an image of your item.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post an Item | MILELE</title>
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
        body { background: var(--chalk); color: var(--indigo); font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 40px 20px; }
        
        .dashboard-card { background: var(--white); border: 1px solid var(--card-border); padding: 40px; border-radius: 24px; width: 100%; max-width: 600px; box-shadow: 0 20px 60px rgba(26,16,64,0.06); }
        .brand { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; color: var(--indigo); margin-bottom: 5px; }
        .subtitle { color: var(--slate); margin-bottom: 30px; font-size: 14px; border-bottom: 1px solid var(--card-border); padding-bottom: 20px; }
        
        .input-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: var(--indigo); font-size: 14px; font-weight: 600; }
        
        .input-field { width: 100%; border: 2px solid var(--card-border); padding: 14px 20px; border-radius: 12px; color: var(--indigo); font-size: 15px; font-family: 'Inter', sans-serif; background: var(--chalk); outline: none; transition: 0.2s; }
        .input-field:focus { border-color: var(--amber); box-shadow: 0 0 0 4px rgba(245,166,35,0.12); background: var(--white); }
        textarea.input-field { resize: vertical; min-height: 120px; border-radius: 16px; }
        
        .file-upload-wrapper { background: var(--chalk); border: 2px dashed var(--card-border); padding: 30px 20px; border-radius: 16px; text-align: center; color: var(--slate); cursor: pointer; transition: 0.3s; font-size: 14px; font-weight: 500; }
        .file-upload-wrapper:hover { border-color: var(--amber); background: rgba(245,166,35,0.05); color: var(--indigo); }
        .file-upload-icon { font-size: 32px; margin-bottom: 10px; display: block; }
        
        .btn-primary { width: 100%; padding: 16px; background: var(--amber); color: var(--indigo); border: none; border-radius: 50px; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.2s; font-family: 'Inter', sans-serif; box-shadow: 0 4px 20px rgba(245,166,35,0.3); margin-top: 10px; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(245,166,35,0.45); }
        
        .alert-error { background: rgba(255,107,107,0.1); color: var(--coral); border: 1px solid rgba(255,107,107,0.2); padding: 14px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; text-align: center; font-weight: 600; }
        .alert-success { background: rgba(0,212,170,0.1); color: #059669; border: 1px solid rgba(0,212,170,0.2); padding: 14px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; text-align: center; font-weight: 600; }
        
        .nav-links { display: flex; justify-content: space-between; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--card-border); }
        .nav-links a { color: var(--slate); text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.2s; }
        .nav-links a:hover { color: var(--indigo); }
        .nav-links .danger { color: var(--coral); }
        .nav-links .danger:hover { opacity: 0.8; }
    </style>
</head>
<body>

<div class="dashboard-card">
    <div class="brand">Create Listing</div>
    <div class="subtitle">Turn your campus stuff into cash.</div>

    <?php if($error) echo "<div class='alert-error'>$error</div>"; ?>
    <?php if($message) echo "<div class='alert-success'>$message</div>"; ?>

    <form method="POST" enctype="multipart/form-data">
        
        <div class="input-group">
            <label>What are you selling?</label>
            <input type="text" name="title" class="input-field" placeholder="e.g. MacBook Pro M1 2020" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="input-group">
                <label>Price (KES)</label>
                <input type="number" name="price" class="input-field" placeholder="e.g. 85000" required>
            </div>

            <div class="input-group">
                <label>Category</label>
                <select name="category" class="input-field" required style="cursor: pointer; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg fill=\"%238B7FA8\" height=\"24\" viewBox=\"0 0 24 24\" width=\"24\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M7 10l5 5 5-5z\"/></svg>'); background-repeat: no-repeat; background-position: right 16px center;">
                    <option value="" disabled selected>Select...</option>
                    <option value="Electronics">Electronics</option>
                    <option value="Furniture">Furniture</option>
                    <option value="Books">Textbooks</option>
                    <option value="Clothes">Clothes</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>

        <div class="input-group">
            <label>Description</label>
            <textarea name="description" class="input-field" placeholder="Describe the condition, specs, and any other details buyers should know..." required></textarea>
        </div>

        <div class="input-group">
            <label>Upload Photos</label>
            <div class="file-upload-wrapper" onclick="document.getElementById('image').click();">
                <span class="file-upload-icon">📸</span>
                <span id="upload-text">Click to browse for an image (JPG, PNG)</span>
                <input type="file" name="image" id="image" accept="image/*" required style="display:none;" onchange="document.getElementById('upload-text').innerHTML = '<strong>Selected:</strong> ' + this.files[0].name; this.parentElement.style.borderColor = 'var(--mint)'; this.parentElement.style.background = 'rgba(0,212,170,0.05)';">
            </div>
        </div>

        <button type="submit" class="btn-primary">Post to Marketplace</button>
    </form>

    <div class="nav-links">
        <a href="index.php">&larr; Back to Feed</a>
        <a href="profile.php">Dashboard</a>
        <a href="logout.php" class="danger">Log Out</a>
    </div>
</div>

</body>
</html>