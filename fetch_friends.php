<?php
require_once './index.php';
require_login();

ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");

// Hardcode user for debugging
$user_id = $_SESSION['user_id'];

try {
    $connection = connect_to_database($env);
    error_log("Connected successfully to database for user_id: $user_id");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
    exit();
}

// Fetch friends using UNION for clarity
$sql = "
    SELECT 
        U.id AS friend_id, 
        U.first_name, 
        U.last_name, 
        U.username,
        U.profile_picture
    FROM Friends F
    JOIN Users U ON F.user1_id = ? AND F.user2_id = U.id

    UNION

    SELECT 
        U.id AS friend_id, 
        U.first_name, 
        U.last_name, 
        U.username,
        U.profile_picture
    FROM Friends F
    JOIN Users U ON F.user2_id = ? AND F.user1_id = U.id
";

$stmt = $connection->prepare($sql);
if(!$stmt){
    error_log("Prepare failed: " . $connection->error);
    http_response_code(500);
    exit();
}

$stmt->bind_param("ii", $user_id, $user_id);
if(!$stmt->execute()){
    error_log("Execute failed: " . $stmt->error);
    http_response_code(500);
    exit();
}

$result = $stmt->get_result();
$friends = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $friends[] = $row;
    }
}

$response = [
    'friends' => $friends,
    'message' => empty($friends) ? 'No friends found' : 'Friends retrieved successfully'
];

echo json_encode($response, JSON_PRETTY_PRINT);

$stmt->close();
$connection->close();
?>