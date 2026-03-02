<?php
require_once "./index.php";
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

$data = json_decode(file_get_contents("php://input"), true);
if ($data['token'] != $_SESSION['csrf_token']) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid cookie"
    ]);
    exit();
} else {
    echo json_encode([
        "success" => true,
        "message" => "Cookie valid",
        "data" => [
            "username" => $_SESSION['username'], 
            "email" => $_SESSION['email'], 
            "first_name" => $_SESSION['first_name'],
            "last_name" => $_SESSION['last_name'],
            "profile_picture" => $_SESSION['profile_picture']
        ]
    ]);
    exit();
}
?>