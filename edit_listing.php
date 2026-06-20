<?php
// MILELE - Premium Edit Studio

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$listing_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];

if (!$listing_id) {
    header("Location: profile.php");
    exit();
}

try {
    // Ensure the user actually owns this item before letting them edit it
    $stmt = $pdo->prepare("SELECT * FROM listings WHERE listing_id = :id AND seller_id = :seller AND listing_status != 'deleted'");
    $stmt->execute([':id' => $listing_id, ':seller' => $user_id]);
    $item = $stmt->fetch();

    if (!$item) {
        header("Location: profile.php");
        exit();
    }
} catch (PDOException $e) {
    die("System error loading item data.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Listing | MILELE</title>
    <style>
        /* Shared Notesing Studio Aesthetic */
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 2rem; background-image: radial-gradient(circle at 15% 50%, rgba(45, 212, 191, 0.05), transparent 25%); margin: 0; }
        .studio-container { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 32px; padding: 3rem; width: 100%; max-width: 600px; box-shadow: 0 24px 48px rgba(0, 0, 0, 0.4); }
        .header { text-align: center; margin-bottom: 2.5rem; }
        .header h1 { font-size: 2rem; margin-bottom: 0.5rem; color: #2DD4BF; }
        .header p { color: #888; font-size: 0.95rem; }
        
        .input-group { margin-bottom: 1.5rem; }
        .input-group label { display: block; font-size: 0.85rem; color: #888; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 1px; }
        .input-group input, .input-group select, .input-group textarea { width: 100%; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); color: #fff; padding: 1rem 1.2rem; border-radius: 16px; font-size: 1rem; transition: all 0.3s ease; outline: none; box-sizing: border-box; }
        .input-group input:focus, .input-group select:focus, .input-group textarea:focus { border-color: #2DD4BF; background: rgba(255, 255, 255, 0.05); }
        .input-group select option { background: #000; color: #fff; }

        input[type="file"] { padding: 0.8rem 1rem; color: #888; }
        input[type="file"]::file-selector-button { background: rgba(255, 255, 255, 0.1); border: none; padding: 0.5rem 1rem; border-radius: 8px; color: #fff; cursor: pointer; margin-right: 1rem; }

        .file-notice { font-size: 0.8rem; color: #2DD4BF; margin-top: 5px; }

        .btn-primary { width: 100%; background: #fff; color: #000; border: none; padding: 1.2rem; border-radius: 16px; font-size: 1.05rem; font-weight: bold; cursor: pointer; transition: 0.2s; margin-top: 1rem; }
        .btn-primary:hover { background: #2DD4BF; transform: translateY(-2px); }
        .back-link { display: block; text-align: center; margin-top: 1.5rem; color: #888; text-decoration: none; font-size: 0.9rem; transition: 0.2s; }
        .back-link:hover { color: #fff; }
    </style>
</head>
<body>
    
    <div class="studio-container">
        <div class="header">
            <h1>Update Listing</h1>
            <p>Modify the details of your active item.</p>
        </div>

        <form action="edit_process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="listing_id" value="<?php echo $item['listing_id']; ?>">
            
            <div class="input-group">
                <label>Item Title</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($item['title']); ?>" required>
            </div>

            <div class="input-group">
                <label>Price (KES)</label>
                <input type="number" name="price" value="<?php echo htmlspecialchars($item['price']); ?>" required min="0" step="0.01">
            </div>

            <div class="input-group">
                <label>Category</label>
                <select name="category" required>
                    <option value="Notes" <?php if($item['category'] == 'Notes') echo 'selected'; ?>>Academic Notes</option>
                    <option value="Textbooks" <?php if($item['category'] == 'Textbooks') echo 'selected'; ?>>Textbooks</option>
                    <option value="Electronics" <?php if($item['category'] == 'Electronics') echo 'selected'; ?>>Electronics</option>
                    <option value="Other" <?php if($item['category'] == 'Other') echo 'selected'; ?>>Other</option>
                </select>
            </div>

            <div class="input-group">
                <label>Update File / Image (Optional)</label>
                <input type="file" name="listing_file">
                <div class="file-notice">Leave blank to keep your current image/file.</div>
            </div>

            <div class="input-group">
                <label>Description</label>
                <textarea name="description" rows="4" required><?php echo htmlspecialchars($item['description']); ?></textarea>
            </div>

            <button type="submit" class="btn-primary">Save Changes</button>
        </form>
        
        <a href="profile.php" class="back-link">Cancel & Return to Profile</a>
    </div>

</body>
</html>