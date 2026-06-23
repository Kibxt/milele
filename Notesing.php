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
    <title>Sell an Item | MILELE</title>
    <style>
        body { background: #050505; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box;}
        .dashboard-card { background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0) 100%); border: 1px solid rgba(255,255,255,0.08); padding: 40px; border-radius: 24px; width: 100%; max-width: 600px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); position: relative; overflow: hidden;}
        .dashboard-card::before { content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(45,212,191,0.1); filter: blur(50px); pointer-events: none;}
        .brand { font-size: 2rem; font-weight: 900; color: #2DD4BF; margin-bottom: 5px; letter-spacing: -1px;}
        .subtitle { color: #888; margin-bottom: 30px; font-size: 0.95rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px;}
        .input-group { margin-bottom: 20px;}
        label { display: block; margin-bottom: 8px; color: #ccc; font-size: 0.9rem; font-weight: bold;}
        .input-field { width: 100%; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); padding: 16px 20px; border-radius: 12px; color: #fff; font-size: 1rem; outline: none; box-sizing: border-box; transition: 0.3s;}
        .input-field:focus { border-color: #2DD4BF; background: rgba(45,212,191,0.02);}
        textarea.input-field { resize: vertical; min-height: 120px; }
        .file-upload-wrapper { background: rgba(0,0,0,0.5); border: 1px dashed rgba(255,255,255,0.2); padding: 20px; border-radius: 12px; text-align: center; color: #888; cursor: pointer; transition: 0.3s; }
        .file-upload-wrapper:hover { border-color: #2DD4BF; color: #fff; }
        .btn-primary { width: 100%; padding: 18px; background: #2DD4BF; color: #000; border: none; border-radius: 12px; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: 0.3s; margin-top: 10px;}
        .btn-primary:hover { background: #fff; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(45,212,191,0.2);}
        .alert-error { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.3); padding: 15px; border-radius: 12px; margin-bottom: 25px; font-size: 0.95rem; text-align: center; font-weight: bold;}
        .alert-success { background: rgba(45,212,191,0.1); color: #2DD4BF; border: 1px solid rgba(45,212,191,0.3); padding: 15px; border-radius: 12px; margin-bottom: 25px; font-size: 0.95rem; text-align: center; font-weight: bold;}
        .nav-links { display: flex; justify-content: space-between; margin-top: 20px; }
        .nav-links a { color: #888; text-decoration: none; font-size: 0.9rem; transition: 0.2s; }
        .nav-links a:hover { color: #2DD4BF; }
    </style>
</head>
<body>

<div class="dashboard-card">
    <div class="brand">Create Listing</div>
    <div class="subtitle">Post an item to the MILELE marketplace.</div>

    <?php if($error) echo "<div class='alert-error'>$error</div>"; ?>
    <?php if($message) echo "<div class='alert-success'>$message</div>"; ?>

    <form method="POST" enctype="multipart/form-data">
        
        <div class="input-group">
            <label>Item Title</label>
            <input type="text" name="title" class="input-field" placeholder="e.g. MacBook Pro M1 2020" required>
        </div>

        <div class="input-group">
            <label>Price (Ksh)</label>
            <input type="number" name="price" class="input-field" placeholder="e.g. 85000" required>
        </div>

        <div class="input-group">
            <label>Category</label>
            <select name="category" class="input-field" required>
                <option value="" disabled selected>Select a Category</option>
                <option value="Electronics">Electronics & Laptops</option>
                <option value="Furniture">Hostel Furniture</option>
                <option value="Books">Textbooks & Notes</option>
                <option value="Services">Services & Tutoring</option>
            </select>
        </div>

        <div class="input-group">
            <label>Description</label>
            <textarea name="description" class="input-field" placeholder="Describe the condition, specs, and any other details..." required></textarea>
        </div>

        <div class="input-group">
            <label>Upload Image</label>
            <div class="file-upload-wrapper" onclick="document.getElementById('image').click();">
                Click to browse for an image (JPG, PNG, WEBP)
                <input type="file" name="image" id="image" accept="image/*" required style="display:none;" onchange="this.parentElement.innerHTML = 'Image Selected: ' + this.files[0].name;">
            </div>
        </div>

        <button type="submit" class="btn-primary">Post to Marketplace</button>
    </form>

    <div class="nav-links">
        <a href="profile.php">&larr; Back to Dashboard</a>
        <a href="logout.php">Log Out</a>
    </div>
</div>

</body>
</html>