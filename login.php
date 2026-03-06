<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

$frontend_origin = "https://recall-lnrz.onrender.com";

require "./index.php";

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: $frontend_origin");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {

    /* -------------------------
       Rate limiting
    ------------------------- */

    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $rateLimitFile = sys_get_temp_dir() . '/login_attempts_' . md5($ipAddress);

    $currentTime = time();
    $timeWindow = 60;
    $maxAttempts = 5;

    if (file_exists($rateLimitFile)) {

        $attempts = json_decode(file_get_contents($rateLimitFile), true) ?? [];

        $attempts = array_filter($attempts, function($timestamp) use ($currentTime, $timeWindow) {
            return ($currentTime - $timestamp) < $timeWindow;
        });

        if (count($attempts) >= $maxAttempts) {
            http_response_code(429);
            echo json_encode([
                "success" => false,
                "message" => "Too many login attempts. Please try again later."
            ]);
            exit;
        }

        $attempts[] = $currentTime;
        file_put_contents($rateLimitFile, json_encode($attempts));

    } else {
        file_put_contents($rateLimitFile, json_encode([$currentTime]));
    }

    /* -------------------------
       Read request data
    ------------------------- */

    $raw = file_get_contents("php://input");

    if (!$raw) {
        throw new Exception("Empty request body");
    }

    $data = json_decode($raw, true);

    if (!$data) {
        throw new Exception("Invalid JSON request");
    }

    $email = isset($data['email'])
        ? trim(htmlspecialchars($data['email'], ENT_QUOTES, 'UTF-8'))
        : '';

    $password = $data['password'] ?? '';

    if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid email format."
        ]);
        exit;
    }

    /* -------------------------
       Database connection
    ------------------------- */

    $mysqli = connect_to_database($env);

    if (!$mysqli) {
        throw new Exception("Database connection failed");
    }

    /* -------------------------
       Prepare query
    ------------------------- */

    $stmt = $mysqli->prepare(
        "SELECT id, email, username, first_name, last_name, profile_picture, password_hash 
         FROM Users WHERE email = ? LIMIT 1"
    );

    if (!$stmt) {
        throw new Exception("Failed to prepare SQL statement");
    }

    $stmt->bind_param("s", $email);

    if (!$stmt->execute()) {
        throw new Exception("Failed to execute SQL query");
    }

    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception("Failed to retrieve query result");
    }

    $profile = $result->fetch_assoc();

    /* -------------------------
       Authentication
    ------------------------- */

    if (!$profile) {

        echo json_encode([
            "success" => false,
            "message" => "Sorry, we couldn't find your account!"
        ]);

        exit;
    }

    if (!password_verify($password, $profile['password_hash'])) {

        echo json_encode([
            "success" => false,
            "message" => "Incorrect password!"
        ]);

        exit;
    }

    /* -------------------------
       Successful login
    ------------------------- */

    $csrfToken = bin2hex(random_bytes(32));

    $_SESSION['csrf_token'] = $csrfToken;
    $_SESSION['user_id'] = $profile['id'];
    $_SESSION['email'] = $profile['email'];
    $_SESSION['username'] = $profile['username'];
    $_SESSION['first_name'] = $profile['first_name'];
    $_SESSION['last_name'] = $profile['last_name'];

    if ($profile['profile_picture'] != null) {
        $_SESSION['profile_picture'] = "server/uploads/" . $profile['profile_picture'];
    } else {
        $_SESSION['profile_picture'] = null;
    }

    error_log("Successful login: " . $email);

    echo json_encode([
        "success" => true,
        "message" => "Login successful.",
        "user" => [
            "email" => $profile['email'],
            "username" => $profile['username'],
            "first_name" => $profile['first_name'],
            "last_name" => $profile['last_name'],
            "profile_picture" => $_SESSION['profile_picture']
        ],
        "csrf_token" => $csrfToken
    ]);

    $stmt->close();
    $mysqli->close();

} catch (Exception $e) {

    http_response_code(500);

    error_log("Login error: " . $e->getMessage());

    echo json_encode([
        "success" => false,
        "message" => "Server error",
        "debug" => $e->getMessage()
    ]);

    exit;
}