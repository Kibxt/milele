<?php
// backend/api/test_b2c_payout.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// ==========================================
// 1. DARAJA B2C CREDENTIALS
// ==========================================
$consumerKey = 'Ww5JD2ePuyRdenPIVIC4yrf8b0we5YN5eAqbGhFZtGAEGC2k';
$consumerSecret = '7hT2qAbFXJvU4bxFnZpM2uOR27ShC8U9lpi4CXeqepHOKrn4n8UoA6JNLo1U28IB';

// Standard Sandbox B2C Parameters
$InitiatorName = 'testapi';
$SecurityCredential = 'OdLLtAlBP9mn/PFyaMRL+b10fBCGZCwQoMUPcY5sN85tgdhI19VtsLcuDJMb37pJqQduV7aY6wSPZFpMQWwqCKUOQGh/2YWqJk7wqOiUr/0JrQj0Ylg9g6MqEmVN0RvcCUhwktomX/0LdDZc+jXm+F4lhLJ4Tp3eZqigrZ5ijShCxR9wI7N5s90cjpdfzcXzXv2pLBT0p6e07WKZJz8N4d2PseELwlhZgkJ3HTfnlPnxvkQQ4QVTpElkLV5wkrpq97rA3dQ9RkKadMZrBMHMMhkQ9q9NneIg0KbkGuccve0j1M/n3R0NpJi94deBAwdN53VJmyUTRMY3Bmkz1lhaAA=='; // From your Daraja B2C simulate page
$B2CShortCode = '600000'; // Standard Safaricom B2C Sandbox Shortcode
$Amount = 1; 
$ReceiverPhoneNumber = '254708374149'; // The seller's phone number receiving the money

// ==========================================
// 2. GENERATE THE 1-HOUR ACCESS TOKEN
// ==========================================
$credentials = base64_encode($consumerKey . ':' . $consumerSecret);
$auth_url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

$curl_auth = curl_init();
curl_setopt($curl_auth, CURLOPT_URL, $auth_url);
curl_setopt($curl_auth, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
curl_setopt($curl_auth, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl_auth, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl_auth, CURLOPT_SSL_VERIFYHOST, false);

$auth_raw_response = curl_exec($curl_auth);
$auth_response = json_decode($auth_raw_response);
curl_close($curl_auth);

if (!isset($auth_response->access_token)) {
    echo json_encode(["status" => "error", "message" => "Failed to generate access token for B2C."]);
    exit();
}
$access_token = $auth_response->access_token;

// ==========================================
// 3. TRIGGER THE B2C DISBURSEMENT (PAYOUT)
// ==========================================
$b2c_url = 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';

$curl_b2c = curl_init();
curl_setopt($curl_b2c, CURLOPT_URL, $b2c_url);
curl_setopt($curl_b2c, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);

$curl_post_data = [
    'InitiatorName' => $InitiatorName,
    'SecurityCredential' => $SecurityCredential,
    'CommandID' => 'BusinessPayment', // Tells Safaricom this is a standard payout
    'Amount' => $Amount,
    'PartyA' => $B2CShortCode,
    'PartyB' => $ReceiverPhoneNumber,
    'Remarks' => 'MILELE Escrow Payout',
    'QueueTimeOutURL' => 'https://milele-escrow.com/api/b2c_timeout.php',
    'ResultURL' => 'https://milele-escrow.com/api/b2c_result.php',
    'Occasion' => 'HandshakeVerified'
];

curl_setopt($curl_b2c, CURLOPT_POST, true);
curl_setopt($curl_b2c, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
curl_setopt($curl_b2c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl_b2c, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl_b2c, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($curl_b2c);
curl_close($curl_b2c);

echo $response;
?>