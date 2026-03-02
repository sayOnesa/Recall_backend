<?php
require_once "./index.php";

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$data = json_decode(file_get_contents("php://input"), true);
try {
    verify_csrf_token($data);

    // Destroy the session
    $_SESSION = [];
    session_destroy();
    if(ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Handle any errors caught by guard clauses
    $error_data = [
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ];

    echo json_encode($error_data);
    exit();
}
?>