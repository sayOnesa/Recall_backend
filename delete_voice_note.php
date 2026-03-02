<?php
/* 
   Deletes a voice note with id if it's the current user's. 
*/
require_once './index.php';

require_login();

$user_id = $_SESSION['user_id'];

// Required fields for this endpoint
$required_fields = ['id'];
try {
    $post_data = json_decode(file_get_contents("php://input"), true);

    verify_csrf_token($post_data);

    // Ensure the request is not empty and has the required fields
    verify_fields_in_request_data($post_data, $required_fields);

    // Verify that the friend_id field is actually a number
    $id = $post_data['id'];
    if(!is_numeric($id)) {
        throw new Exception("Not a valid voice note id", 400);
    }

    // Connect to the database
    $connection = connect_to_database($env);

    $select_sql = "SELECT id FROM Voice_Notes WHERE id = ? AND user_id = ? LIMIT 1";
    $bindings = 'ii';
    $values = [$id, $user_id];
    if(!element_in_table($connection, $select_sql, $bindings, $values)) {
        throw new Exception("Voice note does not exist", 400);
    }

    // Delete the corresponding entry from the Voice_Notes table 
    $delete_sql = "DELETE FROM Voice_Notes WHERE id = ? AND user_id = ?";
    $bindings = 'ii';
    $values = [$id, $user_id];
    if(!delete_from_table($connection, $delete_sql, $bindings, $values)) {
        throw new Exception("Error processing friend request", 500);
    }

    echo json_encode(['success' => true, "message" => "Voice note deleted"]);
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