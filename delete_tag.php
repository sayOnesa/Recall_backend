<?php
/* Creates or updates a user's journal */
require_once './index.php';

require_login();

/* This session variable is just for testing purposes right now */
$user_id = $_SESSION['user_id'];

// The fields that must be sent in the POST request
$required_fields = ['id', 'tag'];

try {
    $post_data = json_decode(file_get_contents("php://input"), true);

    verify_csrf_token($post_data);

    // Ensure POST request has data
    if(empty($post_data)) {
        throw new Exception("No POST data", 400);
    }

    // Ensure no input fields are missing or empty
    foreach ($required_fields as $field) {
        if (!isset($post_data[$field])) {
            throw new Exception("Missing input data", 400);
        }
    }

    // Verify that the inputted date is valid
    $date_string = trim($post_data['id']);
    if(!is_valid_date_string($date_string)) {
        throw new Exception("Invalid date string", 400);
    }

    $tag = htmlspecialchars(trim($post_data['tag']));

    // Create a new connection to the database
    $connection = connect_to_database($env);

    /* Get the journal's id */
    $sql = "SELECT id FROM Journals WHERE user_id = ? AND created_at = ?";
    $statement = $connection->prepare($sql);
    if(!$statement) {
        die("Error preparing SQL statement: " . $connection->error);
    }

    $statement->bind_param("is", $user_id,$date_string);
    if(!$statement->execute()) {
        die("Error executing SQL query");
    }

    $result = $statement->get_result();
    if($result->num_rows <= 0) {
        throw new Exception("User does not have a journal for this date", 400);
    }
    $row = $result->fetch_assoc();

    $journal_id = $row['id'];


    /* Delete tag from the Tags table */
    $sql = "DELETE FROM Tags WHERE user_id = ? AND journal_id = ? AND BINARY tag = ?";
    $deleteStatement = $connection->prepare($sql);
    if (!$deleteStatement) {
        die("Error preparing DELETE statement");
    }
    $deleteStatement->bind_param("iis", $user_id,  $journal_id, $tag);

    if (!$deleteStatement->execute()) {
        die("Error executing DELETE query");
    }   
    
    echo json_encode(['success' => true]);
    
    // Close database connections
    $connection->close();
    $statement->close();
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