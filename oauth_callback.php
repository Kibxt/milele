<?php
// MILELE - Google Single Sign-On Receiver

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require 'db.php';

// 1. Check if Google sent us the authorization code
if (isset($_GET['code'])) {
    
    // 2. Trade the code for an Access Token
    $token_url = 'https://oauth2.googleapis.com/token';
    $post_data = [
        'code'          => $_GET['code'],
        'client_id'     => $google_client_id,
        'client_secret' => $google_client_secret,
        'redirect_uri'  => $google_redirect_uri,
        'grant_type'    => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $token_response = curl_exec($ch);
    curl_close($ch);

    $token_data = json_decode($token_response, true);

    // 3. If we got the token, ask Google for the user's profile info
    if (isset($token_data['access_token'])) {
        $info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $info_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_data['access_token']]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $user_info_response = curl_exec($ch);
        curl_close($ch);

        $google_user = json_decode($user_info_response, true);

        // 4. Process the User into the MILELE Database
        if (isset($google_user['email'])) {
            $email = strtolower($google_user['email']);
            $full_name = $google_user['name'];
            $oauth_uid = $google_user['id'];

            try {
                // Check if this email already exists in your database
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $existing_user = $stmt->fetch();

                if ($existing_user) {
                    // USER EXISTS: Update their record to show they use Google, and log them in
                    $update_stmt = $pdo->prepare("UPDATE users SET oauth_provider = 'google', oauth_uid = ?, is_verified = 1 WHERE email = ?");
                    $update_stmt->execute([$oauth_uid, $email]);

                    $_SESSION['user_id'] = $existing_user['user_id'];
                    header("Location: profile.php"); // Redirect to their dashboard
                    exit();
                } else {
                    // NEW USER: Create their account instantly (No email verification needed because Google verified them)
                    // Note: We set University to 'Not Selected' since Google doesn't know their school
                    $insert_stmt = $pdo->prepare("INSERT INTO users (full_name, email, university_name, password_hash, is_verified, oauth_provider, oauth_uid) VALUES (?, ?, 'Not Selected', '', 1, 'google', ?)");
                    $insert_stmt->execute([$full_name, $email, $oauth_uid]);

                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    header("Location: profile.php"); // Redirect to their dashboard
                    exit();
                }
            } catch (PDOException $e) {
                die("Database Routing Error: " . $e->getMessage());
            }
        }
    }
}

// If anything fails, send them back to the register page
header("Location: register.php?error=GoogleAuthFailed");
exit();
?>