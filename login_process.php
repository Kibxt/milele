<?php
// MILELE - Secure Login Processor

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $_SESSION['error_msg'] = "Please fill in all fields.";
        header("Location: login.php");
        exit();
    }

    // ⚡ THE MASTER CONNECTION
    require 'db.php';

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Login Success
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['account_state'] = $user['account_state'];

            if ($user['account_state'] === 'registered') {
                header("Location: verification_center.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $_SESSION['error_msg'] = "Invalid email or password.";
            header("Location: login.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "System error. Please try again later.";
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>