<?php
// db.php - MILELE Master Database Connector

$jawsdb_url = getenv('JAWSDB_URL');

if ($jawsdb_url) {
    // We are live on Heroku! Parse the dynamic URL.
    $url = parse_url($jawsdb_url);
    $db_host = $url["host"];
    $db_user = $url["user"];
    $db_pass = $url["pass"];
    $db_name = substr($url["path"], 1);
} else {
    // We are local OR the env variable failed. Use your exact credentials.
    $db_host = 'd6ybckq58s9ru745.cbetxkdyhwsb.us-east-1.rds.amazonaws.com';
    $db_name = 'd3q5eq3iaqfixhhz';
    $db_user = 'g176xod6z1b2ltaj';
    $db_pass = 'py2fe6h52ohvj9re';
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("System Offline: Database connection failed.");
}
?>