<?php
require_once './index.php';
require_login();

ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");

// Get entry_id from URL
$entry_id = $_GET['entry_id'] ?? null;

if (!$entry_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing entry_id"]);
    exit();
}

try {
    $conn = connect_to_database($env);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed: " . $e->getMessage()]);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT id, user_id, title, content, created_at,mood FROM Journals WHERE id = ? LIMIT 1");

    $stmt->bind_param("i", $entry_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Journal entry not found"]);
        exit();
    }

    $row = $result->fetch_assoc();

    echo json_encode([
        "success" => true,
        "entry" => [
            "id" => $row['id'],
            "date" => $row['created_at'],
            "owner_id" => $row["user_id"],
            "title" => htmlspecialchars_decode($row['title']),
            "content" => $row['content'],
            "mood" => $row['mood']
        ]
    ]);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
}
?>