<?php
/* Creates a friend request entry in the Friend_Requests table */
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

    if($friend_id === $user_id) {
        throw new Exception("Cannot request yourself as a friend", 400);
    }


    // Verify that friend_id belongs to an actual user
    $select_sql = "SELECT id FROM Users WHERE id = ? LIMIT 1";
    $bindings = 'i';
    $values = [$friend_id];
    if(!element_in_table($connection, $select_sql, $bindings, $values)) {
        throw new Exception("This user does not exist");
    }

    // Verify this friendship doesn't already exist
    $select_sql = "SELECT user1_id FROM Friends WHERE user1_id = ? AND user2_id = ? LIMIT 1";
    $bindings = 'ii';
    $values = [$user_id, $friend_id];
    sort($values);
    if(element_in_table($connection, $select_sql, $bindings, $values)) {
        throw new Exception("Friendship already exists", 400);
    }

    // Verify this friend request does not already exist
    $select_sql = "SELECT id FROM Friend_Requests WHERE user_id = ? AND friend_id = ? LIMIT 1";
    $bindings = 'ii';
    $values = [$user_id, $friend_id];
    if(element_in_table($connection, $select_sql, $bindings, $values)) {
        throw new Exception("Friend request already send", 400);
    }

    $connection->begin_transaction();

    // Update the Friend_Requests table
    $insert_sql = "INSERT INTO Friend_Requests (user_id, friend_id) VALUES (?, ?)";
    if(!insert_into_table($connection, $insert_sql, $bindings, $values)) {
        throw new Exception("Error sending friend request", 500);
    }

    // Update the Notifications table
    $insert_sql = "INSERT INTO Notifications (user_id, actor_id, type, message, is_read, created_at)
                   VALUES (?, ?, ?, ?, 0, NOW())";
    $bindings = 'iiss';
    $values = [$friend_id, $user_id, "friend_request", "wants to be your friend"];
    if(!insert_into_table($connection, $insert_sql, $bindings, $values)) {
        throw new Exception("Error sending notification", 500);
    }

    $connection->commit();

    echo json_encode(['success' => true, "message" => "Friend request sent"]);

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