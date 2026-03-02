<?php
// server/delete_journal.php
require_once './index.php';
session_start();

/* --- TEST LOGIN (remove in production) --- */
if (!isset($_SESSION['user_id'])) {
    // In production you'd redirect to login; here we set a test id
    $_SESSION['user_id'] = 29;
}
$user_id = $_SESSION['user_id'];

/* --- DB connection (your project's helper) --- */
$connection = connect_to_database($env);

header('Content-Type: application/json; charset=utf-8');

/* Required fields */
$required_fields = ['id']; // frontend sends date string "MM-DD-YYYY" in your setup

try {
    $raw = file_get_contents('php://input');
    $post_data = json_decode($raw, true);
    if (empty($post_data)) {
        throw new Exception("No POST data", 400);
    }

    // Optional: CSRF protection (uncomment if you set a token in session and send as header or body)
    /*
    if (!isset($post_data['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $post_data['csrf_token'])) {
        throw new Exception("Invalid CSRF token", 403);
    }
    */

    // Validate required fields
    foreach ($required_fields as $f) {
        if (!isset($post_data[$f]) || trim($post_data[$f]) === '') {
            throw new Exception("Missing required field: $f", 400);
        }
    }

    // Validate format of the date string (MM-DD-YYYY). Adjust regex to match your frontend format.
    $journal_date = $post_data['id'];
    if (!preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $journal_date)) {
        // this rejects arbitrary non-date IDs (prevents some attacks)
        throw new Exception("Invalid journal identifier format", 400);
    }

    // Use prepared statements (bind_param) to avoid SQL injection.
    // Delete by created_at (your DB uses date string), and user_id to ensure ownership
    $delete_sql = "DELETE FROM Journals WHERE created_at = ? AND user_id = ?";

    $stmt = $connection->prepare($delete_sql);
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $connection->error, 500);
    }

    // both values are strings in your schema; change "si" if user_id is int in DB
    $stmt->bind_param("ss", $journal_date, $user_id);
    $stmt->execute();

    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        echo json_encode(["success" => true, "message" => "Journal deleted successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "No matching journal found or not owned by you."]);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
