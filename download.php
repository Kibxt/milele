<?php
// MILELE - Digital Vault (Download Hub)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

$transaction_id = filter_input(INPUT_GET, 'tx', FILTER_VALIDATE_INT);
if (!$transaction_id) {
    header("Location: index.php");
    exit();
}

try {
    // Verify the user actually bought this item
    $stmt = $pdo->prepare("
        SELECT t.*, l.title, l.file_path 
        FROM escrow_transactions t
        JOIN listings l ON t.listing_id = l.listing_id
        WHERE t.transaction_id = :tx AND t.buyer_id = :buyer AND l.item_type = 'digital'
    ");
    $stmt->execute([':tx' => $transaction_id, ':buyer' => $_SESSION['user_id']]);
    $receipt = $stmt->fetch();

    if (!$receipt) {
        die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>Access Denied. You do not own this item.</div>");
    }

} catch (PDOException $e) {
    die("<div style='background:#000; color:#F87171; padding:50px; text-align:center;'>System error loading your file.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download | MILELE</title>
    <style>
        body { background: #000; color: #fff; font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .success-box { background: rgba(45,212,191,0.05); border: 1px solid rgba(45,212,191,0.2); padding: 50px; border-radius: 24px; text-align: center; max-width: 500px; }
        h1 { color: #2DD4BF; margin-bottom: 10px; }
        p { color: #aaa; margin-bottom: 30px; line-height: 1.5; }
        .btn-download { display: inline-block; padding: 15px 40px; background: #fff; color: #000; text-decoration: none; font-weight: bold; border-radius: 12px; transition: 0.2s; }
        .btn-download:hover { background: #2DD4BF; transform: translateY(-2px); }
        .back { display: block; margin-top: 30px; color: #888; text-decoration: none; }
    </style>
</head>
<body>

<div class="success-box">
    <h1>Payment Successful ⚡</h1>
    <p>You now have full access to <strong><?php echo htmlspecialchars($receipt['title']); ?></strong>.</p>
    
    <?php if (!empty($receipt['file_path'])): ?>
        <a href="<?php echo htmlspecialchars($receipt['file_path']); ?>" download class="btn-download">Download File</a>
    <?php else: ?>
        <p style="color: #F87171;">Error: The seller did not attach a valid file.</p>
    <?php endif; ?>

    <a href="index.php" class="back">← Return to Feed</a>
</div>

</body>
</html>