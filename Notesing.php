<?php
// MILELE - Premium Creation Studio (Notesing)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ⚡ THE MASTER CONNECTION
require 'db.php';

try {
    // 2. Fetch active user data
    $stmt = $pdo->prepare("SELECT full_name, account_state FROM users WHERE user_id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['account_state'] !== 'active') {
        header("Location: verification_center.php");
        exit();
    }
    
    $creator_name = explode(' ', $user['full_name'])[0];

} catch (PDOException $e) {
    $creator_name = "Creator";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studio | MILELE</title>
    <style>
        /* Ultra-Premium Glass-Forward Aesthetic */
        :root {
            --bg-dark: #000000;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --accent: #2DD4BF;
            --text-main: #FFFFFF;
            --text-muted: #888888;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            /* Subtle mesh gradient background effect */
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(45, 212, 191, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(255, 255, 255, 0.03), transparent 25%);
        }

        .studio-container {
            background: var(--glass-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            border-radius: 32px;
            padding: 3rem;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.4);
        }

        .header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 600;
            letter-spacing: -0.5px;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #fff 0%, #aaa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .error-banner {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.2);
            color: #F87171;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-group label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .input-group input, 
        .input-group select, 
        .input-group textarea {
            width: 100%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            color: var(--text-main);
            padding: 1rem 1.2rem;
            border-radius: 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
            outline: none;
        }

        .input-group input:focus, 
        .input-group select:focus, 
        .input-group textarea:focus {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(45, 212, 191, 0.1);
        }

        .input-group select option {
            background: var(--bg-dark);
            color: var(--text-main);
        }

        /* Custom File Upload Styling */
        input[type="file"] {
            padding: 0.8rem 1rem;
            color: var(--text-muted);
        }

        input[type="file"]::file-selector-button {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            color: var(--text-main);
            cursor: pointer;
            transition: background 0.2s;
            margin-right: 1rem;
        }

        input[type="file"]::file-selector-button:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .btn-primary {
            width: 100%;
            background: var(--text-main);
            color: var(--bg-dark);
            border: none;
            padding: 1.2rem;
            border-radius: 16px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, background 0.2s;
            margin-top: 1rem;
        }

        .btn-primary:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }

        /* Nav back to feed */
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--text-main);
        }
    </style>
</head>
<body>
    
    <div class="studio-container">
        <div class="header">
            <h1>Studio</h1>
            <p>Welcome back, <?php echo htmlspecialchars($creator_name); ?>. Create a new listing.</p>
        </div>

        <?php if(isset($_SESSION['error_msg'])): ?>
            <div class="error-banner">
                <?php echo htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?>
            </div>
        <?php endif; ?>

        <form action="process_listing.php" method="POST" enctype="multipart/form-data" class="premium-form">
            
            <div class="input-group">
                <label>Item Title</label>
                <input type="text" name="title" required placeholder="e.g., Advanced Calculus Notes">
            </div>

            <div class="input-group">
                <label>Price (KES)</label>
                <input type="number" name="price" required placeholder="0.00" min="0" step="0.01">
            </div>

            <div class="input-group">
                <label>Category</label>
                <select name="category" required>
                    <option value="Notes">Academic Notes</option>
                    <option value="Textbooks">Textbooks</option>
                    <option value="Electronics">Electronics</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="input-group">
                <label>Item Type</label>
                <select name="item_type" required>
                    <option value="digital">Digital File (Instant Download)</option>
                    <option value="physical">Physical Item (Escrow Vault)</option>
                </select>
            </div>

            <div class="input-group">
                <label>Upload File / Cover Image</label>
                <input type="file" name="listing_file" required>
            </div>

            <div class="input-group">
                <label>Description</label>
                <textarea name="description" rows="4" required placeholder="Describe your item in detail..."></textarea>
            </div>

            <button type="submit" class="btn-primary">Publish to Global Feed</button>
        </form>
        
        <a href="index.php" class="back-link">← Return to Feed</a>
    </div>

</body>
</html>