<?php
/* Inserts a voice note into the Voice_Notes table */
require_once './index.php';

require_login();

$user_id = $_SESSION['user_id'];

// Required fields for this endpoint
$required_fields = ['id', 'title'];

try {
    $post_data = json_decode(file_get_contents("php://input"), true);
    verify_csrf_token($post_data);

    // Verify that the date field is contained in the request
    verify_fields_in_request_data($post_data, $required_fields);

    // Verify that id is a numeric value
    if(!is_numeric($post_data['id'])) {
        throw new Exception("Invalid id", 400);
    }
    $id = $post_data['id'];

    // Verify that the title is not longer than 100 characters
    if(strlen($post_data['title']) > 100) {
        throw new Exception("Title too long", 400);
    }
    $title = htmlspecialchars($post_data['title']);

    // Connect to the database
    $connection = connect_to_database($env);

    $update_sql = "UPDATE Voice_Notes SET title = ? WHERE id = ? AND user_id = ?";
    $bindings = 'sii';
    $values = [$title, $id, $user_id];
    if(!insert_into_table($connection, $update_sql, $bindings, $values)) {
        throw new Exception("Error updating voice note", 500);
    }

    echo json_encode(['success' => true, "message" => "Voice note updated"]);
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