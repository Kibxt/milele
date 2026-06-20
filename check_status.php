<?php
// MILELE - Invisible Payment Radar
require 'db.php';

header('Content-Type: application/json');

$checkout_id = filter_input(INPUT_GET, 'checkout_id', FILTER_SANITIZE_SPECIAL_CHARS);

if (!$checkout_id) {
    echo json_encode(['status' => 'pending']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT transaction_status FROM escrow_transactions WHERE mpesa_checkout_id = :id");
    $stmt->execute([':id' => $checkout_id]);
    $tx = $stmt->fetch();

    if ($tx) {
        echo json_encode(['status' => $tx['transaction_status']]);
    } else {
        echo json_encode(['status' => 'pending']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'pending']);
}
?>