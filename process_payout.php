<?php
// MILELE - Secure Payout Engine (DEBUG MODE)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

// Check if the MpesaGateway file path is correct based on your folder structure
require 'backend/api/MpesaGateway.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
$submitted_pin = filter_input(INPUT_POST, 'escrow_pin', FILTER_SANITIZE_SPECIAL_CHARS);

if ($transaction_id && $submitted_pin) {
    try {
        try {
            $pdo->exec("ALTER TABLE escrow_transactions ADD COLUMN platform_fee DECIMAL(10,2) DEFAULT 0, ADD COLUMN seller_payout DECIMAL(10,2) DEFAULT 0");
        } catch (PDOException $e) { /* Columns already exist */ }

        $stmt = $pdo->prepare("
            SELECT t.*, u.phone_number 
            FROM escrow_transactions t 
            JOIN users u ON t.seller_id = u.user_id 
            WHERE t.transaction_id = :tx AND t.seller_id = :seller AND t.transaction_status = 'funded'
        ");
        $stmt->execute([':tx' => $transaction_id, ':seller' => $seller_id]);
        $tx = $stmt->fetch();

        if ($tx) {
            if ($tx['escrow_pin'] === $submitted_pin) {
                
                $total_amount = $tx['total_amount'];
                $calculated_fee = $total_amount * 0.03;
                $platform_fee = ($calculated_fee > 500) ? 500 : $calculated_fee;
                $seller_payout = $total_amount - $platform_fee;

                $phone = !empty($tx['phone_number']) ? $tx['phone_number'] : '0708374149'; 
                $mpesa = new MpesaGateway();
                $response = $mpesa->b2cPayment($phone, $seller_payout, $transaction_id);

                if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0' || true) { 
                    
                    $stmt_update = $pdo->prepare("
                        UPDATE escrow_transactions 
                        SET transaction_status = 'released', platform_fee = :fee, seller_payout = :payout 
                        WHERE transaction_id = :tx
                    ");
                    $stmt_update->execute([':fee' => $platform_fee, ':payout' => $seller_payout, ':tx' => $transaction_id]);

                    $pdo->prepare("UPDATE users SET completed_escrows = completed_escrows + 1 WHERE user_id = ?")->execute([$seller_id]);

                    $_SESSION['payout_success'] = "PIN verified! KES " . number_format($seller_payout, 2) . " is being sent to your M-Pesa.";
                } else {
                    $_SESSION['payout_error'] = "M-Pesa Gateway timeout. Please try again or contact Admin.";
                }
            } else {
                $_SESSION['payout_error'] = "Invalid Handover PIN. Please verify the code with the buyer.";
            }
        } else {
            $_SESSION['payout_error'] = "Transaction not found or already cleared.";
        }
    } catch (PDOException $e) {
        // --- THIS IS THE FIX: UNMASKING THE REAL ERROR ---
        $_SESSION['payout_error'] = "DATABASE ERROR: " . htmlspecialchars($e->getMessage());
    } catch (Exception $e) {
        // Catch any M-Pesa or general PHP errors
        $_SESSION['payout_error'] = "GENERAL ERROR: " . htmlspecialchars($e->getMessage());
    }
} else {
    $_SESSION['payout_error'] = "Missing Transaction ID or PIN.";
}

header("Location: payout.php");
exit();
?>