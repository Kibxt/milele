<?php
// MILELE - Premium Profile Dashboard (V4 Complete Dashboard)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$user_id = $_SESSION['user_id'];

try {
    // 1. User Details
    $stmt = $pdo->prepare("SELECT full_name, email, university_name, completed_escrows, created_at FROM users WHERE user_id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();

    // 2. Inventory
    $stmt_listings = $pdo->prepare("SELECT * FROM listings WHERE seller_id = :id AND listing_status != 'deleted' ORDER BY created_at DESC");
    $stmt_listings->execute([':id' => $user_id]);
    $my_listings = $stmt_listings->fetchAll();

    // 3. Purchase History
    $stmt_history = $pdo->prepare("
        SELECT t.*, l.title, u.full_name as seller_name 
        FROM escrow_transactions t
        JOIN listings l ON t.listing_id = l.listing_id
        JOIN users u ON t.seller_id = u.user_id
        WHERE t.buyer_id = :id
        ORDER BY t.created_at DESC
    ");
    $stmt_history->execute([':id' => $user_id]);
    $purchase_history = $stmt_history->fetchAll();

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System error loading profile.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | MILELE</title>
    <style>
        /* Shared Glass Aesthetic */
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .nav-bar h1 { color: #fff; margin: 0; font-size: 2rem; }
        .nav-buttons { display: flex; gap: 15px; }
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; transition: 0.3s; font-size: 0.9rem; }
        .btn-glass:hover { background: rgba(255,255,255,0.1); }
        .btn-accent { background: #2DD4BF; color: #000; border: none; font-weight: bold; }
        .btn-accent:hover { background: #fff; }
        .btn-danger { color: #F87171; border-color: rgba(248,113,113,0.3); }
        .btn-danger:hover { background: rgba(248,113,113,0.1); }

        .profile-card { background: rgba(255,255,255,0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.08); padding: 30px; border-radius: 24px; margin-bottom: 40px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;}
        .profile-info h2 { margin: 0 0 5px 0; color: #2DD4BF; }
        .profile-info p { margin: 0; color: #888; font-size: 0.95rem; }
        .stats-box { text-align: center; background: rgba(0,0,0,0.5); padding: 15px 30px; border-radius: 16px; border: 1px solid rgba(45,212,191,0.2); }
        .stats-box h3 { margin: 0; font-size: 2rem; color: #fff; }
        .stats-box span { font-size: 0.8rem; color: #2DD4BF; text-transform: uppercase; letter-spacing: 1px; }

        .section-title { font-size: 1.2rem; color: #888; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; margin-top: 40px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
        
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .item-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 16px; transition: 0.3s; display: flex; flex-direction: column; }
        .item-card:hover { border-color: rgba(45,212,191,0.3); transform: translateY(-3px); }
        .item-title { font-weight: bold; margin-bottom: 10px; font-size: 1.1rem; }
        .item-price { color: #2DD4BF; margin-bottom: 15px; }
        
        .card-controls { display: flex; gap: 10px; margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.05); }
        .btn-edit { flex: 1; text-align: center; padding: 8px; background: rgba(45,212,191,0.1); color: #2DD4BF; border-radius: 8px; text-decoration: none; font-size: 0.85rem; transition: 0.2s; }
        .btn-edit:hover { background: #2DD4BF; color: #000; }
        .btn-delete { flex: 1; text-align: center; padding: 8px; background: rgba(248,113,113,0.1); color: #F87171; border-radius: 8px; border: none; cursor: pointer; font-size: 0.85rem; transition: 0.2s; font-family: inherit;}
        .btn-delete:hover { background: #F87171; color: #000; }

        .history-section { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; padding: 30px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { color: #666; padding-bottom: 15px; font-size: 0.85rem; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.1); }
        td { padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.05); color: #ccc; }
        .code-box { background: rgba(0,0,0,0.5); border: 1px solid rgba(45,212,191,0.3); padding: 5px 10px; border-radius: 6px; color: #2DD4BF; font-family: monospace; font-weight: bold; letter-spacing: 2px; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; }
        .status-funded { background: rgba(251, 191, 36, 0.1); color: #FBBF24; }
        .status-released { background: rgba(45, 212, 191, 0.1); color: #2DD4BF; }

        /* Premium Custom Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 1000; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-box { background: rgba(20,20,20,0.95); border: 1px solid rgba(255,255,255,0.1); border-radius: 24px; padding: 40px; max-width: 400px; text-align: center; box-shadow: 0 24px 48px rgba(0,0,0,0.5); transform: translateY(20px); transition: transform 0.3s ease; }
        .modal-overlay.active .modal-box { transform: translateY(0); }
        .modal-icon { font-size: 3rem; margin-bottom: 15px; }
        .modal-title { font-size: 1.5rem; margin-bottom: 10px; color: #fff; }
        .modal-text { color: #888; margin-bottom: 30px; font-size: 0.95rem; line-height: 1.5; }
        .modal-actions { display: flex; gap: 15px; }
        .btn-cancel { flex: 1; padding: 12px; background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .btn-cancel:hover { background: rgba(255,255,255,0.1); }
        .btn-confirm-del { flex: 1; padding: 12px; background: #F87171; color: #000; border: none; border-radius: 12px; cursor: pointer; font-weight: bold; text-decoration: none; transition: 0.2s; }
        .btn-confirm-del:hover { background: #fff; }
    </style>
</head>
<body>

<div class="container">
    <div class="nav-bar">
        <h1>My Profile</h1>
        <div class="nav-buttons">
            <a href="index.php" class="btn-glass btn-accent">← Return to Market</a>
            <a href="payout.php" class="btn-glass" style="background: rgba(45,212,191,0.1); color: #2DD4BF; border-color: rgba(45,212,191,0.2);">Payouts</a>
            <a href="logout.php" class="btn-glass btn-danger">Logout</a>
        </div>
    </div>

    <div class="profile-card">
        <div class="profile-info">
            <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
            <p><?php echo htmlspecialchars($user['email']); ?></p>
            <p style="margin-top: 10px;">🎓 <?php echo htmlspecialchars($user['university_name']); ?></p>
        </div>
        <div class="stats-box">
            <h3><?php echo (int)$user['completed_escrows']; ?></h3>
            <span>Successful Deals</span>
        </div>
    </div>

    <div class="section-title">My Market Inventory</div>
    
    <?php if (empty($my_listings)): ?>
        <p style="color: #666; text-align: center; padding: 20px; background: rgba(255,255,255,0.02); border-radius: 16px;">You haven't posted any items yet.</p>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($my_listings as $item): ?>
                <div class="item-card">
                    <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div class="item-price">KES <?php echo number_format($item['price'], 2); ?></div>
                    <div class="card-controls">
                        <a href="edit_listing.php?id=<?php echo $item['listing_id']; ?>" class="btn-edit">Edit</a>
                        <button onclick="openModal(<?php echo $item['listing_id']; ?>)" class="btn-delete">Remove</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section-title" style="margin-top: 60px;">Purchase History & Vault Codes</div>
    
    <div class="history-section">
        <?php if (empty($purchase_history)): ?>
            <p style="color: #666; text-align: center;">You haven't made any purchases yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Seller</th>
                        <th>Amount Paid</th>
                        <th>Vault Code (PIN)</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchase_history as $deal): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($deal['created_at'])); ?></td>
                            <td style="color: #fff; font-weight: bold;"><?php echo htmlspecialchars($deal['title']); ?></td>
                            <td><?php echo htmlspecialchars(explode(' ', $deal['seller_name'])[0]); ?></td>
                            <td>KES <?php echo number_format($deal['total_amount'], 2); ?></td>
                            <td>
                                <?php if ($deal['transaction_status'] === 'funded' && !empty($deal['escrow_pin'])): ?>
                                    <span class="code-box"><?php echo htmlspecialchars($deal['escrow_pin']); ?></span>
                                <?php else: ?>
                                    <span style="color: #666;">Cleared / N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($deal['transaction_status'] === 'funded'): ?>
                                    <span class="status-badge status-funded">Locked</span>
                                <?php else: ?>
                                    <span class="status-badge status-released">Released</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($deal['transaction_status'] === 'funded'): ?>
                                    <button onclick="openGuideModal()" style="background:none; border:1px solid rgba(255,255,255,0.2); color:#ccc; padding:6px 12px; border-radius:8px; cursor:pointer; font-size:0.8rem; transition:0.2s;">ℹ️ Guide</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">🗑️</div>
        <div class="modal-title">Remove Item?</div>
        <div class="modal-text">This will hide your item from the global marketplace. Past receipts will be preserved. This action cannot be undone.</div>
        <div class="modal-actions">
            <button onclick="closeModal()" class="btn-cancel">Cancel</button>
            <a href="#" id="confirmDeleteBtn" class="btn-confirm-del">Yes, Remove it</a>
        </div>
    </div>
</div>

<div class="modal-overlay" id="guideModal">
    <div class="modal-box" style="text-align: left;">
        <div class="modal-title" style="text-align: center; color: #2DD4BF;">How Escrow Works</div>
        <p style="color: #888; font-size: 0.9rem; text-align: center; margin-bottom: 20px;">Your money is safe. The seller cannot access it until you provide your 4-digit PIN.</p>
        
        <div style="margin-bottom: 15px;">
            <strong style="color: #fff;">1. Meet Up:</strong> <span style="color: #ccc; font-size: 0.9rem;">Coordinate a safe location using the Inbox.</span>
        </div>
        <div style="margin-bottom: 15px;">
            <strong style="color: #fff;">2. Inspect:</strong> <span style="color: #ccc; font-size: 0.9rem;">Check the item to make sure it matches the listing exactly.</span>
        </div>
        <div style="margin-bottom: 25px;">
            <strong style="color: #fff;">3. Handover PIN:</strong> <span style="color: #ccc; font-size: 0.9rem;">Only give the seller your Vault PIN when you have the item in your hands. This releases the money to them.</span>
        </div>
        
        <button onclick="closeGuideModal()" class="btn-cancel" style="width: 100%;">Understood</button>
    </div>
</div>

<script>
    // Delete Modal Logic
    const deleteModal = document.getElementById('deleteModal');
    const confirmBtn = document.getElementById('confirmDeleteBtn');

    function openModal(listingId) {
        confirmBtn.href = "delete_listing.php?id=" + listingId;
        deleteModal.classList.add('active');
    }

    function closeModal() {
        deleteModal.classList.remove('active');
        setTimeout(() => confirmBtn.href = "#", 300);
    }

    // Guide Modal Logic
    const guideModal = document.getElementById('guideModal');
    
    function openGuideModal() {
        guideModal.classList.add('active');
    }
    
    function closeGuideModal() {
        guideModal.classList.remove('active');
    }

    // Close modals if clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === deleteModal) closeModal();
        if (e.target === guideModal) closeGuideModal();
    });
</script>

</body>
</html>