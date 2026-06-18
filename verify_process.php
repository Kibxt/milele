<?php
// MILELE Email Verification Backend

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Safety check: only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: verification_center.php");
    exit();
}

// Database Connection
$db_host = 'localhost';
$db_name = 'milele_escrow';
$db_user = 'root';
$db_pass = ''; 

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed. Please try again later.");
}

// Get the user's input
$otp_code = trim($_POST['otp_code'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: login.php");
    exit();
}

if (empty($otp_code) || strlen($otp_code) !== 4) {
    $_SESSION['error_msg'] = "Please enter a valid 4-digit code.";
    header("Location: verification_center.php");
    exit();
}

// ------------------------------------------------------------------
// FOR LOCAL TESTING: We are using "1234" as the universal test code.
// Later, we will match this against a code saved in the database.
// ------------------------------------------------------------------
$correct_code = "1234";

if ($otp_code === $correct_code) {
    
    try {
        // Upgrade their account state in the database!
        $update_stmt = $pdo->prepare("UPDATE users SET account_state = 'campus_verified' WHERE user_id = :user_id");
        $update_stmt->execute([':user_id' => $user_id]);

        // Update their session so the site knows they are verified
        $_SESSION['account_state'] = 'campus_verified';

        // Boom. Send them through the doors to the main marketplace!
        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "We had an issue updating your account. Please try again.";
        header("Location: verification_center.php");
        exit();
    }

} else {
    $_SESSION['error_msg'] = "Incorrect code. Please check your student email again.";
    header("Location: verification_center.php");
    exit();
}