<?php
// MILELE Login Backend & Smart Router

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
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

// Get the user's inputs safely
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$plain_password = $_POST['password'] ?? '';

if (!$email || empty($plain_password)) {
    $_SESSION['error_msg'] = "Please enter both your email and password.";
    header("Location: login.php");
    exit();
}

$email_clean = strtolower(trim($email));

try {
    // Look up the user in the database
    $stmt = $pdo->prepare("SELECT user_id, full_name, password_hash, account_state FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email_clean]);
    $user = $stmt->fetch();

    // Check if user exists AND if the password matches the scrambled hash
    if ($user && password_verify($plain_password, $user['password_hash'])) {
        
        // Security check: Is the account suspended?
        if ($user['account_state'] === 'suspended') {
            $_SESSION['error_msg'] = "Your account has been suspended for violating campus marketplace rules.";
            header("Location: login.php");
            exit();
        }

        // Login successful! Save their identity to the session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['account_state'] = $user['account_state'];

        // SMART ROUTING: Where do they go next?
        if ($user['account_state'] === 'registered') {
            // They haven't verified their email yet. Send to Verification Hub.
            header("Location: verification_center.php");
            exit();
        } else {
            // They are fully verified! Send them to the main campus marketplace feed.
            header("Location: index.php"); // (We will build index.php soon)
            exit();
        }

    } else {
        // We use a generic error message so hackers don't know if they guessed the email right but password wrong.
        $_SESSION['error_msg'] = "Incorrect email or password. Please try again.";
        header("Location: login.php");
        exit();
    }

} catch (PDOException $e) {
    $_SESSION['error_msg'] = "Something went wrong on our end. Please try again.";
    header("Location: login.php");
    exit();
}