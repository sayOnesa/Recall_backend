<?php
/* File contains all configuration and constants values for web app */

// File containing all database connection information
$env = parse_ini_file("./config.ini");

// Base configure for the HTTP header sent in HTTP responses to clients 
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: https://aptitude.cse.buffalo.edu");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("X-Content-Type-Options: nosniff");
header(header: "X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialization for sessions
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

session_set_cookie_params([
    'lifetime' => 1800,
    'domain' => 'aptitude.cse.buffalo.edu',
    'path' => '/CSE442/2025-Fall/cse-442g/',
    'secure' => true,
    'httponly' => true,
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$interval = 1800;
if(!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] >= $interval) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}
?>