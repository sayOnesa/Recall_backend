<?php
/* 
    Rejects a friend request. NOTE: user_id corresponds to friend_id
    and friend_id corresponds to user_id in Friend_Requests table 
*/
require_once './index.php';

require_login();

$user_id = $_SESSION['user_id'];

try {
    $post_data = json_decode(file_get_contents("php://input"), true);

    verify_csrf_token($post_data);

    $connection = connect_to_database($env);

    // Delete the corresponding entry from the Friend_Requests table 
    $update_sql = "UPDATE Notifications SET is_read = 1 WHERE user_id = ? AND (type = ? OR type = ?)";
    $bindings = 'iss';
    $values = [$user_id, 'friend_request_accept', 'share_journal'];
    if(!insert_into_table($connection, $update_sql, $bindings, $values)) {
        throw new Exception("Error updating notifications", 500);
    }

    echo json_encode(['success' => true, "message" => "Notifications updated"]);

} catch(Exception $e) {
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