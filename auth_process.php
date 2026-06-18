<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$db_host = 'localhost'; $db_name = 'milele_escrow'; $db_user = 'root'; $db_pass = ''; 
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Database Connection Failed.");
}

$action = $_POST['action'] ?? '';

// REGISTER LOGIC
if ($action === 'register') {
    $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $email = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));
    $phone = trim(filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_SPECIAL_CHARS));
    $university = trim(filter_input(INPUT_POST, 'university_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $password = $_POST['password'];

    // Ensure they enter a full 9-digit number
    if (strlen($phone) !== 9) {
        $_SESSION['error_msg'] = "Please enter a valid 9-digit phone number (e.g., 712345678).";
        header("Location: register.php");
        exit();
    }

    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        $_SESSION['error_msg'] = "That email is already registered.";
        header("Location: register.php");
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    // Hardcoded format to standard +254 structure for the DB
    $formatted_phone = '254' . $phone;

    try {
        $insert_sql = "INSERT INTO users (full_name, email, phone_number, password_hash, university_name, account_state) 
                       VALUES (:name, :email, :phone, :pass, :uni, 'registered')";
        $stmt = $pdo->prepare($insert_sql);
        $stmt->execute([
            ':name' => $full_name,
            ':email' => $email,
            ':phone' => $formatted_phone,
            ':pass' => $hashed_password,
            ':uni' => $university
        ]);

        $user_id = $pdo->lastInsertId();
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['full_name'] = $full_name;
        $_SESSION['account_state'] = 'registered';

        // Send to Verification Center
        header("Location: verification_center.php");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Registration failed. Please try again.";
        header("Location: register.php");
        exit();
    }
}

// LOGIN LOGIC (Included for completeness)
if ($action === 'login') {
    $email = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
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
}
?>