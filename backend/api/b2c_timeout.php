<?php
// backend/api/b2c_timeout.php

header("Content-Type: application/json");

$safaricom_timeout = file_get_contents('php://input');

$logFile = "mpesa_b2c_logs.txt";
$log = fopen($logFile, "a");
fwrite($log, "[" . date('Y-m-d H:i:s') . "] DARAJA TIMEOUT NOTIFICATION: " . PHP_EOL);
fwrite($log, $safaricom_timeout . PHP_EOL);
fwrite($log, "---------------------------------------------------" . PHP_EOL);
fclose($log);

echo json_encode([
    "ResultCode" => 0,
    "ResultDesc" => "Accepted"
]);
?>