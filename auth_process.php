<?php
// MILELE - Secure Registration Processor (V8)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_SPECIAL_CHARS);
    $password = $_POST['password'];
    $university_name = filter_input(INPUT_POST, 'university_name', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($full_name) || empty($email) || empty($phone_number) || empty($password)) {
        die("ERROR: Please fill in all required fields. Go back and try again.");
    }

    if (!file_exists('db.php')) {
        die("CRITICAL ERROR: db.php is missing from the server.");
    }
    
    require 'db.php';

    try {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            die("ERROR: An account with this email already exists. Please log in.");
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $insert = $pdo->prepare("INSERT INTO users (full_name, email, phone_number, password_hash, university_name, account_state) 
                                 VALUES (:name, :email, :phone, :pass, :uni, 'active')");
        $insert->execute([
            ':name' => $full_name,
            ':email' => $email,
            ':phone' => $phone_number,
            ':pass' => $password_hash,
            ':uni' => $university_name ?? 'Not Specified'
        ]);

        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['full_name'] = $full_name;
        $_SESSION['account_state'] = 'active';

        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        die("CLOUD DB ERROR: " . $e->getMessage());
    }
} else {
    header("Location: login.php");
    exit();
}
?>