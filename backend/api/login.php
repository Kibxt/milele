<?php
// backend/api/login.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../AuthController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Use POST."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

$email    = $data['email'] ?? ($_POST['email'] ?? null);
$password = $data['password'] ?? ($_POST['password'] ?? null);

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email and password fields are required."]);
    exit();
}

$auth = new AuthController();
$result = $auth->login($email, $password);

if ($result['status'] === 'success') {
    http_response_code(200); // 200 OK
} else {
    http_response_code(401); // 401 Unauthorized
}

echo json_encode($result);
?>