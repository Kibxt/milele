<?php
// MILELE - Secure Registration Processor

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Capture and clean the input
    $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $university_name = filter_input(INPUT_POST, 'university_name', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($full_name) || empty($email) || empty($password)) {
        $_SESSION['error_msg'] = "Please fill in all required fields.";
        header("Location: login.php");
        exit();
    }

    // ⚡ THE MASTER CONNECTION
    require 'db.php';

    try {
        // 2. Check if the email already exists in the live database
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $_SESSION['error_msg'] = "An account with this email already exists.";
            header("Location: login.php");
            exit();
        }

        // 3. Securely hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // 4. Save the new user to the live cloud
        $insert = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, university_name, account_state) 
                                 VALUES (:name, :email, :pass, :uni, 'active')");
        $insert->execute([
            ':name' => $full_name,
            ':email' => $email,
            ':pass' => $password_hash,
            ':uni' => $university_name ?? 'Not Specified'
        ]);

        // 5. Instantly log them in and drop them into the feed
        $user_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['full_name'] = $full_name;
        $_SESSION['account_state'] = 'active';

        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "System error during registration. Please try again.";
        header("Location: login.php");
        exit();
    }
} else {
    // Kick out anyone trying to access this file directly
    header("Location: login.php");
    exit();
}
?>