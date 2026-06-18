<?php
// MILELE - Secure Message Processor (Fixed Double-Encoding)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Gate
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header("Location: inbox.php");
    exit();
}

$sender_id = $_SESSION['user_id'];
$listing_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
$receiver_id = filter_input(INPUT_POST, 'receiver_id', FILTER_VALIDATE_INT);

// THE FIX: We grab the raw text here. We protect against hackers in chat.php on the way out.
$message_text = trim($_POST['message_text']);

// Don't send empty ghosts
if (!$listing_id || !$receiver_id || empty($message_text)) {
    header("Location: chat.php?listing_id=$listing_id&user=$receiver_id");
    exit();
}

// Database Connection
$db_host = 'localhost'; $db_name = 'milele_escrow'; $db_user = 'root'; $db_pass = ''; 
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("System Error.");
}

try {
    $insert_sql = "INSERT INTO messages (listing_id, sender_id, receiver_id, message_text) 
                   VALUES (:lid, :sid, :rid, :txt)";
    $stmt = $pdo->prepare($insert_sql);
    $stmt->execute([
        ':lid' => $listing_id,
        ':sid' => $sender_id,
        ':rid' => $receiver_id,
        ':txt' => $message_text
    ]);

    // Instantly bounce them back to the chat screen
    header("Location: chat.php?listing_id=$listing_id&user=$receiver_id");
    exit();

} catch (PDOException $e) {
    header("Location: chat.php?listing_id=$listing_id&user=$receiver_id");
    exit();
}