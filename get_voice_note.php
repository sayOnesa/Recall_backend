<?php
require_once './index.php';

require_login();

// ⭐ Allow shared journal to specify the owner's id
// If no owner_id passed, default to logged-in user
$user_id = isset($_GET['owner_id']) 
           ? intval($_GET['owner_id']) 
           : $_SESSION['user_id'];

$errors = [];

$required_fields = ['id', 'date'];

try {
    // Check that all required fields are present in $_GET
    foreach($required_fields as $field) {
        if(!isset($_GET[$field]) || empty($_GET[$field])) {
            throw new Exception("Missing input field", 400);
        }
    }

    // Verify that the inputted id is a number
    $id = $_GET['id'];
    if(!is_numeric($id)) {
        throw new Exception("Invalid voice note id", 400);
    }

    // Verify that the inputted date is valid
    // ⭐ Your created_at column is stored exactly as "MM-DD-YYYY"
    // So use the string directly with no conversion
    $date_string = trim($_GET['date']);
    if(!is_valid_date_string($date_string)) {
        throw new Exception("Invalid date string", 400);
    }

    // Create a new connection to the database
    $connection = connect_to_database($env);
    
    // ⭐ Your DB stores created_at as STRING "MM-DD-YYYY"
    // So query must use the exact same string
    $select_sql = "SELECT audio 
                   FROM Voice_Notes 
                   WHERE id = ? AND user_id = ? AND created_at = ?";

    $bindings = "iis";
    $values = [$id, $user_id, $date_string];

    $rows = get_rows_from_table($connection, $select_sql, $bindings, $values);

    if (!$rows || !isset($rows[0])) {
        throw new Exception("Audio not found", 404);
    }
    
    $audioBlob = $rows[0]['audio'];

    // Output the audio blob
    header('Content-Type: audio/webm');
    header('Content-Length: ' . strlen($audioBlob));
    header('Content-Disposition: inline; filename="voice_note.webm"');
    header('Cache-Control: no-cache');
    echo $audioBlob;
    exit();

 } catch (Exception $e) {
    // Handle any errors caught by guard clauses
    $error_data = [
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage(),
        'errors' => $errors,
        'code' => $e->getCode()
    ];

    // ⭐ Important: JSON should not be served as audio
    header("Content-Type: application/json");
    echo json_encode($error_data);
    exit();
}
?>
