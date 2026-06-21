<?php
// MILELE - Admin God-Mode Dispute Resolution

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require 'db.php';
require 'backend/api/MpesaGateway.php';

$admin_id = $_SESSION['user_id'];

// 1. Double Verification: Ensure this user is actually an Admin
$stmt_check = $pdo->prepare("SELECT is_admin FROM users WHERE user_id = :id");
$stmt_check->execute([':id' => $admin_id]);
$admin = $stmt_check->fetch();

if (!$admin || $admin['is_admin'] != 1 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS);

if ($transaction_id && ($action === 'refund' || $action === 'force_pay')) {
    try {
        // Fetch the transaction and the phone numbers of both parties
        $stmt = $pdo->prepare("
            SELECT t.*, b.phone_number as buyer_phone, s.phone_number as seller_phone 
            FROM escrow_transactions t
            JOIN users b ON t.buyer_id = b.user_id
            JOIN users s ON t.seller_id = s.user_id
            WHERE t.transaction_id = :tx_id AND (t.transaction_status = 'funded' OR t.transaction_status = 'disputed')
        ");
        $stmt->execute([':tx_id' => $transaction_id]);
        $tx = $stmt->fetch();

        if ($tx) {
            $mpesa = new MpesaGateway();
            
            if ($action === 'refund') {
                // Return money to the BUYER
                $phone = !empty($tx['buyer_phone']) ? $tx['buyer_phone'] : '0708374149';
                $response = $mpesa->b2cPayment($phone, $tx['total_amount'], $transaction_id);
                
                if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
                    $pdo->prepare("UPDATE escrow_transactions SET transaction_status = 'failed' WHERE transaction_id = ?")->execute([$transaction_id]);
                }
                
            } elseif ($action === 'force_pay') {
                // Push money to the SELLER
                $phone = !empty($tx['seller_phone']) ? $tx['seller_phone'] : '0708374149';
                $response = $mpesa->b2cPayment($phone, $tx['total_amount'], $transaction_id);
                
                if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
                    $pdo->prepare("UPDATE escrow_transactions SET transaction_status = 'released' WHERE transaction_id = ?")->execute([$transaction_id]);
                    $pdo->prepare("UPDATE users SET completed_escrows = completed_escrows + 1 WHERE user_id = ?")->execute([$tx['seller_id']]);
                }
            }
        }
    } catch (PDOException $e) {
        // Catch DB errors safely
    }
}

header("Location: admin_dashboard.php");
exit();
?>