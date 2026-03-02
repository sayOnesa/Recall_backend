<?php
require_once './index.php';
require_login();

// In production, set to 0 to avoid breaking JSON output
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json");

// --- 1. Get session + request data safely ---
$user_id = $_SESSION['user_id'] ?? null;
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);

$entry_id = $data["entry_id"] ?? null;
$friend_id = $data["friend_id"] ?? null;

// Log raw input for debugging (does NOT appear in browser)
error_log("DEBUG: Raw POST input: " . $raw_input);
error_log("DEBUG: Parsed entry_id={$entry_id}, friend_id={$friend_id}, user_id={$user_id}");

// --- 2. Validate input ---
if (!$user_id || !$entry_id || !$friend_id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing parameters", "received" => $data]);
    exit();
}

// --- 3. Connect to database ---
try {
    $conn = connect_to_database($env);
    error_log("DEBUG: Connected to database successfully for user_id={$user_id}");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed", "details" => $e->getMessage()]);
    exit();
}

// --- 4. Insert notification ---
try {
    $stmt = $conn->prepare("
        INSERT INTO Notifications (user_id, actor_id, entry_id_shared, type, message, is_read)
        VALUES (?, ?, ?, 'share_journal', ?, 0)
    ");

    $message = "shared their journal with you!";
    $stmt->bind_param("iiis", $friend_id, $user_id, $entry_id, $message);
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Entry shared successfully",
        "entry_id_shared" => $entry_id,
        "shared_with" => $friend_id
    ]);

} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        // Duplicate entry (already shared)
        echo json_encode([
            "success" => false,
            "message" => "This entry has already been shared with this user."
        ]);
    } else {
        error_log("MySQL Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "Database error", "details" => $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Unexpected error", "details" => $e->getMessage()]);
}
?>