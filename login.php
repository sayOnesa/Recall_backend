<?php
require "./index.php";
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
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

// IP-based rate limiting
$ipAddress = $_SERVER['REMOTE_ADDR'];
$rateLimitFile = sys_get_temp_dir() . '/login_attempts_' . md5($ipAddress);
$currentTime = time();
$timeWindow = 60; // 5 min
$maxAttempts = 5;

if (file_exists($rateLimitFile)) {
    $attempts = json_decode(file_get_contents($rateLimitFile), true);
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

    // Add current attempt
    $attempts[] = $currentTime;
    file_put_contents($rateLimitFile, json_encode($attempts));
} else {
    // First attempt
    file_put_contents($rateLimitFile, json_encode([$currentTime]));
}

$data = json_decode(file_get_contents("php://input"), true); //Gets request from frontend

$email = isset($data['email']) ? trim(htmlspecialchars($data['email'], ENT_QUOTES, 'UTF-8')) : '';
$password = $data['password'] ?? '';

if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email format."
    ]);
    exit;
}


$mysqli = connect_to_database($env);
$stmt = $mysqli->prepare("SELECT * FROM Users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();

try {
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
    } else {
        $csrfToken = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $csrfToken;
        $_SESSION['user_id'] = $profile['id'];
        $_SESSION['email'] = $profile['email'];
        $_SESSION['username'] = $profile['username'];
        $_SESSION['first_name'] = $profile['first_name'];
        $_SESSION['last_name'] = $profile['last_name'];
        $_SESSION['profile_picture'] = "server/uploads/" . $profile['profile_picture'];
        
        if ($profile['profile_picture'] != null){
            $_SESSION['profile_picture'] = "server/uploads/" . $profile['profile_picture'];
        } else {
            $_SESSION['profile_picture'] = null;
        }

        // Log successful login
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
    }
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "message" => $e->getMessage()
    ]);
    exit();
}
$mysqli->close();
?>