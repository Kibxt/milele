<?php
// MILELE - Secure Registration Processor (V7 Cloud Diagnostic)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Force the server to show us the exact error if one happens
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $university_name = filter_input(INPUT_POST, 'university_name', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($full_name) || empty($email) || empty($password)) {
        die("ERROR: Please fill in all fields. Go back and try again.");
    }

    // Check if the brain file exists before trying to load it
    if (!file_exists('db.php')) {
        die("CRITICAL ERROR: db.php is missing from the server. The connection cannot be made.");
    }
    
    // ⚡ THE MASTER CONNECTION
    require 'db.php';

    try {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            die("ERROR: An account with this email already exists. Please log in.");
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $insert = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, university_name, account_state) 
                                 VALUES (:name, :email, :pass, :uni, 'active')");
        $insert->execute([
            ':name' => $full_name,
            ':email' => $email,
            ':pass' => $password_hash,
            ':uni' => $university_name ?? 'Not Specified'
        ]);

        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['full_name'] = $full_name;
        $_SESSION['account_state'] = 'active';

        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        // If it fails now, it will print the exact AWS cloud error on your screen
        die("CLOUD DB ERROR: " . $e->getMessage());
    }
} else {
    header("Location: login.php");
    exit();
}
?>