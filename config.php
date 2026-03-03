<?php
/* File contains all configuration and constants values for web app */

// File containing all database connection information
$env = parse_ini_file("./config.ini");

// Base configuration for the HTTP header sent in HTTP responses to clients
header("Content-Type: application/json; charset=utf-8");

// Specify the frontend origin here
$frontend_origin = "https://recall-lnrz.onrender.com";

// CORS headers
// header("Access-Control-Allow-Origin: $frontend_origin");
// header("Access-Control-Allow-Credentials: true");
// header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// // Security headers
// header("X-Content-Type-Options: nosniff");
// header("X-Frame-Options: DENY");
// header("Content-Security-Policy: default-src 'self'");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialization for sessions
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

// Set session cookie parameters
session_set_cookie_params([
    'lifetime' => 1800,
    'path' => '/',                  // cookie valid site-wide
    'secure' => true,               // HTTPS only
    'httponly' => true,             // JS cannot read
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID every 30 minutes
$interval = 1800;
if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] >= $interval) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}
?>