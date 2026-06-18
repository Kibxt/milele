<?php
// backend/api/test_stk_push.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// ==========================================
// 1. INTEGRATED DARAJA SANDBOX CREDENTIALS
// ==========================================
$consumerKey = 'Ww5JD2ePuyRdenPIVIC4yrf8b0we5YN5eAqbGhFZtGAEGC2k';
$consumerSecret = '7hT2qAbFXJvU4bxFnZpM2uOR27ShC8U9lpi4CXeqepHOKrn4n8UoA6JNLo1U28IB';
$Passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
$TestPhoneNumber = '254708374149'; 

$BusinessShortCode = '174379'; // Standard Safaricom Express Sandbox Shortcode
$Amount = 1; // Testing with 1 Ksh

// ==========================================
// 2. GENERATE DYNAMIC SAFARICOM PASSWORD
// ==========================================
date_default_timezone_set('Africa/Nairobi');
$Timestamp = date('YmdHis');
$Password = base64_encode($BusinessShortCode . $Passkey . $Timestamp);

// ==========================================
// 3. GENERATE THE 1-HOUR ACCESS TOKEN
// ==========================================
$credentials = base64_encode($consumerKey . ':' . $consumerSecret);
$auth_url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

$curl_auth = curl_init();
curl_setopt($curl_auth, CURLOPT_URL, $auth_url);
curl_setopt($curl_auth, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
curl_setopt($curl_auth, CURLOPT_RETURNTRANSFER, true);

// Bypass SSL verification restrictions for local XAMPP environments
curl_setopt($curl_auth, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($curl_auth, CURLOPT_SSL_VERIFYHOST, false); 

$auth_raw_response = curl_exec($curl_auth);
$auth_error = curl_error($curl_auth); 
$auth_info = curl_getinfo($curl_auth); 
curl_close($curl_auth);

// Check for network connection failures
if ($auth_raw_response === false) {
    echo json_encode([
        "status" => "error",
        "message" => "Network connection failed completely before reaching Safaricom.",
        "curl_error" => $auth_error,
        "http_code" => $auth_info['http_code']
    ]);
    exit();
}

$auth_response = json_decode($auth_raw_response);

// Check if keys were rejected by Safaricom's gateway
if (!isset($auth_response->access_token)) {
    echo json_encode([
        "status" => "error",
        "message" => "Safaricom actively rejected the authentication credentials.",
        "http_code" => $auth_info['http_code'],
        "safaricom_response" => $auth_response
    ]);
    exit();
}

$access_token = $auth_response->access_token;

// ==========================================
// 4. TRIGGER THE LIVE STK PUSH PROMPT
// ==========================================
$stk_url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

$curl_stk = curl_init();
curl_setopt($curl_stk, CURLOPT_URL, $stk_url);
curl_setopt($curl_stk, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);

$curl_post_data = [
    'BusinessShortCode' => $BusinessShortCode,
    'Password' => $Password,
    'Timestamp' => $Timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => $Amount,
    'PartyA' => $TestPhoneNumber,
    'PartyB' => $BusinessShortCode,
    'PhoneNumber' => $TestPhoneNumber,
    'CallBackURL' => 'https://milele-escrow.com/api/mpesa_webhook.php', 
    'AccountReference' => 'MILELE Escrow',
    'TransactionDesc' => 'MacBook Pro Payment'
];

curl_setopt($curl_stk, CURLOPT_POST, true);
curl_setopt($curl_stk, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
curl_setopt($curl_stk, CURLOPT_RETURNTRANSFER, true);

// Bypass SSL restrictions for the STK push payload transfer
curl_setopt($curl_stk, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl_stk, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($curl_stk);
$stk_error = curl_error($curl_stk);
curl_close($curl_stk);

if ($response === false) {
    echo json_encode([
        "status" => "error",
        "message" => "Token was generated, but the STK Push endpoint failed to respond.",
        "curl_error" => $stk_error
    ]);
    exit();
}

// Return Safaricom's direct response block
echo $response;
?>