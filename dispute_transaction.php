<?php
// MILELE - Dispute Trigger & Vault Freeze

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

require 'db.php';

$buyer_id = $_SESSION['user_id'];
$transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);

if ($transaction_id) {
    try {
        // Only allow disputes if the transaction is currently 'funded' and belongs to this buyer
        $stmt = $pdo->prepare("UPDATE escrow_transactions SET transaction_status = 'disputed' WHERE transaction_id = :tx_id AND buyer_id = :buyer AND transaction_status = 'funded'");
        $stmt->execute([':tx_id' => $transaction_id, ':buyer' => $buyer_id]);
    } catch (PDOException $e) {
        // Silently catch errors in production
    }
}

header("Location: profile.php");
exit();
?>