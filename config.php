<?php
// MILELE - Universal Configuration Engine

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Dynamic Environment Detection
$is_localhost = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1');

if ($is_localhost) {
    // Load local secrets ONLY if the hidden file exists
    if (file_exists(__DIR__ . '/env.php')) {
        require_once __DIR__ . '/env.php';
    }
    $google_client_id = getenv('LOCAL_GOOGLE_ID');
    $google_client_secret = getenv('LOCAL_GOOGLE_SECRET');
    $google_redirect_uri = 'http://localhost/MILELE/oauth_callback.php';
} else {
    // Live Heroku Credentials (Pulled safely from Heroku Config Vars)
    $google_client_id = getenv('GOOGLE_CLIENT_ID');
    $google_client_secret = getenv('GOOGLE_CLIENT_SECRET');
    
    // Dynamically build your live Heroku URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $google_redirect_uri = $protocol . $_SERVER['HTTP_HOST'] . '/oauth_callback.php';
}

// 2. Generate Secure Google OAuth URL
$google_login_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'response_type' => 'code',
    'client_id'     => $google_client_id,
    'redirect_uri'  => $google_redirect_uri,
    'scope'         => 'openid email profile',
    'access_type'   => 'online',
    'prompt'        => 'select_account'
]);