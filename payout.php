<?php
// MILELE - Seller Payout & Escrow Claim Dashboard

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$user_id = $_SESSION['user_id'];

try {
    // Fetch all escrow transactions where this user is the SELLER
    $stmt = $pdo->prepare("
        SELECT t.*, l.title, u.full_name as buyer_name 
        FROM escrow_transactions t
        JOIN listings l ON t.listing_id = l.listing_id
        JOIN users u ON t.buyer_id = u.user_id
        WHERE t.seller_id = :id
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([':id' => $user_id]);
    $sales = $stmt->fetchAll();

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System error loading payouts.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payouts | MILELE</title>
    <style>
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        
        .nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .nav-bar h1 { color: #fff; margin: 0; font-size: 2rem; }
        .btn-glass { padding: 10px 20px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; transition: 0.3s; font-size: 0.9rem; }
        .btn-glass:hover { background: rgba(255,255,255,0.1); }

        .sales-section { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; padding: 30px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { color: #666; padding-bottom: 15px; font-size: 0.85rem; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.1); }
        td { padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.05); color: #ccc; }
        
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; font-weight: bold;}
        .status-funded { background: rgba(251, 191, 36, 0.1); color: #FBBF24; }
        .status-released { background: rgba(45, 212, 191, 0.1); color: #2DD4BF; }
        
        .btn-claim { background: #2DD4BF; color: #000; padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; font-weight: bold; font-size: 0.85rem; transition: 0.2s; }
        .btn-claim:hover { background: #fff; }

        /* Error/Success Messages */
        .msg { padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; }
        .msg-error { background: rgba(248,113,113,0.1); color: #F87171; border: 1px solid rgba(248,113,113,0.2); }
        .msg-success { background: rgba(45,212,191,0.1); color: #2DD4BF; border: 1px solid rgba(45,212,191,0.2); }

        /* Custom Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 1000; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-box { background: rgba(20,20,20,0.95); border: 1px solid rgba(255,255,255,0.1); border-radius: 24px; padding: 40px; max-width: 400px; width: 100%; text-align: center; box-shadow: 0 24px 48px rgba(0,0,0,0.5); transform: translateY(20px); transition: transform 0.3s ease; }
        .modal-overlay.active .modal-box { transform: translateY(0); }
        
        .pin-input { width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(45,212,191,0.5); color: #2DD4BF; font-size: 2rem; padding: 15px; text-align: center; border-radius: 16px; letter-spacing: 10px; margin-bottom: 20px; outline: none; }
        .pin-input:focus { border-color: #2DD4BF; background: rgba(255,255,255,0.08); }

        .btn-submit-pin { width: 100%; padding: 14px; background: #2DD4BF; color: #000; border: none; border-radius: 12px; font-weight: bold; font-size: 1.05rem; cursor: pointer; transition: 0.2s; margin-bottom: 10px;}
        .btn-submit-pin:hover { background: #fff; }
        .btn-cancel { width: 100%; padding: 12px; background: transparent; color: #888; border: none; cursor: pointer; transition: 0.2s; }
        .btn-cancel:hover { color: #fff; }
    </style>
</head>
<body>

<div class="container">
    <div class="nav-bar">
        <h1>Seller Payouts</h1>
        <a href="profile.php" class="btn-glass">← Back to Profile</a>
    </div>

    <?php if (isset($_SESSION['payout_error'])): ?>
        <div class="msg msg-error"><?php echo $_SESSION['payout_error']; unset($_SESSION['payout_error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['payout_success'])): ?>
        <div class="msg msg-success"><?php echo $_SESSION['payout_success']; unset($_SESSION['payout_success']); ?></div>
    <?php endif; ?>

    <div class="sales-section">
        <?php if (empty($sales)): ?>
            <p style="color: #666; text-align: center;">You have not made any sales yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Buyer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></td>
                            <td style="color: #fff; font-weight: bold;"><?php echo htmlspecialchars($sale['title']); ?></td>
                            <td><?php echo htmlspecialchars(explode(' ', $sale['buyer_name'])[0]); ?></td>
                            <td style="color: #2DD4BF;">KES <?php echo number_format($sale['total_amount'], 2); ?></td>
                            <td>
                                <?php if ($sale['transaction_status'] === 'funded'): ?>
                                    <span class="status-badge status-funded">Locked</span>
                                <?php else: ?>
                                    <span class="status-badge status-released">Cleared</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($sale['transaction_status'] === 'funded'): ?>
                                    <button onclick="openClaimModal(<?php echo $sale['transaction_id']; ?>)" class="btn-claim">Claim Funds</button>
                                <?php else: ?>
                                    <span style="color: #666; font-size: 0.85rem;">Released to You</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="claimModal">
    <div class="modal-box">
        <h2 style="margin: 0 0 10px 0; color: #fff;">Enter Vault PIN</h2>
        <p style="color: #888; font-size: 0.9rem; margin-bottom: 25px;">Ask the buyer for their 4-digit Escrow PIN to release these funds into your account.</p>
        
        <form action="process_payout.php" method="POST">
            <input type="hidden" name="transaction_id" id="modalTxId" value="">
            <input type="text" name="escrow_pin" class="pin-input" maxlength="4" placeholder="••••" required autocomplete="off" pattern="\d{4}" title="Please enter a 4-digit PIN">
            
            <button type="submit" class="btn-submit-pin">Verify & Claim</button>
            <button type="button" onclick="closeClaimModal()" class="btn-cancel">Cancel</button>
        </form>
    </div>
</div>

<script>
    const claimModal = document.getElementById('claimModal');
    const modalTxId = document.getElementById('modalTxId');
    const pinInput = document.querySelector('.pin-input');

    function openClaimModal(txId) {
        modalTxId.value = txId;
        claimModal.classList.add('active');
        setTimeout(() => pinInput.focus(), 100);
    }

    function closeClaimModal() {
        claimModal.classList.remove('active');
        pinInput.value = '';
    }

    // Close if clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === claimModal) closeClaimModal();
    });
</script>

</body>
</html>