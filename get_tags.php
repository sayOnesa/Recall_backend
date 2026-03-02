<?php
/* Creates or updates a user's journal */

require_once './index.php';

require_login();

$user_id = $_SESSION['user_id'];

// The fields that must be sent in the POST request
$required_fields = ['id'];
try {

    if(!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception("No date set", 400);
    }

    // Verify that the inputted date is valid
    $date_string = trim($_GET['id']);
    if(!is_valid_date_string($date_string)) {
        throw new Exception("Invalid date string", 400);
    }

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

    /* Insert tag into the Tags table */
    $sql = "SELECT tag FROM Tags WHERE user_id = ? AND journal_id = ?";
    $selectStatement = $connection->prepare($sql);
    if (!$selectStatement) {
        die("Error preparing SELECT statement");
    }
    $selectStatement->bind_param("ii", $user_id,  $journal_id);

    if (!$selectStatement->execute()) {
        die("Error executing SELECT query");
    }

    $result = $selectStatement->get_result();
    
    $tags = [];
    while ($row = $result->fetch_assoc()) {
        $tags[] = htmlspecialchars_decode($row['tag']);
    }

    echo json_encode(['success' => true, 'tags' => $tags]);
    
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