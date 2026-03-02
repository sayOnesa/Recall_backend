<?php
/* 
    Accepts a friend request. NOTE: user_id corresponds to friend_id
    and friend_id corresponds to user_id in Friend_Requests table 
*/
require_once './index.php';

require_login();

$user_id = $_SESSION['user_id'];

// Required fields for this endpoint
$required_fields = ['friend_id'];

// Declare connection here because we may have to 
// rollback this transaction if an error occurs
$connection = connect_to_database($env);

try {
    $post_data = json_decode(file_get_contents("php://input"), true);

    verify_csrf_token($post_data);

    // Ensure the request is not empty and has the required fields
    verify_fields_in_request_data($post_data, $required_fields);

    // Verify that the friend_id field is actually a number
    if(!is_numeric($post_data['friend_id'])) {
        throw new Exception("Not a valid friend id", 400);
    }
    $friend_id = $post_data['friend_id'];

    // Verify this friend request exists
    $select_sql = "SELECT id FROM Friend_Requests WHERE user_id = ? AND friend_id = ? LIMIT 1";
    $bindings = 'ii';
    $values = [$friend_id, $user_id];
    if(!element_in_table($connection, $select_sql, $bindings, $values)) {
        throw new Exception("Friend request does not exist", 400);
    }

    $connection->begin_transaction();

    // Update the Friends table
    $insert_sql = "INSERT INTO Friends (user1_id, user2_id) VALUES (?, ?)";
    $bindings = "ii";
    sort($values);
    if(!insert_into_table($connection, $insert_sql, $bindings, $values)) {
        throw new Exception("Error creating friendship", 500);
    }

    // Delete the corresponding entry from the Friend_Requests table 
    $delete_sql = "DELETE FROM Friend_Requests WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)";
    $bindings = 'iiii';
    $values = [$friend_id, $user_id, $user_id, $friend_id];
    if(!delete_from_table($connection, $delete_sql, $bindings, $values)) {
        throw new Exception("Error processing friend request", 500);
    }

    // Insert friend_request notification into Notifications table
    $insert_sql = "INSERT INTO Notifications (user_id, actor_id, type, message, is_read, created_at)
                   VALUES (?, ?, ?, ?, 0, NOW())";
    $bindings = 'iiss';
    $values = [$friend_id, $user_id, "friend_request_accept", "accepted your friend request"];
    if(!insert_into_table($connection, $insert_sql, $bindings, $values)) {
        throw new Exception("Error sending notification", 500);
    }

    // Delete the friend_request notifications from the Notifications table
    $delete_sql = "DELETE FROM Notifications WHERE (((user_id = ? AND actor_id = ?) OR (user_id = ? AND actor_id = ?)) AND type = ?) ";
    $bindings = "iiiis";
    $values = [$user_id, $friend_id, $friend_id, $user_id, 'friend_request'];
    if(!delete_from_table($connection, $delete_sql, $bindings, $values)) {
        throw new Exception("Error deleting notification", 500);
    }

    $connection->commit();

    echo json_encode(['success' => true, "message" => "Friend request accepted"]);

} catch(Exception $e) {
    // Handle any errors caught by guard clauses
    $error_data = [
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ];

    $connection->rollback();

    echo json_encode($error_data);
    exit();
}
?>